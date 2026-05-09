<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504132602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add level column to user_hero table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_hero ADD level SMALLINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_hero DROP COLUMN level');
    }
}
