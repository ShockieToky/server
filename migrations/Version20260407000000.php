<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_currency and user_event_currency tables; add event_currency FK to dungeon_reward and shop_item';
    }

    public function up(Schema $schema): void
    {
        // ── event_currency ────────────────────────────────────────────────────
        $this->addSql("CREATE TABLE event_currency (
            id           INT AUTO_INCREMENT NOT NULL,
            game_event_id INT NOT NULL,
            name         VARCHAR(80)  NOT NULL,
            description  LONGTEXT     DEFAULT NULL,
            icon         VARCHAR(10)  NOT NULL DEFAULT '🪙',
            sort_order   INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            INDEX IDX_event_currency_event (game_event_id),
            CONSTRAINT FK_event_currency_event
                FOREIGN KEY (game_event_id) REFERENCES game_event (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // ── user_event_currency ───────────────────────────────────────────────
        $this->addSql("CREATE TABLE user_event_currency (
            id                INT AUTO_INCREMENT NOT NULL,
            user_id           INT NOT NULL,
            event_currency_id INT NOT NULL,
            amount            INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY UQ_user_event_currency (user_id, event_currency_id),
            INDEX IDX_user_event_currency_user     (user_id),
            INDEX IDX_user_event_currency_currency (event_currency_id),
            CONSTRAINT FK_user_event_currency_user
                FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
            CONSTRAINT FK_user_event_currency_currency
                FOREIGN KEY (event_currency_id) REFERENCES event_currency (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // ── dungeon_reward: add event_currency FK ─────────────────────────────
        $this->addSql('ALTER TABLE dungeon_reward
            ADD COLUMN event_currency_id INT DEFAULT NULL,
            ADD INDEX IDX_dungeon_reward_currency (event_currency_id),
            ADD CONSTRAINT FK_dungeon_reward_currency
                FOREIGN KEY (event_currency_id) REFERENCES event_currency (id) ON DELETE SET NULL');

        // Extend reward_type column to fit 'event_currency'
        $this->addSql("ALTER TABLE dungeon_reward MODIFY COLUMN reward_type VARCHAR(20) NOT NULL DEFAULT 'gold'");

        // ── shop_item: add cost_event_currency + reward_event_currency FKs ────
        $this->addSql('ALTER TABLE shop_item
            ADD COLUMN cost_event_currency_id   INT DEFAULT NULL,
            ADD COLUMN reward_event_currency_id INT DEFAULT NULL,
            ADD INDEX IDX_shop_item_cost_currency   (cost_event_currency_id),
            ADD INDEX IDX_shop_item_reward_currency (reward_event_currency_id),
            ADD CONSTRAINT FK_shop_item_cost_currency
                FOREIGN KEY (cost_event_currency_id)   REFERENCES event_currency (id) ON DELETE SET NULL,
            ADD CONSTRAINT FK_shop_item_reward_currency
                FOREIGN KEY (reward_event_currency_id) REFERENCES event_currency (id) ON DELETE SET NULL');

        // Extend cost_type and reward_type columns
        $this->addSql("ALTER TABLE shop_item
            MODIFY COLUMN cost_type   VARCHAR(20) NOT NULL DEFAULT 'gold',
            MODIFY COLUMN reward_type VARCHAR(20) NOT NULL DEFAULT 'gold'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_item
            DROP FOREIGN KEY FK_shop_item_cost_currency,
            DROP FOREIGN KEY FK_shop_item_reward_currency,
            DROP INDEX IDX_shop_item_cost_currency,
            DROP INDEX IDX_shop_item_reward_currency,
            DROP COLUMN cost_event_currency_id,
            DROP COLUMN reward_event_currency_id');

        $this->addSql('ALTER TABLE dungeon_reward
            DROP FOREIGN KEY FK_dungeon_reward_currency,
            DROP INDEX IDX_dungeon_reward_currency,
            DROP COLUMN event_currency_id');

        $this->addSql('DROP TABLE user_event_currency');
        $this->addSql('DROP TABLE event_currency');
    }
}
