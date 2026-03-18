<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add special_code column to attack table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE attack ADD special_code VARCHAR(60) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE attack DROP COLUMN special_code");
    }
}
