<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Messages\AccountMailBuilder;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Notifications\InvitationNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\AnonymousNotifiable;

final readonly class SendInvitationMail
{
    public function __construct(
        private Dispatcher $notifications,
        private AccountMailBuilder $mails,
    ) {}

    public function __invoke(IssuedInvitation $issued, User $inviter, Organization $organization): void
    {
        $invitation = $issued->invitation;
        $mail = $this->mails->invitation($inviter->name, $organization->name, $invitation->role, $invitation->expiresAt(), $issued->token);

        $this->notifications->send((new AnonymousNotifiable)->route('mail', $invitation->email), new InvitationNotification($mail));
    }
}
