<?php

declare(strict_types=1);

namespace App\Organizations\Http\Resources;

use App\Organizations\Models\Invitation;
use App\Organizations\Models\User;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InvitationResource extends JsonResource
{
    public function __construct(private readonly Invitation $invitation)
    {
        parent::__construct($invitation);
    }

    public function toArray(Request $request): array
    {
        $inviter = $this->invitation->inviter;

        return [
            'id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'role' => $this->invitation->role->value,
            'invited_by' => $inviter instanceof User ? ['id' => $inviter->id, 'name' => $inviter->name] : null,
            'expires_at' => IsoTime::format($this->invitation->expiresAt()),
            'created_at' => IsoTime::format($this->invitation->created_at),
        ];
    }
}
