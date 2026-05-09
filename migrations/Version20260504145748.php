<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504145748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add xp column to user_hero';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_hero ADD xp INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_hero DROP COLUMN xp');
    }
}
