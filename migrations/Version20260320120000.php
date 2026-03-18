<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_purchase table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_purchase (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            shop_item_id INT NOT NULL,
            purchased_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX IDX_shop_purchase_user (user_id),
            INDEX IDX_shop_purchase_item (shop_item_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shop_purchase');
    }
}
