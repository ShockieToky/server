<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316132320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equipped_extension (id INT AUTO_INCREMENT NOT NULL, slot_index SMALLINT NOT NULL, rolled_value SMALLINT DEFAULT NULL, module_id INT NOT NULL, extension_id INT DEFAULT NULL, INDEX IDX_278D9A36AFC2B591 (module_id), INDEX IDX_278D9A36812D5EB (extension_id), UNIQUE INDEX uniq_equipped_slot (module_id, slot_index), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE extension (id INT AUTO_INCREMENT NOT NULL, stat VARCHAR(10) NOT NULL, rarity VARCHAR(15) NOT NULL, min_value SMALLINT NOT NULL, max_value SMALLINT NOT NULL, UNIQUE INDEX uniq_extension_stat_rarity (stat, rarity), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero_module (id INT AUTO_INCREMENT NOT NULL, slot_index SMALLINT NOT NULL, level SMALLINT NOT NULL, user_hero_id INT NOT NULL, INDEX IDX_9BB4E345C5615C7 (user_hero_id), UNIQUE INDEX uniq_hero_module_slot (user_hero_id, slot_index), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_hero (id INT AUTO_INCREMENT NOT NULL, acquired_at DATETIME NOT NULL, user_id INT NOT NULL, hero_id INT NOT NULL, INDEX IDX_2B4F224FA76ED395 (user_id), INDEX IDX_2B4F224F45B0BCD (hero_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A36AFC2B591 FOREIGN KEY (module_id) REFERENCES hero_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A36812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero_module ADD CONSTRAINT FK_9BB4E345C5615C7 FOREIGN KEY (user_hero_id) REFERENCES user_hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224F45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E864448F8DA FOREIGN KEY (faction_id) REFERENCES faction (id)');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E8687998E FOREIGN KEY (origine_id) REFERENCES origine (id)');
        $this->addSql('ALTER TABLE scroll_rate ADD CONSTRAINT FK_96C232A64724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D24724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A36AFC2B591');
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A36812D5EB');
        $this->addSql('ALTER TABLE hero_module DROP FOREIGN KEY FK_9BB4E345C5615C7');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224FA76ED395');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224F45B0BCD');
        $this->addSql('DROP TABLE equipped_extension');
        $this->addSql('DROP TABLE extension');
        $this->addSql('DROP TABLE hero_module');
        $this->addSql('DROP TABLE user_hero');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E864448F8DA');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E8687998E');
        $this->addSql('ALTER TABLE scroll_rate DROP FOREIGN KEY FK_96C232A64724FEBE');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2A76ED395');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2126F525E');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D24724FEBE');
    }
}
