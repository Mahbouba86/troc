<?php

namespace App\Service\MailerService;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerService
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function sendEmail($from, $to, $subject, ?string $body): void
    {
        $email = new Email()
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }
}
