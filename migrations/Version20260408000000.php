<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hero: add scroll_obtainable flag; rarity max raised to 6 (no DB constraint change needed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE hero ADD COLUMN scroll_obtainable TINYINT(1) NOT NULL DEFAULT 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero DROP COLUMN scroll_obtainable');
    }
}
