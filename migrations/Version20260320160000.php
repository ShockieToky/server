<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create arena_admin_team table (bot teams for daily attacks)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE arena_admin_team (
            id               INT AUTO_INCREMENT NOT NULL,
            name             VARCHAR(100)       NOT NULL,
            slot_index       TINYINT(1)         NOT NULL DEFAULT 1,
            hero1_id         INT DEFAULT NULL,
            hero2_id         INT DEFAULT NULL,
            hero3_id         INT DEFAULT NULL,
            hero4_id         INT DEFAULT NULL,
            lead_faction_id  INT DEFAULT NULL,
            lead_origine_id  INT DEFAULT NULL,
            is_active        TINYINT(1)         NOT NULL DEFAULT 1,
            PRIMARY KEY(id),
            INDEX IDX_arena_admin_team_active  (is_active),
            INDEX IDX_arena_admin_team_hero1   (hero1_id),
            INDEX IDX_arena_admin_team_hero2   (hero2_id),
            INDEX IDX_arena_admin_team_hero3   (hero3_id),
            INDEX IDX_arena_admin_team_hero4   (hero4_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS arena_admin_team');
    }
}
