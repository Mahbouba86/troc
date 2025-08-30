<?php

namespace App\Entity;

use App\Repository\AnnonceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Annonce\AnnonceStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: AnnonceRepository::class)]
class Annonce
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 250)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    // Enum natif Doctrine (AnnonceStatus)
    #[ORM\Column(type: Types::STRING, length: 20, enumType: AnnonceStatus::class)]
    private AnnonceStatus $status;

    #[ORM\ManyToOne(inversedBy: 'annonces')]
    #[ORM\JoinColumn(nullable: false)]
    #[Ignore]
    private ?User $user = null;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(
        targetEntity: Reservation::class,
        mappedBy: 'annonce',
        cascade: ['remove'],
        orphanRemoval: true
    )]
    private Collection $reservations;

    #[ORM\ManyToOne(inversedBy: 'annonces')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Vous devez sélectionner une catégorie.')]
    private ?Category $category = null;

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'annonce', orphanRemoval: true)]
    private Collection $messages;

    #[ORM\Column(length: 255)]
    private ?string $ville = null;

    /** @var Collection<int, Photo> */
    #[ORM\OneToMany(
        targetEntity: Photo::class,
        mappedBy: 'annonce',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['isMain' => 'DESC', 'id' => 'ASC'])]
    private Collection $photos;

    public function __construct()
    {
        $this->messages     = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->photos       = new ArrayCollection();

        // Valeurs par défaut
        $this->status    = AnnonceStatus::PENDING;
        $this->createdAt = new \DateTimeImmutable();
    }

    // ---------- Getters & Setters ----------

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getStatus(): AnnonceStatus { return $this->status; }
    public function setStatus(AnnonceStatus $status): static { $this->status = $status; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    // ---------- Messages ----------
    /** @return Collection<int, Message> */
    public function getMessages(): Collection { return $this->messages; }
    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setAnnonce($this);
        }
        return $this;
    }
    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message) && $message->getAnnonce() === $this) {
            $message->setAnnonce(null);
        }
        return $this;
    }

    // ---------- Ville ----------
    public function getVille(): ?string { return $this->ville; }
    public function setVille(string $ville): static { $this->ville = $ville; return $this; }

    // ---------- Photos ----------
    /** @return Collection<int, Photo> */
    public function getPhotos(): Collection { return $this->photos; }
    public function addPhoto(Photo $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setAnnonce($this);
        }
        return $this;
    }
    public function removePhoto(Photo $photo): static
    {
        if ($this->photos->removeElement($photo) && $photo->getAnnonce() === $this) {
            $photo->setAnnonce(null);
        }
        return $this;
    }

    // ---------- Reservations ----------
    /** @return Collection<int, Reservation> */
    public function getReservations(): Collection { return $this->reservations; }
    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setAnnonce($this);
        }
        return $this;
    }
    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation) && $reservation->getAnnonce() === $this) {
            $reservation->setAnnonce(null);
        }
        return $this;
    }

    // ---------- Helpers pour la photo principale ----------
    public function getMainPhoto(): ?Photo
    {
        foreach ($this->photos as $p) {
            if ($p->isMain()) {
                return $p;
            }
        }
        return null;
    }

    public function clearMainPhoto(): void
    {
        foreach ($this->photos as $p) {
            if ($p->isMain()) {
                $p->setIsMain(false);
            }
        }
    }

    public function setMainPhotoById(int $photoId): void
    {
        $this->clearMainPhoto();
        foreach ($this->photos as $p) {
            if ($p->getId() === $photoId) {
                $p->setIsMain(true);
                break;
            }
        }
    }
}
