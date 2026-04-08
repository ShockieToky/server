<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game_event table and its join tables for dungeons, scrolls, and shop items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_event (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            start_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            end_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game_event_dungeon (
            game_event_id INT NOT NULL,
            dungeon_id INT NOT NULL,
            PRIMARY KEY(game_event_id, dungeon_id),
            INDEX IDX_game_event_dungeon_event (game_event_id),
            INDEX IDX_game_event_dungeon_dungeon (dungeon_id),
            CONSTRAINT FK_game_event_dungeon_event FOREIGN KEY (game_event_id) REFERENCES game_event (id) ON DELETE CASCADE,
            CONSTRAINT FK_game_event_dungeon_dungeon FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game_event_scroll (
            game_event_id INT NOT NULL,
            scroll_id INT NOT NULL,
            PRIMARY KEY(game_event_id, scroll_id),
            INDEX IDX_game_event_scroll_event (game_event_id),
            INDEX IDX_game_event_scroll_scroll (scroll_id),
            CONSTRAINT FK_game_event_scroll_event FOREIGN KEY (game_event_id) REFERENCES game_event (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game_event_shop_item (
            game_event_id INT NOT NULL,
            shop_item_id INT NOT NULL,
            PRIMARY KEY(game_event_id, shop_item_id),
            INDEX IDX_game_event_shop_item_event (game_event_id),
            INDEX IDX_game_event_shop_item_item (shop_item_id),
            CONSTRAINT FK_game_event_shop_item_event FOREIGN KEY (game_event_id) REFERENCES game_event (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game_event_shop_item');
        $this->addSql('DROP TABLE game_event_scroll');
        $this->addSql('DROP TABLE game_event_dungeon');
        $this->addSql('DROP TABLE game_event');
    }
}
