<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Models\Organization;
use Illuminate\Database\ConnectionInterface;

final readonly class DeleteOrganization
{
    public function __construct(private ConnectionInterface $database) {}

    public function __invoke(Organization $organization): void
    {
        $this->database->transaction(function () use ($organization): void {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->sole();
            $organization->delete();
        });
    }
}
