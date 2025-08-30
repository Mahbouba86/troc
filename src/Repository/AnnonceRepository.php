<?php

namespace App\Repository;

use App\Entity\Annonce;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Annonce>
 *
 * @method Annonce|null find($id, $lockMode = null, $lockVersion = null)
 * @method Annonce|null findOneBy(array $criteria, array $orderBy = null)
 * @method Annonce[]    findAll()
 * @method Annonce[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnnonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annonce::class);
    }

    /**
     * Compte les annonces publiées par un utilisateur donné
     */
    public function countForUser(User $user): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(User $user, string $status): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche instantanée par terme (titre) + filtres optionnels.
     * Charge aussi l'auteur et les photos pour éviter le N+1 sur la liste.
     *
     * @param string|null $term       Texte saisi (ex: "vélo")
     * @param int|null    $categoryId ID catégorie (optionnel)
     * @param string|null $ville      Ville (optionnel)
     * @param int|null    $limit      Limite (optionnel)
     * @return Annonce[]
     */
    public function searchByTerm(?string $term, ?int $categoryId = null, ?string $ville = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->leftJoin('a.photos', 'p')->addSelect('p')
            ->orderBy('a.createdAt', 'DESC');

        if ($term !== null && $term !== '') {
            $qb->andWhere('LOWER(a.titre) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($term).'%');
        }

        if ($categoryId) {
            $qb->andWhere('a.category = :cat')->setParameter('cat', $categoryId);
        }

        if ($ville) {
            $qb->andWhere('a.ville LIKE :ville')->setParameter('ville', '%'.$ville.'%');
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
