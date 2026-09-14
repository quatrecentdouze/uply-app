<?php

declare(strict_types=1);

namespace App\Organizations\Http\Controllers;

use App\Organizations\Actions\MemberChanges;
use App\Organizations\Http\Requests\ChangeRoleRequest;
use App\Organizations\Http\Resources\MemberResource;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Shared\Http\PageRequest;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class MemberController
{
    public function index(PageRequest $request, PaginationSettings $pagination, Organization $organization): AnonymousResourceCollection
    {
        return MemberResource::collection(
            $organization->memberships()
                ->with('user')
                ->oldest()
                ->orderBy('id')
                ->paginate($request->perPage($pagination)),
        );
    }

    public function update(ChangeRoleRequest $request, Organization $organization, Membership $membership, MemberChanges $changes): MemberResource
    {
        return new MemberResource($changes->changeRole($request->currentUser(), $membership, $request->role())->load('user'));
    }

    public function destroy(Organization $organization, Membership $membership, MemberChanges $changes, #[CurrentUser] User $user): Response
    {
        $changes->remove($user, $membership);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
