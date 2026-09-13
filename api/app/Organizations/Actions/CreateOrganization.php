<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Contracts\Role;
use App\Organizations\Enums\OrganizationError;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Rules\SlugRules;
use App\Organizations\Settings\OrganizationLimits;
use App\Organizations\Settings\OrganizationsSettings;
use App\Shared\Errors\DomainError;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final readonly class CreateOrganization
{
    private const int SLUG_ATTEMPTS = 3;

    private OrganizationLimits $limits;

    public function __construct(
        private ConnectionInterface $database,
        OrganizationsSettings $settings,
    ) {
        $this->limits = $settings->organizations;
    }

    public function __invoke(User $owner, string $name): Organization
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->database->transaction(fn (): Organization => $this->create($owner, $name));
            } catch (UniqueConstraintViolationException $collision) {
                if ($attempt >= self::SLUG_ATTEMPTS) {
                    throw $collision;
                }
            }
        }
    }

    private function create(User $owner, string $name): Organization
    {
        User::query()->whereKey($owner->id)->lockForUpdate()->sole();
        if ($this->ownedCount($owner) >= $this->limits->maxOwnedPerUser) {
            throw new DomainError(OrganizationError::OrganizationLimitReached, ['max' => $this->limits->maxOwnedPerUser]);
        }

        $organization = Organization::query()->create(['name' => $name, 'slug' => $this->freeSlug($name)]);
        Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => Role::Owner,
        ]);

        return $organization;
    }

    private function ownedCount(User $owner): int
    {
        return Membership::query()->where('user_id', $owner->id)->where('role', Role::Owner)->count();
    }

    private function freeSlug(string $name): string
    {
        $base = SlugRules::fit(Str::slug($name), $this->limits->slugMinLength, $this->limits->slugMaxLength);
        $taken = Organization::query()->where('slug', 'like', $base.'%')->pluck('slug')->all();

        return SlugRules::firstFree($base, $taken, $this->limits->slugMaxLength);
    }
}
