<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Annonce;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class MessageRepository extends ServiceEntityRepository
{

    public function findConversationUsersByAnnonce(Annonce $annonce, User $exclude): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('DISTINCT u')
            ->join('m.annonce', 'a')
            ->leftJoin('m.sender', 's')
            ->leftJoin('m.receiver', 'r')
            ->addSelect('s', 'r')
            ->where('a = :a')
            ->setParameter('a', $annonce);

        // on reconstruit "u" comme l’autre côté (sender/receiver) ≈ renvoyer deux sets puis merger
        $senders = $this->createQueryBuilder('m1')
            ->select('DISTINCT s1')
            ->join('m1.annonce', 'a1')
            ->join('m1.sender', 's1')
            ->where('a1 = :a')->setParameter('a', $annonce)
            ->getQuery()->getResult();

        $receivers = $this->createQueryBuilder('m2')
            ->select('DISTINCT r1')
            ->join('m2.annonce', 'a2')
            ->join('m2.receiver', 'r1')
            ->where('a2 = :a')->setParameter('a', $annonce)
            ->getQuery()->getResult();

        $all = array_unique(array_merge($senders, $receivers), SORT_REGULAR);
        // filtre l'utilisateur à exclure (propriétaire)
        return array_values(array_filter($all, fn($u) => $u->getId() !== $exclude->getId()));
    }
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Compte les messages reçus par un utilisateur donné
     */
    public function findByAnnonceAndUsers(Annonce $annonce, User $user1, User $user2): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.annonce = :annonce')
            ->andWhere('(m.sender = :user1 AND m.receiver = :user2) OR (m.sender = :user2 AND m.receiver = :user1)')
            ->setParameter('annonce', $annonce)
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function countReceivedForUser(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.receiver = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
