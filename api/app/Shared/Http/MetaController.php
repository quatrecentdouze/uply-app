<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Meta\MetaSection;
use App\Shared\Settings\ApiSettings;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\JsonResponse;

final readonly class MetaController
{
    private const string CACHE_KEY = 'api.meta';

    public function __construct(
        #[Tag(MetaSection::TAG)] private iterable $sections,
        private Repository $cache,
        private ApiSettings $settings,
    ) {}

    public function __invoke(): JsonResponse
    {
        $seconds = $this->settings->metaCacheSeconds;
        $meta = $this->cache->remember(self::CACHE_KEY, $seconds, fn (): array => $this->describe());

        return (new JsonResponse(['data' => $meta]))->setPublic()->setMaxAge($seconds);
    }

    private function describe(): array
    {
        $meta = [];
        foreach ($this->sections as $section) {
            if ($section instanceof MetaSection) {
                $meta[$section->key()] = $section->describe();
            }
        }
        ksort($meta);

        return $meta;
    }
}
