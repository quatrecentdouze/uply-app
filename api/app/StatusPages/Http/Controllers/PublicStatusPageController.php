<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Controllers;

use App\StatusPages\Actions\PublicPageBuilder;
use App\StatusPages\Http\Resources\PublicStatusPageResource;
use App\StatusPages\Storage\PublicPageCache;
use App\StatusPages\Storage\StatusPageQuery;
use App\StatusPages\Values\PublicPage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicStatusPageController
{
    public function __construct(
        private StatusPageQuery $pages,
        private PublicPageBuilder $builder,
        private PublicPageCache $cache,
    ) {}

    public function show(string $slug): PublicStatusPageResource
    {
        $snapshot = $this->cache->remember($slug, function () use ($slug): ?array {
            $page = $this->pages->published($slug);

            return $page === null ? null : $this->builder->build($page)->toSnapshot();
        });

        return $snapshot === null ? throw new NotFoundHttpException : new PublicStatusPageResource(PublicPage::fromSnapshot($snapshot));
    }
}
