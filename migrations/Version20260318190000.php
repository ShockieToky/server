<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** SUPPRIMÉE — doublon de Version20260318100721 (special_code déjà ajouté) */
final class Version20260318190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '[supprimée] doublon special_code';
    }

    public function up(Schema $schema): void {}

    public function down(Schema $schema): void {}
}

