<?php

declare(strict_types=1);

use App\Organizations\Actions\IssuedInvitation;
use App\Organizations\Actions\SendInvitationMail;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Notifications\InvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('mails the invitation link to the invited address', function (): void {
    Notification::fake();
    $organization = Organization::factory()->create(['name' => 'Analytical Engines']);
    $inviter = User::factory()->create(['name' => 'Ada Lovelace']);
    $invitation = Invitation::factory()->for($organization)->role(Role::Viewer)->withToken('invitation-token')->create(['email' => 'grace@example.com']);

    app(SendInvitationMail::class)(new IssuedInvitation($invitation, 'invitation-token'), $inviter, $organization);

    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes === ['mail' => 'grace@example.com']
            && $mail->actionUrl === 'http://localhost:5173/invitations/invitation-token'
            && $mail->subject === __('organizations.mail.invitation.subject', ['inviter' => 'Ada Lovelace', 'organization' => 'Analytical Engines']);
    });
});
