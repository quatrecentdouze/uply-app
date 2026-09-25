<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Resources;

use App\StatusPages\Values\PublicPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicStatusPageResource extends JsonResource
{
    public function __construct(private readonly PublicPage $page)
    {
        parent::__construct($page);
    }

    public function toArray(Request $request): array
    {
        return [
            'title' => $this->page->title,
            'description' => $this->page->description,
            'overall_state' => $this->page->overallState,
            'monitors' => PublicMonitorResource::collection($this->page->monitors),
            'incidents' => PublicIncidentResource::collection($this->page->incidents),
        ];
    }
}
