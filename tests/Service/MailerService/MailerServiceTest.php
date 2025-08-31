<?php
declare(strict_types=1);

namespace App\Tests\Service\MailerService;

use App\Service\MailerService\MailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class MailerServiceTest extends TestCase
{
    public function testSendEmailBuildsAndSendsEmail(): void
    {
        $from    = 'expediteur@example.com';
        $to      = 'destinataire@example.com';
        $subject = 'Sujet de test';
        $body    = '<p>Hello World</p>';

        $mailer = $this->createMock(MailerInterface::class);

        // On vérifie que MailerInterface::send() est appelé 1 fois avec un Email correctement rempli
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($from, $to, $subject, $body): bool {
                // From
                $froms = $email->getFrom();
                $this->assertCount(1, $froms, 'Un seul expéditeur attendu');
                $this->assertSame($from, $froms[0]->getAddress());

                // To
                $tos = $email->getTo();
                $this->assertCount(1, $tos, 'Un seul destinataire attendu');
                $this->assertSame($to, $tos[0]->getAddress());

                // Subject
                $this->assertSame($subject, $email->getSubject());

                // HTML body (Email::getHtmlBody() existe sur Symfony Mime)
                $this->assertSame($body, $email->getHtmlBody());

                return true;
            }));

        $service = new MailerService($mailer);
        $service->sendEmail($from, $to, $subject, $body);
    }
}
