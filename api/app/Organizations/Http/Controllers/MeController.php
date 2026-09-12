<?php

declare(strict_types=1);

namespace App\Organizations\Http\Controllers;

use App\Organizations\Actions\UpdateAccount;
use App\Organizations\Http\Requests\UpdateMeRequest;
use App\Organizations\Http\Resources\MeResource;
use App\Organizations\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final class MeController
{
    public function show(#[CurrentUser] User $user): MeResource
    {
        return $this->resource($user);
    }

    public function update(UpdateMeRequest $request, UpdateAccount $updateAccount): MeResource
    {
        return $this->resource($updateAccount($request->currentUser(), $request->newName(), $request->newEmail()));
    }

    private function resource(User $user): MeResource
    {
        return new MeResource($user->load(['memberships' => fn ($memberships) => $memberships->with('organization')->oldest()]));
    }
}
