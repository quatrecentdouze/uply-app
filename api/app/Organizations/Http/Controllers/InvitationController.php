<?php

declare(strict_types=1);

namespace App\Organizations\Http\Controllers;

use App\Organizations\Actions\InviteMember;
use App\Organizations\Actions\SendInvitationMail;
use App\Organizations\Http\Requests\InviteRequest;
use App\Organizations\Http\Resources\InvitationResource;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Organization;
use App\Shared\Http\PageRequest;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class InvitationController
{
    public function index(PageRequest $request, PaginationSettings $pagination, Organization $organization): AnonymousResourceCollection
    {
        return InvitationResource::collection(
            $organization->invitations()
                ->whereNull('accepted_at')
                ->with('inviter')
                ->oldest()
                ->orderBy('id')
                ->paginate($request->perPage($pagination)),
        );
    }

    public function store(InviteRequest $request, Organization $organization, InviteMember $inviteMember, SendInvitationMail $sendInvitationMail): JsonResponse
    {
        $inviter = $request->currentUser();
        $issued = $inviteMember($inviter, $organization, $request->email(), $request->role());
        $sendInvitationMail($issued, $inviter, $organization);

        return (new InvitationResource($issued->invitation->load('inviter')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Organization $organization, Invitation $invitation): Response
    {
        $invitation->delete();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
