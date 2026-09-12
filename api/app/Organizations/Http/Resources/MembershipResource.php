<?php

declare(strict_types=1);

namespace App\Organizations\Http\Resources;

use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MembershipResource extends JsonResource
{
    public function __construct(private readonly Membership $membership)
    {
        parent::__construct($membership);
    }

    public function toArray(Request $request): array
    {
        $organization = $this->membership->organization;

        return [
            'id' => $this->membership->id,
            'role' => $this->membership->role->value,
            'organization' => $organization instanceof Organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ] : null,
            'joined_at' => IsoTime::format($this->membership->created_at),
        ];
    }
}
