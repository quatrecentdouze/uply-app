<?php

declare(strict_types=1);

namespace App\StatusPages\Storage;

use App\StatusPages\Settings\StatusPagesSettings;
use Closure;
use Illuminate\Contracts\Cache\Repository;

final readonly class PublicPageCache
{
    private const string KEY_PREFIX = 'status-pages.public:';

    public function __construct(
        private Repository $cache,
        private StatusPagesSettings $settings,
    ) {}

    public function remember(string $slug, Closure $build): ?array
    {
        $snapshot = $this->cache->get(self::KEY_PREFIX.$slug);
        if (is_array($snapshot)) {
            return $snapshot;
        }
        $built = $build();
        if (! is_array($built)) {
            return null;
        }
        $this->cache->put(self::KEY_PREFIX.$slug, $built, $this->settings->public->cacheSeconds);

        return $built;
    }

    public function forget(string ...$slugs): void
    {
        foreach (array_unique($slugs) as $slug) {
            $this->cache->forget(self::KEY_PREFIX.$slug);
        }
    }
}
