<?php

declare(strict_types=1);

namespace App\Organizations\Messages;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Notifications\Messages\MailMessage;

final readonly class AccountMailBuilder
{
    public function __construct(private Translator $translator) {}

    public function verification(?string $name, string $url, int $minutes): MailMessage
    {
        return $this->mail('verify', $this->greeting($name), $url, ['minutes' => $minutes]);
    }

    public function passwordReset(?string $name, string $url, int $minutes): MailMessage
    {
        return $this->mail('reset', $this->greeting($name), $url, ['minutes' => $minutes]);
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
