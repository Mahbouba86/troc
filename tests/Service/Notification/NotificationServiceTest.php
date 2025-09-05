<?php
declare(strict_types=1);

namespace App\Tests\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testSendPersistsNotificationWithAllFields(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new NotificationService($em);

        $user    = new User(); // pas de flush réel -> ok même si l'entité a d'autres champs
        $title   = 'Confirmez la réception';
        $message = 'Le propriétaire indique que « Objet » est remis.';
        $link    = 'https://example.test/annonce/42';

        $now = new \DateTimeImmutable();

        // On capture l'entité passée à persist() pour faire des assertions dessus
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Notification $n) use ($user, $title, $message, $link, $now): bool {
                // Titre & message
                $this->assertSame($title, $n->getTitre());
                $this->assertSame($message, $n->getMessage());

                // Utilisateur
                $this->assertSame($user, $n->getUser());

                // createdAt proche de "maintenant" (± 5 secondes)
                $createdAt = $n->getCreatedAt();
                $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
                $this->assertLessThanOrEqual(5, abs($createdAt->getTimestamp() - $now->getTimestamp()));

                // isRead initialisé à false
                $this->assertFalse($n->isRead());

                // link si le champ existe
                if (method_exists($n, 'getLink')) {
                    $this->assertSame($link, $n->getLink());
                }

                return true;
            }));

        $em->expects($this->once())->method('flush');

        $service->send($user, $title, $message, $link);
    }

    public function testNotifyDelegatesToSendWithNullLink(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new NotificationService($em);

        $user    = new User();
        $title   = 'Info';
        $message = 'Texte de la notification.';

        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Notification $n) use ($user, $title, $message): bool {
                $this->assertSame($title, $n->getTitre());
                $this->assertSame($message, $n->getMessage());
                $this->assertSame($user, $n->getUser());
                $this->assertFalse($n->isRead());
                // Si l’entity a un champ link, il doit être null via notify()
                if (method_exists($n, 'getLink')) {
                    $this->assertNull($n->getLink());
                }
                return true;
            }));

        $em->expects($this->once())->method('flush');
        $service->notify($user, $title, $message);
    }
}
