<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Système de donjons PVE rejouables avec IA avancée.
 */
final class Version20260317180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dungeon tables (dungeon, dungeon_wave, dungeon_wave_monster, dungeon_reward, user_dungeon_progress)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dungeon (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            difficulty VARCHAR(12) NOT NULL DEFAULT \'normal\',
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE dungeon_wave (
            id INT AUTO_INCREMENT NOT NULL,
            dungeon_id INT NOT NULL,
            wave_number SMALLINT NOT NULL,
            PRIMARY KEY(id),
            INDEX IDX_dungeon_wave_dungeon (dungeon_id),
            CONSTRAINT FK_dungeon_wave_dungeon FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE dungeon_wave_monster (
            id INT AUTO_INCREMENT NOT NULL,
            wave_id INT NOT NULL,
            monster_id INT NOT NULL,
            quantity SMALLINT NOT NULL DEFAULT 1,
            PRIMARY KEY(id),
            INDEX IDX_dungeon_wave_monster_wave (wave_id),
            INDEX IDX_dungeon_wave_monster_monster (monster_id),
            CONSTRAINT FK_dungeon_wave_monster_wave FOREIGN KEY (wave_id) REFERENCES dungeon_wave (id) ON DELETE CASCADE,
            CONSTRAINT FK_dungeon_wave_monster_monster FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE dungeon_reward (
            id INT AUTO_INCREMENT NOT NULL,
            dungeon_id INT NOT NULL,
            reward_type VARCHAR(15) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            item_id INT DEFAULT NULL,
            scroll_id INT DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX IDX_dungeon_reward_dungeon (dungeon_id),
            INDEX IDX_dungeon_reward_item (item_id),
            INDEX IDX_dungeon_reward_scroll (scroll_id),
            CONSTRAINT FK_dungeon_reward_dungeon FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE,
            CONSTRAINT FK_dungeon_reward_item FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL,
            CONSTRAINT FK_dungeon_reward_scroll FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE user_dungeon_progress (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            dungeon_id INT NOT NULL,
            run_count INT NOT NULL DEFAULT 0,
            last_completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE INDEX uniq_user_dungeon (user_id, dungeon_id),
            INDEX IDX_udp_user (user_id),
            INDEX IDX_udp_dungeon (dungeon_id),
            CONSTRAINT FK_udp_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
            CONSTRAINT FK_udp_dungeon FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_dungeon_progress');
        $this->addSql('DROP TABLE dungeon_reward');
        $this->addSql('DROP TABLE dungeon_wave_monster');
        $this->addSql('DROP TABLE dungeon_wave');
        $this->addSql('DROP TABLE dungeon');
    }
}
