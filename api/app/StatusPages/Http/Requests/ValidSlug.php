<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Requests;

use App\StatusPages\Models\StatusPage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidSlug implements ValidationRule
{
    public const string PATTERN_BODY = '^[a-z0-9-]+$';

    private const string PATTERN = '/'.self::PATTERN_BODY.'/';

    public function __construct(private ?string $ignoredPageId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('status_pages.validation.slug')->translate();

            return;
        }
        $taken = StatusPage::query()
            ->where('slug', $value)
            ->when($this->ignoredPageId !== null, fn ($pages) => $pages->whereKeyNot($this->ignoredPageId))
            ->exists();
        if ($taken) {
            $fail('status_pages.validation.slug_taken')->translate();
        }
    }
}
