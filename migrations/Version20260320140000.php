<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_deck table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_deck (
            id               INT AUTO_INCREMENT NOT NULL,
            user_id          INT NOT NULL,
            hero1_id         INT DEFAULT NULL,
            hero2_id         INT DEFAULT NULL,
            hero3_id         INT DEFAULT NULL,
            hero4_id         INT DEFAULT NULL,
            name             VARCHAR(50) NOT NULL,
            lead_faction_id  INT DEFAULT NULL,
            lead_origine_id  INT DEFAULT NULL,
            created_at       DATETIME NOT NULL,
            updated_at       DATETIME NOT NULL,
            PRIMARY KEY(id),
            INDEX IDX_user_deck_user   (user_id),
            INDEX IDX_user_deck_hero1  (hero1_id),
            INDEX IDX_user_deck_hero2  (hero2_id),
            INDEX IDX_user_deck_hero3  (hero3_id),
            INDEX IDX_user_deck_hero4  (hero4_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_deck');
    }
}
