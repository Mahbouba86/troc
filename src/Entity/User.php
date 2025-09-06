<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Entity\Annonce;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Reservation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 15, unique: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $address = null;

    #[ORM\Column(length: 20)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Annonce::class, orphanRemoval: true)]
    private Collection $annonces;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Reservation::class)]
    private Collection $reservations;

    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: Message::class, orphanRemoval: true)]
    private Collection $messagesSent;

    #[ORM\OneToMany(mappedBy: 'receiver', targetEntity: Message::class, orphanRemoval: true)]
    private Collection $messagesReceived;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 5, nullable: false)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 255)]
    private ?string $ville = null;

    public function __construct()
    {
        $this->annonces = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->messagesSent = new ArrayCollection();
        $this->messagesReceived = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    // -------- Core --------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
    public function setPhoneNumber(string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    // -------- Relations: Annonces --------

    /** @return Collection<int, Annonce> */
    public function getAnnonces(): Collection
    {
        return $this->annonces;
    }

    public function addAnnonce(Annonce $annonce): self
    {
        if (!$this->annonces->contains($annonce)) {
            $this->annonces->add($annonce);
            $annonce->setUser($this);
        }
        return $this;
    }

    public function removeAnnonce(Annonce $annonce): self
    {
        if ($this->annonces->removeElement($annonce)) {
            if ($annonce->getUser() === $this) {
                $annonce->setUser(null);
            }
        }
        return $this;
    }

    // -------- Relations: Reservations (demandes faites par l'utilisateur) --------

    /** @return Collection<int, Reservation> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): self
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setUser($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getUser() === $this) {
                $reservation->setUser(null);
            }
        }
        return $this;
    }

    // -------- Relations: Messages --------

    /** @return Collection<int, Message> */
    public function getMessagesSent(): Collection
    {
        return $this->messagesSent;
    }

    public function addMessagesSent(Message $message): self
    {
        if (!$this->messagesSent->contains($message)) {
            $this->messagesSent->add($message);
            $message->setSender($this);
        }
        return $this;
    }

    public function removeMessagesSent(Message $message): self
    {
        if ($this->messagesSent->removeElement($message)) {
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Message> */
    public function getMessagesReceived(): Collection
    {
        return $this->messagesReceived;
    }

    public function addMessagesReceived(Message $message): self
    {
        if (!$this->messagesReceived->contains($message)) {
            $this->messagesReceived->add($message);
            $message->setReceiver($this);
        }
        return $this;
    }

    public function removeMessagesReceived(Message $message): self
    {
        if ($this->messagesReceived->removeElement($message)) {
            if ($message->getReceiver() === $this) {
                $message->setReceiver(null);
            }
        }
        return $this;
    }

    // -------- Relations: Notifications --------

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }
        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }
        return $this;
    }

    // -------- Divers --------

    public function isVerified(): bool
    {
        return $this->isVerified;
    }
    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }
    public function setCodePostal(string $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }
    public function setVille(string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }
}
