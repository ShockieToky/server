<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add best_turn_count to user_dungeon_progress; create dungeon_auto_session table';
    }

    public function up(Schema $schema): void
    {
        // Meilleur score de tours (nombre d'actions effectuées lors du run le plus rapide)
        $this->addSql('ALTER TABLE user_dungeon_progress
            ADD COLUMN best_turn_count INT NULL DEFAULT NULL
        ');

        // Sessions d'auto-ferme
        $this->addSql('CREATE TABLE dungeon_auto_session (
            id               INT AUTO_INCREMENT NOT NULL,
            user_id          INT NOT NULL,
            dungeon_id       INT NOT NULL,
            duration_seconds INT NOT NULL,
            started_at       DATETIME NOT NULL,
            completions      INT NOT NULL DEFAULT 0,
            rewards_json     LONGTEXT NULL,
            is_claimed       TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_das_user   (user_id),
            INDEX idx_das_dungeon (dungeon_id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_dungeon_progress DROP COLUMN best_turn_count');
        $this->addSql('DROP TABLE dungeon_auto_session');
    }
}
