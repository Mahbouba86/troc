<?php

namespace App\Entity;

use App\Repository\PhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\Index(columns: ['annonce_id'], name: 'idx_photo_annonce')] // (facultatif) perfs
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    // valeur par défaut en PHP + en DB (optionnel mais propre)
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isMain = false;

    #[ORM\ManyToOne(targetEntity: Annonce::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] // ✅ important
    private ?Annonce $annonce = null;

    public function getId(): ?int { return $this->id; }

    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function isMain(): bool { return $this->isMain; }
    public function setIsMain(bool $isMain): static { $this->isMain = $isMain; return $this; }

    public function getAnnonce(): ?Annonce { return $this->annonce; }
    public function setAnnonce(?Annonce $annonce): static { $this->annonce = $annonce; return $this; }

    public function __toString(): string
    {
        return $this->filename ?? ('photo#' . $this->id);
    }
}
