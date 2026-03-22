<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task_data JSON column to training_slot for storing user-chosen extension assignments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_slot ADD task_data JSON NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_slot DROP COLUMN task_data');
    }
}
