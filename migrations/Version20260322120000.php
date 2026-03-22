<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add training_slot table for the idle Training Center feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE training_slot (
                id          INT AUTO_INCREMENT NOT NULL,
                user_id     INT NOT NULL,
                user_hero_id INT NOT NULL,
                slot_index  SMALLINT NOT NULL,
                task_type   VARCHAR(30) NOT NULL,
                started_at  DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                finished_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                claimed_at  DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_training_user (user_id),
                INDEX IDX_training_hero (user_hero_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE training_slot
                ADD CONSTRAINT FK_training_slot_user
                    FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
                ADD CONSTRAINT FK_training_slot_hero
                    FOREIGN KEY (user_hero_id) REFERENCES user_hero (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_slot DROP FOREIGN KEY FK_training_slot_user');
        $this->addSql('ALTER TABLE training_slot DROP FOREIGN KEY FK_training_slot_hero');
        $this->addSql('DROP TABLE training_slot');
    }
}
