<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250830211902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize notification.created_at (no zero date), add is_read/link if missing.';
    }

    public function up(Schema $schema): void
    {
        // Introspection du schéma courant
        $table = $schema->getTable('notification');

        // --- created_at ---
        if ($table->hasColumn('created_at')) {
            // La colonne existe déjà : on l’assouplit, on corrige les valeurs, puis on remet NOT NULL
            $this->addSql("ALTER TABLE notification MODIFY created_at DATETIME NULL");
            $this->addSql("UPDATE notification SET created_at = NOW() WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
            $this->addSql("ALTER TABLE notification MODIFY created_at DATETIME NOT NULL");
        } else {
            // La colonne n’existe pas : on l’ajoute, on remplit, puis NOT NULL
            $this->addSql("ALTER TABLE notification ADD created_at DATETIME NULL");
            $this->addSql("UPDATE notification SET created_at = NOW() WHERE created_at IS NULL");
            $this->addSql("ALTER TABLE notification MODIFY created_at DATETIME NOT NULL");
        }

        // --- is_read ---
        if (!$table->hasColumn('is_read')) {
            $this->addSql("ALTER TABLE notification ADD is_read TINYINT(1) NULL");
            $this->addSql("UPDATE notification SET is_read = 0 WHERE is_read IS NULL");
            $this->addSql("ALTER TABLE notification MODIFY is_read TINYINT(1) NOT NULL");
        }

        // --- link (optionnel) ---
        if (!$table->hasColumn('link')) {
            $this->addSql("ALTER TABLE notification ADD link VARCHAR(255) DEFAULT NULL");
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('notification');

        // On assouplit created_at (on évite de remettre des zéros)
        if ($table->hasColumn('created_at')) {
            $this->addSql("ALTER TABLE notification MODIFY created_at DATETIME NULL");
        }

        // On retire les colonnes optionnelles si présentes
        if ($table->hasColumn('link')) {
            $this->addSql("ALTER TABLE notification DROP COLUMN link");
        }
        if ($table->hasColumn('is_read')) {
            $this->addSql("ALTER TABLE notification DROP COLUMN is_read");
        }
    }
}
