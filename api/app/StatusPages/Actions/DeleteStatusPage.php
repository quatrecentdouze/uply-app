<?php

declare(strict_types=1);

namespace App\StatusPages\Actions;

use App\StatusPages\Models\StatusPage;
use App\StatusPages\Storage\PublicPageCache;

final readonly class DeleteStatusPage
{
    public function __construct(private PublicPageCache $cache) {}

    public function __invoke(StatusPage $page): void
    {
        $page->delete();
        $this->cache->forget($page->slug);
    }
}
