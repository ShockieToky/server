<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Récompenses de donjon : quantity → quantity_min + quantity_max (plage de loot).
 */
final class Version20260317190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DungeonReward: replace quantity with quantity_min + quantity_max for loot ranges';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dungeon_reward
            ADD quantity_min INT NOT NULL DEFAULT 1,
            ADD quantity_max INT NOT NULL DEFAULT 1');
        // Copier les éventuelles données existantes
        $this->addSql('UPDATE dungeon_reward SET quantity_min = quantity, quantity_max = quantity');
        $this->addSql('ALTER TABLE dungeon_reward DROP COLUMN quantity');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dungeon_reward ADD quantity INT NOT NULL DEFAULT 1');
        $this->addSql('UPDATE dungeon_reward SET quantity = quantity_min');
        $this->addSql('ALTER TABLE dungeon_reward DROP COLUMN quantity_min, DROP COLUMN quantity_max');
    }
}
