<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create arena tables: arena_season, arena_defense, arena_season_player, arena_battle';
    }

    public function up(Schema $schema): void
    {
        // ── Saisons ──────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE arena_season (
            id         INT AUTO_INCREMENT NOT NULL,
            name       VARCHAR(100)       NOT NULL,
            started_at DATETIME           NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ends_at    DATETIME           DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_active  TINYINT(1)         NOT NULL DEFAULT 1,
            PRIMARY KEY(id),
            INDEX IDX_arena_season_active (is_active)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');

        // ── Défenses enregistrées par joueur ──────────────────────────────────
        $this->addSql('CREATE TABLE arena_defense (
            id               INT AUTO_INCREMENT NOT NULL,
            user_id          INT NOT NULL,
            slot_index       TINYINT(1) NOT NULL DEFAULT 1,
            hero1_id         INT DEFAULT NULL,
            hero2_id         INT DEFAULT NULL,
            hero3_id         INT DEFAULT NULL,
            hero4_id         INT DEFAULT NULL,
            lead_faction_id  INT DEFAULT NULL,
            lead_origine_id  INT DEFAULT NULL,
            updated_at       DATETIME NOT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX uq_arena_defense_user_slot (user_id, slot_index),
            INDEX IDX_arena_defense_user (user_id),
            INDEX IDX_arena_defense_hero1 (hero1_id),
            INDEX IDX_arena_defense_hero2 (hero2_id),
            INDEX IDX_arena_defense_hero3 (hero3_id),
            INDEX IDX_arena_defense_hero4 (hero4_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');

        // ── Stats par joueur et par saison ───────────────────────────────────
        $this->addSql('CREATE TABLE arena_season_player (
            id                  INT AUTO_INCREMENT NOT NULL,
            user_id             INT NOT NULL,
            season_id           INT NOT NULL,
            wins                INT NOT NULL DEFAULT 0,
            losses              INT NOT NULL DEFAULT 0,
            attacks_used_today  INT NOT NULL DEFAULT 0,
            last_attack_date    DATE DEFAULT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX uq_arena_season_player (user_id, season_id),
            INDEX IDX_asp_user   (user_id),
            INDEX IDX_asp_season (season_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');

        // ── Historique des combats ────────────────────────────────────────────
        $this->addSql('CREATE TABLE arena_battle (
            id                INT AUTO_INCREMENT NOT NULL,
            season_id         INT NOT NULL,
            attacker_id       INT NOT NULL,
            defender_id       INT NOT NULL,
            arena_defense_id  INT DEFAULT NULL,
            defense_snapshot  JSON NOT NULL,
            victory           TINYINT(1) NOT NULL DEFAULT 0,
            fought_at         DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX IDX_ab_season   (season_id),
            INDEX IDX_ab_attacker (attacker_id),
            INDEX IDX_ab_defender (defender_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE arena_battle');
        $this->addSql('DROP TABLE arena_season_player');
        $this->addSql('DROP TABLE arena_defense');
        $this->addSql('DROP TABLE arena_season');
    }
}
