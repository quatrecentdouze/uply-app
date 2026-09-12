<?php

declare(strict_types=1);

namespace App\Organizations\Http\Controllers;

use App\Organizations\Http\Resources\MeResource;
use App\Organizations\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final class MeController
{
    public function show(#[CurrentUser] User $user): MeResource
    {
        return new MeResource($user->load(['memberships' => fn ($memberships) => $memberships->with('organization')->oldest()]));
    }
}
