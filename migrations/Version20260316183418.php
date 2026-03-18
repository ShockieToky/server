<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316183418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recipe (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, category VARCHAR(20) NOT NULL, active TINYINT DEFAULT 1 NOT NULL, result_type VARCHAR(20) NOT NULL, result_quantity INT DEFAULT 1 NOT NULL, result_extension_id INT DEFAULT NULL, result_scroll_id INT DEFAULT NULL, result_hero_id INT DEFAULT NULL, INDEX IDX_DA88B137EE671D7E (result_extension_id), INDEX IDX_DA88B1377E804863 (result_scroll_id), INDEX IDX_DA88B13766628929 (result_hero_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recipe_ingredient (id INT AUTO_INCREMENT NOT NULL, ingredient_type VARCHAR(20) NOT NULL, extension_rarity VARCHAR(20) DEFAULT NULL, quantity INT DEFAULT 1 NOT NULL, recipe_id INT NOT NULL, item_id INT DEFAULT NULL, scroll_id INT DEFAULT NULL, extension_id INT DEFAULT NULL, INDEX IDX_22D1FE1359D8A214 (recipe_id), INDEX IDX_22D1FE13126F525E (item_id), INDEX IDX_22D1FE134724FEBE (scroll_id), INDEX IDX_22D1FE13812D5EB (extension_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B137EE671D7E FOREIGN KEY (result_extension_id) REFERENCES extension (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B1377E804863 FOREIGN KEY (result_scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B13766628929 FOREIGN KEY (result_hero_id) REFERENCES hero (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE1359D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE13126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE134724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE13812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A36AFC2B591 FOREIGN KEY (module_id) REFERENCES hero_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A3688DEA9E9 FOREIGN KEY (user_extension_id) REFERENCES user_extension (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E864448F8DA FOREIGN KEY (faction_id) REFERENCES faction (id)');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E8687998E FOREIGN KEY (origine_id) REFERENCES origine (id)');
        $this->addSql('ALTER TABLE hero_module ADD CONSTRAINT FK_9BB4E345C5615C7 FOREIGN KEY (user_hero_id) REFERENCES user_hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scroll_rate ADD CONSTRAINT FK_96C232A64724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_extension ADD CONSTRAINT FK_9F57B093A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_extension ADD CONSTRAINT FK_9F57B093812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_extension RENAME INDEX idx_user_ext_user TO IDX_9F57B093A76ED395');
        $this->addSql('ALTER TABLE user_extension RENAME INDEX idx_user_ext_extension TO IDX_9F57B093812D5EB');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224F45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D24724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B137EE671D7E');
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B1377E804863');
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B13766628929');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE1359D8A214');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE13126F525E');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE134724FEBE');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE13812D5EB');
        $this->addSql('DROP TABLE recipe');
        $this->addSql('DROP TABLE recipe_ingredient');
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A36AFC2B591');
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A3688DEA9E9');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E864448F8DA');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E8687998E');
        $this->addSql('ALTER TABLE hero_module DROP FOREIGN KEY FK_9BB4E345C5615C7');
        $this->addSql('ALTER TABLE scroll_rate DROP FOREIGN KEY FK_96C232A64724FEBE');
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_9F57B093A76ED395');
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_9F57B093812D5EB');
        $this->addSql('ALTER TABLE user_extension RENAME INDEX idx_9f57b093a76ed395 TO IDX_user_ext_user');
        $this->addSql('ALTER TABLE user_extension RENAME INDEX idx_9f57b093812d5eb TO IDX_user_ext_extension');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224FA76ED395');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224F45B0BCD');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2A76ED395');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2126F525E');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D24724FEBE');
    }
}
