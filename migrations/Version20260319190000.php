<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** SUPPRIMÉE — doublon de Version20260318104249 (drop_chance déjà ajouté) */
final class Version20260319190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[supprimée] doublon drop_chance';
    }

    public function up(Schema $schema): void {}

    public function down(Schema $schema): void {}
}

