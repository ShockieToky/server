<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PVE: monster, attack (add monster_id), story_stage, story_wave, story_wave_monster, story_reward, user_story_progress';
    }

    public function up(Schema $schema): void
    {
        // ── Monster ───────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE monster (
            id          INT AUTO_INCREMENT NOT NULL,
            name        VARCHAR(100) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            level       SMALLINT NOT NULL DEFAULT 1,
            type        VARCHAR(10) NOT NULL DEFAULT \'attack\',
            attack      INT NOT NULL DEFAULT 0,
            defense     INT NOT NULL DEFAULT 0,
            hp          INT NOT NULL DEFAULT 0,
            speed       INT NOT NULL DEFAULT 0,
            crit_rate   INT NOT NULL DEFAULT 15,
            crit_damage INT NOT NULL DEFAULT 150,
            accuracy    INT NOT NULL DEFAULT 0,
            resistance  INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // ── Attack: add monster_id, make hero_id nullable ─────────────────────
        $this->addSql('ALTER TABLE attack
            ADD COLUMN monster_id INT DEFAULT NULL,
            MODIFY COLUMN hero_id INT DEFAULT NULL
        ');
        $this->addSql('ALTER TABLE attack ADD KEY idx_attack_monster (monster_id)');

        // ── Story Stage ───────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE story_stage (
            id           INT AUTO_INCREMENT NOT NULL,
            stage_number SMALLINT NOT NULL,
            name         VARCHAR(100) NOT NULL,
            description  LONGTEXT DEFAULT NULL,
            active       TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uniq_stage_number (stage_number),
            PRIMARY KEY(id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // ── Story Wave ────────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE story_wave (
            id           INT AUTO_INCREMENT NOT NULL,
            stage_id     INT NOT NULL,
            wave_number  SMALLINT NOT NULL,
            KEY idx_story_wave_stage (stage_id),
            PRIMARY KEY(id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // ── Story Wave Monster ────────────────────────────────────────────────
        $this->addSql('CREATE TABLE story_wave_monster (
            id         INT AUTO_INCREMENT NOT NULL,
            wave_id    INT NOT NULL,
            monster_id INT NOT NULL,
            quantity   SMALLINT NOT NULL DEFAULT 1,
            KEY idx_swm_wave (wave_id),
            KEY idx_swm_monster (monster_id),
            PRIMARY KEY(id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // ── Story Reward ──────────────────────────────────────────────────────
        $this->addSql('CREATE TABLE story_reward (
            id          INT AUTO_INCREMENT NOT NULL,
            stage_id    INT NOT NULL,
            reward_type VARCHAR(10) NOT NULL,
            quantity    INT NOT NULL DEFAULT 1,
            item_id     INT DEFAULT NULL,
            scroll_id   INT DEFAULT NULL,
            KEY idx_story_reward_stage (stage_id),
            KEY idx_story_reward_item (item_id),
            KEY idx_story_reward_scroll (scroll_id),
            PRIMARY KEY(id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // ── User Story Progress ───────────────────────────────────────────────
        $this->addSql('CREATE TABLE user_story_progress (
            id             INT AUTO_INCREMENT NOT NULL,
            user_id        INT NOT NULL,
            stage_id       INT NOT NULL,
            completed_at   DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            reward_claimed TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_user_stage (user_id, stage_id),
            KEY idx_usp_user (user_id),
            KEY idx_usp_stage (stage_id),
            PRIMARY KEY(id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_story_progress');
        $this->addSql('DROP TABLE story_reward');
        $this->addSql('DROP TABLE story_wave_monster');
        $this->addSql('DROP TABLE story_wave');
        $this->addSql('DROP TABLE story_stage');
        $this->addSql('ALTER TABLE attack DROP COLUMN monster_id, MODIFY COLUMN hero_id INT NOT NULL');
        $this->addSql('DROP TABLE monster');
    }
}
