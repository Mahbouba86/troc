<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250904165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise les statuts annonce (minuscules), remappe Published→available, Drafted→pending, et définit DEFAULT pending';
    }

    public function up(Schema $schema): void
    {
        // 0) Remap des statuts obsolètes vers des valeurs supportées par l’énum
        //    (évite les erreurs d’hydratation si ton enum ne contient pas "published"/"drafted")
        $this->addSql("UPDATE annonce SET status = 'available' WHERE status = 'Published'");
        $this->addSql("UPDATE annonce SET status = 'pending'   WHERE status = 'Drafted'");

        // 1) Normaliser Title Case -> minuscules pour les valeurs restantes
        $this->addSql("UPDATE annonce SET status = 'pending'   WHERE status = 'Pending'");
        $this->addSql("UPDATE annonce SET status = 'available' WHERE status = 'Available'");
        $this->addSql("UPDATE annonce SET status = 'reserved'  WHERE status = 'Reserved'");
        $this->addSql("UPDATE annonce SET status = 'finished'  WHERE status = 'Finished'");

        // 2) Sécuriser NULL / vides
        $this->addSql("UPDATE annonce SET status = 'pending' WHERE status IS NULL OR status = ''");

        // 3) Définir le DEFAULT et s’assurer du NOT NULL selon la plateforme
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql') {
            // Si la colonne était ENUM, on la passe en VARCHAR + DEFAULT
            $this->addSql("ALTER TABLE annonce MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        } elseif ($platform === 'postgresql') {
            $this->addSql("ALTER TABLE annonce ALTER COLUMN status SET DEFAULT 'pending'");
            $this->addSql("ALTER TABLE annonce ALTER COLUMN status SET NOT NULL");
        } else {
            // fallback générique
            $this->addSql("ALTER TABLE annonce ALTER COLUMN status SET DEFAULT 'pending'");
        }
    }

    public function down(Schema $schema): void
    {
        // On retire le DEFAULT (on ne retransforme pas les données)
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql') {
            $this->addSql("ALTER TABLE annonce MODIFY status VARCHAR(20) NOT NULL");
        } elseif ($platform === 'postgresql') {
            $this->addSql("ALTER TABLE annonce ALTER COLUMN status DROP DEFAULT");
        } else {
            $this->addSql("ALTER TABLE annonce ALTER COLUMN status DROP DEFAULT");
        }
    }
}
