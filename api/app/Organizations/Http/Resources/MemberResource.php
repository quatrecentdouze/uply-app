<?php

declare(strict_types=1);

namespace App\Organizations\Http\Resources;

use App\Organizations\Models\Membership;
use App\Organizations\Models\User;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

final class MemberResource extends JsonResource
{
    public function __construct(private readonly Membership $membership)
    {
        parent::__construct($membership);
    }

    public function toArray(Request $request): array
    {
        $user = $this->membership->user;
        if (! $user instanceof User) {
            throw new LogicException('a member resource needs the user of the membership');
        }

        return [
            'id' => $this->membership->id,
            'role' => $this->membership->role->value,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'joined_at' => IsoTime::format($this->membership->created_at),
        ];
    }
}
