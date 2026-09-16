<?php

declare(strict_types=1);

namespace App\Organizations\Http\Controllers;

use App\Organizations\Actions\AcceptInvitation;
use App\Organizations\Actions\InvitationLookup;
use App\Organizations\Http\Resources\OrganizationResource;
use App\Organizations\Http\Resources\PublicInvitationResource;
use App\Organizations\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final class InvitationTokenController
{
    public const string TOKEN_PATTERN = '[A-Za-z0-9]{1,128}';

    public function show(string $token, InvitationLookup $invitations): PublicInvitationResource
    {
        return new PublicInvitationResource($invitations->pending($token)->load(['organization', 'inviter']));
    }

    public function accept(string $token, AcceptInvitation $acceptInvitation, #[CurrentUser] User $user): OrganizationResource
    {
        return new OrganizationResource($acceptInvitation($user, $token)->load('organization'));
    }
}
