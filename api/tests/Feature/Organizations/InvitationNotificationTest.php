<?php

declare(strict_types=1);

use App\Organizations\Notifications\InvitationNotification;
use App\Shared\QueueName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;

it('sends the prepared invitation mail on the default queue', function (): void {
    $mail = (new MailMessage)->subject('Invited')->action('Open', 'http://localhost:5173/invitations/token');
    $notification = new InvitationNotification($mail);
    $notifiable = (new AnonymousNotifiable)->route('mail', 'ada@example.com');

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->queue)->toBe(QueueName::Default->value)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($notifiable))->toBe(['mail'])
        ->and($notification->toMail($notifiable))->toBe($mail);
});

it('survives the trip through the queue', function (): void {
    $mail = (new MailMessage)->subject('Invited')->action('Open', 'http://localhost:5173/invitations/token');

    $restored = unserialize(serialize(new InvitationNotification($mail)));

    expect($restored)->toBeInstanceOf(InvitationNotification::class)
        ->and($restored->toMail(new AnonymousNotifiable)->actionUrl)->toBe('http://localhost:5173/invitations/token');
});
