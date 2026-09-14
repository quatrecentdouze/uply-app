<?php

declare(strict_types=1);

namespace App\Organizations\Http\Resources;

use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

final class OrganizationResource extends JsonResource
{
    public function __construct(private readonly Membership $membership)
    {
        parent::__construct($membership);
    }

    public function toArray(Request $request): array
    {
        $organization = $this->membership->organization;
        if (! $organization instanceof Organization) {
            throw new LogicException('an organization resource needs the organization of the membership');
        }

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'role' => $this->membership->role->value,
            'created_at' => IsoTime::format($organization->created_at),
        ];
    }
}
