<?php

declare(strict_types=1);

namespace App\Alerting\Mail;

use App\Alerting\Rules\MessageFormatRules;
use App\Alerting\Values\AlertField;
use App\Alerting\Values\AlertMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class AlertMail extends Mailable
{
    private const string VIEW = 'mail.alerting.alert';

    public function __construct(
        public readonly AlertMessage $alert,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alert->title);
    }

    public function content(): Content
    {
        return new Content(markdown: self::VIEW, with: [
            'title' => MessageFormatRules::markdownEscape($this->alert->title),
            'summary' => MessageFormatRules::markdownEscape($this->alert->summary),
            'fields' => $this->alert->mapFields(fn (AlertField $field): array => [
                'label' => MessageFormatRules::markdownEscape($field->label),
                'value' => MessageFormatRules::markdownEscape($field->text),
            ]),
            'link' => $this->alert->link,
            'linkLabel' => MessageFormatRules::markdownEscape($this->alert->linkLabel),
        ]);
    }
}
