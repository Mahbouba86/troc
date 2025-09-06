<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250830160534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE annonce CHANGE status status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784188805AB2F');
        $this->addSql('ALTER TABLE photo CHANGE is_main is_main TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784188805AB2F FOREIGN KEY (annonce_id) REFERENCES annonce (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_14b784188805ab2f TO idx_photo_annonce');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849558805AB2F');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849558805AB2F FOREIGN KEY (annonce_id) REFERENCES annonce (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation RENAME INDEX idx_42c849558805ab2f TO idx_reservation_annonce');
        $this->addSql('ALTER TABLE reservation RENAME INDEX idx_42c84955a76ed395 TO idx_reservation_user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849558805AB2F');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849558805AB2F FOREIGN KEY (annonce_id) REFERENCES annonce (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation RENAME INDEX idx_reservation_annonce TO IDX_42C849558805AB2F');
        $this->addSql('ALTER TABLE reservation RENAME INDEX idx_reservation_user TO IDX_42C84955A76ED395');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784188805AB2F');
        $this->addSql('ALTER TABLE photo CHANGE is_main is_main TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784188805AB2F FOREIGN KEY (annonce_id) REFERENCES annonce (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE photo RENAME INDEX idx_photo_annonce TO IDX_14B784188805AB2F');
        $this->addSql('ALTER TABLE annonce CHANGE status status VARCHAR(255) NOT NULL');
    }
}
