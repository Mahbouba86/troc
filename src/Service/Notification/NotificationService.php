<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Méthode attendue par le contrôleur.
     */
    public function send(User $user, string $title, string $message, ?string $link = null): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitre($title);
        $notification->setMessage($message);
        $notification->setCreatedAt(new \DateTimeImmutable());
        $notification->setIsRead(false);

        // Si ton entité possède un champ "link" (nullable)
        if ($link !== null && method_exists($notification, 'setLink')) {
            $notification->setLink($link);
        }

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Ta méthode existante (garde-la si tu l’utilises ailleurs).
     */
    public function notify(User $user, string $title, string $message): void
    {
        $this->send($user, $title, $message, null);
    }
}
