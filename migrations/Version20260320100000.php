<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category column to dungeon table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dungeon ADD category VARCHAR(100) NOT NULL DEFAULT 'Général'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dungeon DROP COLUMN category");
    }
}
