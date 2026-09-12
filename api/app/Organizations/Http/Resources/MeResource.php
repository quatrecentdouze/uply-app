<?php

declare(strict_types=1);

namespace App\Organizations\Http\Resources;

use App\Organizations\Models\Membership;
use App\Organizations\Models\User;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MeResource extends JsonResource
{
    public function __construct(private readonly User $user)
    {
        parent::__construct($user);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'email_verified_at' => IsoTime::format($this->user->email_verified_at),
            'two_factor_enabled' => $this->user->hasEnabledTwoFactorAuthentication(),
            'created_at' => IsoTime::format($this->user->created_at),
            'memberships' => $this->memberships(),
        ];
    }

    private function memberships(): array
    {
        $resources = [];
        foreach ($this->user->memberships as $membership) {
            if ($membership instanceof Membership) {
                $resources[] = new MembershipResource($membership);
            }
        }

        return $resources;
    }
}
