<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute result_extension_rarity à recipe pour les résultats d'extension par rareté.
 */
final class Version20260316220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add result_extension_rarity column to recipe table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe ADD result_extension_rarity VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe DROP COLUMN result_extension_rarity');
    }
}
