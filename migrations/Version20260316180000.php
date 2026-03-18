<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add effect_type column to item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item ADD effect_type VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item DROP effect_type');
    }
}
