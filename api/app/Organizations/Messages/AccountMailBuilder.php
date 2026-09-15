<?php

declare(strict_types=1);

namespace App\Organizations\Messages;

use App\Organizations\Contracts\Role;
use App\Organizations\Settings\OrganizationsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Notifications\Messages\MailMessage;
use SensitiveParameter;

final readonly class AccountMailBuilder
{
    private const string RESET_PATH = '/reset-password';

    private const string INVITATION_PATH = '/invitations';

    private const string UTC_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private Translator $translator,
        private OrganizationsSettings $settings,
    ) {}

    public function verification(?string $name, string $url, int $minutes): MailMessage
    {
        return $this->mail('verify', $this->greeting($name), $url, ['minutes' => $minutes]);
    }

    public function passwordReset(?string $name, string $email, #[SensitiveParameter] string $token, int $minutes): MailMessage
    {
        $url = $this->settings->frontendLink(self::RESET_PATH.'?'.http_build_query(['token' => $token, 'email' => $email]));

        return $this->mail('reset', $this->greeting($name), $url, ['minutes' => $minutes]);
    }

    public function invitation(string $inviterName, string $organizationName, Role $role, CarbonImmutable $expiresAt, #[SensitiveParameter] string $token): MailMessage
    {
        return $this->mail('invitation', $this->greeting(null), $this->settings->frontendLink(self::INVITATION_PATH.'/'.$token), [
            'inviter' => $inviterName,
            'organization' => $organizationName,
            'role' => $this->text('organizations.roles.'.$role->value),
            'expires' => $expiresAt->utc()->format(self::UTC_FORMAT),
        ]);
    }

    private function mail(string $kind, string $greeting, string $url, array $parameters): MailMessage
    {
        return (new MailMessage)
            ->subject($this->text("organizations.mail.{$kind}.subject", $parameters))
            ->greeting($greeting)
            ->line($this->text("organizations.mail.{$kind}.intro", $parameters))
            ->action($this->text("organizations.mail.{$kind}.action", $parameters), $url)
            ->line($this->text("organizations.mail.{$kind}.outro", $parameters))
            ->salutation($this->text('organizations.mail.salutation'));
    }

    private function greeting(?string $name): string
    {
        return $name === null
            ? $this->text('organizations.mail.greeting_anonymous')
            : $this->text('organizations.mail.greeting', ['name' => $name]);
    }

    private function text(string $key, array $parameters = []): string
    {
        $text = $this->translator->get($key, $parameters);

        return is_string($text) ? $text : $key;
    }
}
