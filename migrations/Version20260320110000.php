<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_item (
            id INT AUTO_INCREMENT NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT \'General\',
            name VARCHAR(150) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            reward_type VARCHAR(20) NOT NULL DEFAULT \'gold\',
            reward_quantity INT NOT NULL DEFAULT 1,
            reward_item_id INT DEFAULT NULL,
            reward_scroll_id INT DEFAULT NULL,
            reward_hero_id INT DEFAULT NULL,
            cost_type VARCHAR(20) NOT NULL DEFAULT \'gold\',
            cost_quantity INT NOT NULL DEFAULT 1,
            cost_item_id INT DEFAULT NULL,
            cost_scroll_id INT DEFAULT NULL,
            limit_per_account INT DEFAULT NULL,
            limit_period VARCHAR(10) DEFAULT NULL,
            limit_per_period INT DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY(id),
            INDEX IDX_shop_item_reward_item (reward_item_id),
            INDEX IDX_shop_item_reward_scroll (reward_scroll_id),
            INDEX IDX_shop_item_reward_hero (reward_hero_id),
            INDEX IDX_shop_item_cost_item (cost_item_id),
            INDEX IDX_shop_item_cost_scroll (cost_scroll_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shop_item');
    }
}
