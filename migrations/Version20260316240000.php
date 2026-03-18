<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create effect, attack and attack_effect tables';
    }

    public function up(Schema $schema): void
    {
        // ── effect ────────────────────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE effect (
                id            INT AUTO_INCREMENT NOT NULL,
                name          VARCHAR(50)  NOT NULL,
                label         VARCHAR(80)  NOT NULL,
                duration_type VARCHAR(10)  NOT NULL DEFAULT \'duration\',
                polarity      VARCHAR(10)  NOT NULL DEFAULT \'negative\',
                description   LONGTEXT     DEFAULT NULL,
                default_value DOUBLE PRECISION DEFAULT NULL,
                UNIQUE KEY uniq_effect_name (name),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = MyISAM
        ');

        // ── attack ────────────────────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE attack (
                id           INT AUTO_INCREMENT NOT NULL,
                hero_id      INT NOT NULL,
                slot_index   SMALLINT    NOT NULL DEFAULT 1,
                name         VARCHAR(100) NOT NULL,
                description  LONGTEXT     DEFAULT NULL,
                hit_count    SMALLINT    NOT NULL DEFAULT 1,
                scaling_stat VARCHAR(10) NOT NULL DEFAULT \'atk\',
                scaling_pct  SMALLINT    NOT NULL DEFAULT 100,
                target_type  VARCHAR(20) NOT NULL DEFAULT \'single_enemy\',
                cooldown     SMALLINT    NOT NULL DEFAULT 0,
                KEY idx_attack_hero (hero_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = MyISAM
        ');

        // ── attack_effect ─────────────────────────────────────────────────────
        $this->addSql('
            CREATE TABLE attack_effect (
                id            INT AUTO_INCREMENT NOT NULL,
                attack_id     INT NOT NULL,
                effect_id     INT NOT NULL,
                chance        SMALLINT NOT NULL DEFAULT 100,
                duration      SMALLINT DEFAULT NULL,
                value         DOUBLE PRECISION DEFAULT NULL,
                effect_target VARCHAR(20) NOT NULL DEFAULT \'target\',
                per_hit       TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_ae_attack (attack_id),
                KEY idx_ae_effect (effect_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = MyISAM
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE attack_effect');
        $this->addSql('DROP TABLE attack');
        $this->addSql('DROP TABLE effect');
    }
}
