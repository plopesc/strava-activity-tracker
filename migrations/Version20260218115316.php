<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218115316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, strava_id BIGINT NOT NULL, name VARCHAR(255) NOT NULL, activity_date DATETIME NOT NULL, distance DOUBLE PRECISION NOT NULL, elapsed_time INTEGER NOT NULL, average_speed DOUBLE PRECISION NOT NULL, average_heartrate DOUBLE PRECISION DEFAULT NULL, raw_laps CLOB DEFAULT NULL, raw_streams CLOB DEFAULT NULL, pattern_type VARCHAR(20) DEFAULT NULL, pattern_signature VARCHAR(500) DEFAULT NULL, pattern_segments CLOB DEFAULT NULL, synced_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AC74095AD36AF002 ON activity (strava_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE activity');
    }
}
