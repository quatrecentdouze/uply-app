<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Controllers;

use App\Monitoring\Http\Requests\CheckListRequest;
use App\Monitoring\Http\Resources\CheckResultResource;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Storage\CheckResultQuery;
use App\Monitoring\Storage\MonitorQuery;
use App\Shared\Clock;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use stdClass;

final readonly class CheckResultController
{
    public function __construct(
        private MonitorQuery $monitors,
        private CheckResultQuery $checks,
        private MonitoringSettings $settings,
        private Clock $clock,
    ) {}

    public function index(CheckListRequest $request, PaginationSettings $pagination, string $organization, string $monitor): AnonymousResourceCollection
    {
        $found = $this->monitors->get($organization, $monitor);

        $page = $this->checks->page(
            $found->id,
            $request->from($this->settings, $this->clock),
            $request->to($this->clock),
            $request->perPage($pagination),
        );

        return CheckResultResource::collection($page->through(
            fn (mixed $row): mixed => $row instanceof stdClass ? new CheckResultResource(CheckResultQuery::record($row), $row) : $row,
        ));
    }
}
