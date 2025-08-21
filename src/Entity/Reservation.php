<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Annonce;
use Enum\Reservation\ReservationStatus;

// ✅ TON enum de réservation

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Annonce $annonce = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Stocké en base comme VARCHAR via backed enum (string).
     */
    #[ORM\Column(type: 'string', length: 20, enumType: ReservationStatus::class)]
    private ReservationStatus $statut;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->statut = ReservationStatus::PENDING; // ✅ valeur par défaut
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAnnonce(): ?Annonce
    {
        return $this->annonce;
    }

    public function setAnnonce(?Annonce $annonce): self
    {
        $this->annonce = $annonce;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /** ✅ Getter/Setter typés sur TON enum ReservationStatus */
    public function getStatut(): ReservationStatus
    {
        return $this->statut;
    }

    public function setStatut(ReservationStatus $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    /** ✅ Helpers pratiques */
    public function isPending(): bool
    {
        return $this->statut === ReservationStatus::PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->statut === ReservationStatus::ACCEPTED;
    }

    public function isRefused(): bool
    {
        return $this->statut === ReservationStatus::REFUSED;
    }

    public function isCancelled(): bool
    {
        return $this->statut === ReservationStatus::CANCELLED;
    }

    /** Par exemple : actionnable tant que pending */
    public function isActionable(): bool
    {
        return $this->isPending();
    }
}
