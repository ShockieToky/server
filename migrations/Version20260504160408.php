<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260504160408 extends AbstractMigration
{
    public function getDescription(): string { return 'Add xp_reward to dungeon and story_stage'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dungeon ADD xp_reward INT DEFAULT NULL');
        $this->addSql('ALTER TABLE story_stage ADD xp_reward INT DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dungeon DROP COLUMN xp_reward');
        $this->addSql('ALTER TABLE story_stage DROP COLUMN xp_reward');
    }
}
