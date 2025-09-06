<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\Reservation\ReservationStatus;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Réservations PENDING pour les annonces appartenant à $owner.
     *
     * @return Reservation[]
     */
    public function findPendingForOwner(User $owner): array
    {
        $pending = ReservationStatus::PENDING->value;

        return $this->createQueryBuilder('r')
            ->innerJoin('r.annonce', 'a')->addSelect('a')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->andWhere('a.user = :owner')
            ->andWhere('r.statut = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', $pending)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Charge une réservation par id si l'annonce liée appartient à $owner.
     */
    public function findOneForOwner(int $id, User $owner): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.annonce', 'a')->addSelect('a')
            ->andWhere('r.id = :id')
            ->andWhere('a.user = :owner')
            ->setParameter('id', $id)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte les réservations PENDING pour les annonces de $owner.
     */
    public function countPendingForOwner(User $owner): int
    {
        $pending = ReservationStatus::PENDING->value;

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->innerJoin('r.annonce', 'a')
            ->andWhere('a.user = :owner')
            ->andWhere('r.statut = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', $pending)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
