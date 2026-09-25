<?php

declare(strict_types=1);

namespace App\StatusPages\Storage;

use App\StatusPages\Models\StatusPage;
use Illuminate\Pagination\LengthAwarePaginator;

final class StatusPageQuery
{
    public function page(string $organizationId, int $perPage): LengthAwarePaginator
    {
        return StatusPage::query()->where('organization_id', $organizationId)->with('monitors')->orderBy('title')->orderBy('id')->paginate($perPage);
    }

    public function published(string $slug): ?StatusPage
    {
        return StatusPage::query()->where('slug', $slug)->where('is_published', true)->with('monitors')->first();
    }

    public function get(string $organizationId, string $pageId): StatusPage
    {
        return StatusPage::query()->where('organization_id', $organizationId)->whereKey($pageId)->with('monitors')->firstOrFail();
    }
}
