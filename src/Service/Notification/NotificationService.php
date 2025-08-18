<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Crée et persiste une notification avec titre + message.
     */
    public function notify(User $user, string $title, string $message): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitre($title);
        $notification->setMessage($message);
        $notification->setCreatedAt(new \DateTimeImmutable());
        $notification->setIsRead(false);

        $this->em->persist($notification);
        $this->em->flush();
    }
}
