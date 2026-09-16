<?php

declare(strict_types=1);

namespace App\Organizations\Http\Resources;

use App\Organizations\Models\Invitation;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

final class PublicInvitationResource extends JsonResource
{
    public function __construct(private readonly Invitation $invitation)
    {
        parent::__construct($invitation);
    }

    public function toArray(Request $request): array
    {
        $organization = $this->invitation->organization;
        if (! $organization instanceof Organization) {
            throw new LogicException('a public invitation needs its organization');
        }
        $inviter = $this->invitation->inviter;

        return [
            'organization' => ['name' => $organization->name],
            'role' => $this->invitation->role->value,
            'invited_by' => $inviter instanceof User ? ['name' => $inviter->name] : null,
            'email' => $this->invitation->email,
            'expires_at' => IsoTime::format($this->invitation->expiresAt()),
        ];
    }
}
