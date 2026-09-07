<?php

declare(strict_types=1);

namespace App\Alerting\Senders;

use App\Alerting\Mail\AlertMail;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\EmailRecipients;
use App\Alerting\Values\SendReport;
use Illuminate\Contracts\Mail\Factory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final readonly class MailSender
{
    public function __construct(
        private Factory $mail,
    ) {}

    public function send(EmailRecipients $recipients, AlertMessage $message): SendReport
    {
        try {
            $this->mail->mailer()->to($recipients->addresses)->send(new AlertMail($message));
        } catch (TransportExceptionInterface) {
            return SendReport::transportFailed('mail transport error');
        }

        return SendReport::accepted();
    }
}
