<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219051955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gear (id INT AUTO_INCREMENT NOT NULL, strava_gear_id VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_B44539BB2D6E8C0 (strava_gear_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activity ADD sport_type VARCHAR(50) DEFAULT NULL, ADD max_heartrate DOUBLE PRECISION DEFAULT NULL, ADD gear_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A77201934 FOREIGN KEY (gear_id) REFERENCES gear (id)');
        $this->addSql('CREATE INDEX IDX_AC74095A77201934 ON activity (gear_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE gear');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A77201934');
        $this->addSql('DROP INDEX IDX_AC74095A77201934 ON activity');
        $this->addSql('ALTER TABLE activity DROP sport_type, DROP max_heartrate, DROP gear_id');
    }
}
