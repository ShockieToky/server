<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260319190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add drop_chance column to dungeon_reward table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dungeon_reward ADD drop_chance INT NOT NULL DEFAULT 100");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dungeon_reward DROP COLUMN drop_chance");
    }
}
