<?php

declare(strict_types=1);

namespace App\StatusPages\Actions;

use App\Shared\Database\AdvisoryLock;
use App\Shared\Errors\DomainError;
use App\StatusPages\Enums\StatusPageError;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Storage\PageMonitorWriter;
use App\StatusPages\Values\PageDraft;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class CreateStatusPage
{
    private const string LOCK_SCOPE = 'status-pages';

    public function __construct(
        private ConnectionInterface $database,
        private AdvisoryLock $lock,
        private StatusPagesSettings $settings,
        private PageMonitorWriter $monitors,
    ) {}

    public function __invoke(string $organizationId, PageDraft $draft): StatusPage
    {
        try {
            return $this->database->transaction(function () use ($organizationId, $draft): StatusPage {
                $this->lock->holdUntilCommit(self::LOCK_SCOPE, $organizationId);
                $max = $this->settings->limits->maxPagesPerOrganization;
                if (StatusPage::query()->where('organization_id', $organizationId)->count() >= $max) {
                    throw new DomainError(StatusPageError::StatusPageLimitReached, ['max' => $max]);
                }
                $page = new StatusPage;
                $page->organization_id = $organizationId;
                $page->title = $draft->title ?? '';
                $page->slug = $draft->slug ?? '';
                $page->description = $draft->description;
                $page->is_published = $draft->isPublished ?? false;
                $page->save();
                $this->monitors->replace($page, $draft->monitors ?? []);

                return $page->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainError(StatusPageError::SlugTaken);
        }
    }
}
