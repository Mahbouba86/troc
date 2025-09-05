<?php

namespace App\Repository;

use App\Entity\Annonce;
use App\Entity\User;
use App\Enum\Annonce\AnnonceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

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
     * Compte les annonces (tous statuts) pour un utilisateur.
     */
    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les annonces d'un utilisateur par statut précis.
     */
    public function countByStatus(User $user, string $status): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * QB côté public: exclut les annonces en attente (PENDING).
     */
    public function createVisibleQb(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status <> :pending')
            ->setParameter('pending', AnnonceStatus::PENDING->value)
            ->orderBy('a.createdAt', 'DESC');
    }

    /**
     * Récupère les annonces visibles (≠ PENDING).
     */
    public function findVisible(int $limit = 20, int $offset = 0): array
    {
        return $this->createVisibleQb()
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche instantanée par terme (titre) + filtres optionnels, côté public (≠ PENDING).
     *
     * @param string|null $term       Texte saisi (ex: "vélo")
     * @param int|null    $categoryId ID catégorie (optionnel)
     * @param string|null $ville      Ville (optionnel)
     * @param int|null    $limit      Limite (optionnel)
     * @return Annonce[]
     */
    public function searchByTerm(?string $term, ?int $categoryId = null, ?string $ville = null, ?int $limit = null): array
    {
        $qb = $this->createVisibleQb()
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->leftJoin('a.photos', 'p')->addSelect('p');

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
