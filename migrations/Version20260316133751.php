<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316133751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A36AFC2B591 FOREIGN KEY (module_id) REFERENCES hero_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A36812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E864448F8DA FOREIGN KEY (faction_id) REFERENCES faction (id)');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E8687998E FOREIGN KEY (origine_id) REFERENCES origine (id)');
        $this->addSql('ALTER TABLE hero_module ADD CONSTRAINT FK_9BB4E345C5615C7 FOREIGN KEY (user_hero_id) REFERENCES user_hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scroll_rate ADD CONSTRAINT FK_96C232A64724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224F45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D24724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A36AFC2B591');
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A36812D5EB');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E864448F8DA');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E8687998E');
        $this->addSql('ALTER TABLE hero_module DROP FOREIGN KEY FK_9BB4E345C5615C7');
        $this->addSql('ALTER TABLE scroll_rate DROP FOREIGN KEY FK_96C232A64724FEBE');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224FA76ED395');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224F45B0BCD');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2A76ED395');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2126F525E');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D24724FEBE');
    }
}
