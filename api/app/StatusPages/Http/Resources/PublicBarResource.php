<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Resources;

use App\StatusPages\Values\PublicBar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicBarResource extends JsonResource
{
    public function __construct(private readonly PublicBar $bar)
    {
        parent::__construct($bar);
    }

    public function toArray(Request $request): array
    {
        return [
            'date' => $this->bar->date,
            'uptime_percent' => $this->bar->uptimePercent,
            'maintenance' => $this->bar->maintenance,
        ];
    }
}
