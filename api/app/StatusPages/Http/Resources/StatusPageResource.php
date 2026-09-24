<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Resources;

use App\Shared\Time\IsoTime;
use App\StatusPages\Models\StatusPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StatusPageResource extends JsonResource
{
    public function __construct(private readonly StatusPage $page)
    {
        parent::__construct($page);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->page->id,
            'slug' => $this->page->slug,
            'title' => $this->page->title,
            'description' => $this->page->description,
            'is_published' => $this->page->is_published,
            'monitors' => PageMonitorResource::collection($this->page->monitors),
            'created_at' => IsoTime::format($this->page->created_at),
            'updated_at' => IsoTime::format($this->page->updated_at),
        ];
    }
}
