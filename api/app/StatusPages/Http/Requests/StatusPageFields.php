<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Requests;

use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorView;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Values\PageDraft;
use App\StatusPages\Values\PageMonitorEntry;
use Illuminate\Foundation\Http\FormRequest;

final readonly class StatusPageFields
{
    public const int TITLE_MIN_LENGTH = 1;

    public function __construct(
        private StatusPagesSettings $settings,
        private MonitorDirectory $monitors,
    ) {}

    public function rules(FormRequest $request, string $organizationId, ?string $pageId, bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $lengths = $this->settings->lengths;

        return [
            'title' => [$presence, 'string', 'min:'.self::TITLE_MIN_LENGTH, 'max:'.$lengths->titleMax],
            'slug' => [$presence, 'string', 'min:'.$lengths->slugMin, 'max:'.$lengths->slugMax, new ValidSlug($pageId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.$lengths->descriptionMax],
            'is_published' => ['sometimes', 'boolean'],
            'monitors' => ['sometimes', 'array', 'list', 'max:'.$this->settings->limits->maxMonitorsPerPage],
            'monitors.*' => ['array:monitor_id,display_name'],
            'monitors.*.monitor_id' => ['required', 'string', 'ulid', 'distinct', new MonitorOfOrganization($this->monitorsIn($request, $organizationId))],
            'monitors.*.display_name' => ['nullable', 'string', 'max:'.$lengths->displayNameMax],
        ];
    }

    public function draft(FormRequest $request): PageDraft
    {
        return new PageDraft(
            title: $request->has('title') ? $request->string('title')->toString() : null,
            slug: $request->has('slug') ? $request->string('slug')->toString() : null,
            changesDescription: $request->has('description'),
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            isPublished: $request->has('is_published') ? $request->boolean('is_published') : null,
            monitors: $request->has('monitors') ? self::entries($request->array('monitors')) : null,
        );
    }

    private function monitorsIn(FormRequest $request, string $organizationId): array
    {
        $allowed = [];
        foreach ($this->monitors->findMany(self::submittedIds($request->input('monitors'))) as $view) {
            if ($view instanceof MonitorView && $view->organizationId === $organizationId) {
                $allowed[] = $view->id;
            }
        }

        return $allowed;
    }

    private static function submittedIds(mixed $monitors): array
    {
        $ids = [];
        foreach (is_array($monitors) ? $monitors : [] as $monitor) {
            if (is_array($monitor) && is_string($monitor['monitor_id'] ?? null)) {
                $ids[] = strtolower($monitor['monitor_id']);
            }
        }

        return $ids;
    }

    private static function entries(array $monitors): array
    {
        $entries = [];
        foreach ($monitors as $monitor) {
            if (is_array($monitor) && is_string($monitor['monitor_id'] ?? null)) {
                $displayName = $monitor['display_name'] ?? null;
                $entries[] = new PageMonitorEntry(strtolower($monitor['monitor_id']), is_string($displayName) ? $displayName : null);
            }
        }

        return $entries;
    }
}
