<?php

declare(strict_types=1);

namespace App\StatusPages\Actions;

use App\Shared\Errors\DomainError;
use App\StatusPages\Enums\StatusPageError;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Storage\PageMonitorWriter;
use App\StatusPages\Values\PageDraft;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class UpdateStatusPage
{
    public function __construct(
        private ConnectionInterface $database,
        private PageMonitorWriter $monitors,
    ) {}

    public function __invoke(StatusPage $page, PageDraft $draft): StatusPage
    {
        try {
            return $this->database->transaction(function () use ($page, $draft): StatusPage {
                $locked = StatusPage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
                $locked->title = $draft->title ?? $locked->title;
                $locked->slug = $draft->slug ?? $locked->slug;
                $locked->description = $draft->changesDescription ? $draft->description : $locked->description;
                $locked->is_published = $draft->isPublished ?? $locked->is_published;
                $locked->save();
                if ($draft->monitors !== null) {
                    $this->monitors->replace($locked, $draft->monitors);
                }

                return $locked->load('monitors');
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainError(StatusPageError::SlugTaken);
        }
    }
}
