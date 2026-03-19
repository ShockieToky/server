<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319182923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE promo_code (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, rewards JSON NOT NULL, expires_at DATETIME DEFAULT NULL, max_uses INT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3D8C939E77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE promo_code_claim (id INT AUTO_INCREMENT NOT NULL, claimed_at DATETIME NOT NULL, promo_code_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_17A767082FAE4625 (promo_code_id), INDEX IDX_17A76708A76ED395 (user_id), UNIQUE INDEX uq_claim_user_code (promo_code_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE promo_code_claim ADD CONSTRAINT FK_17A767082FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE promo_code_claim ADD CONSTRAINT FK_17A76708A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack ADD CONSTRAINT FK_47C02D3B45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack ADD CONSTRAINT FK_47C02D3BC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack_effect ADD CONSTRAINT FK_B1D3196EF5315759 FOREIGN KEY (attack_id) REFERENCES attack (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack_effect ADD CONSTRAINT FK_B1D3196EF5E9B83B FOREIGN KEY (effect_id) REFERENCES effect (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dungeon_reward ADD CONSTRAINT FK_66F3A9B606863 FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dungeon_reward ADD CONSTRAINT FK_66F3A9126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE dungeon_reward ADD CONSTRAINT FK_66F3A94724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE dungeon_wave_monster ADD CONSTRAINT FK_A0E45B8C9461E358 FOREIGN KEY (wave_id) REFERENCES dungeon_wave (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dungeon_wave_monster ADD CONSTRAINT FK_A0E45B8CC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A36AFC2B591 FOREIGN KEY (module_id) REFERENCES hero_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipped_extension ADD CONSTRAINT FK_278D9A3688DEA9E9 FOREIGN KEY (user_extension_id) REFERENCES user_extension (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E864448F8DA FOREIGN KEY (faction_id) REFERENCES faction (id)');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E8687998E FOREIGN KEY (origine_id) REFERENCES origine (id)');
        $this->addSql('ALTER TABLE hero_module ADD CONSTRAINT FK_9BB4E345C5615C7 FOREIGN KEY (user_hero_id) REFERENCES user_hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B137EE671D7E FOREIGN KEY (result_extension_id) REFERENCES extension (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B1377E804863 FOREIGN KEY (result_scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B13766628929 FOREIGN KEY (result_hero_id) REFERENCES hero (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE1359D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE13126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE134724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE13812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scroll_rate ADD CONSTRAINT FK_96C232A64724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_item CHANGE category category VARCHAR(100) DEFAULT \'Général\' NOT NULL');
        $this->addSql('ALTER TABLE shop_item ADD CONSTRAINT FK_DEE9C365F8D8AFA6 FOREIGN KEY (reward_item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_item ADD CONSTRAINT FK_DEE9C365584DF11D FOREIGN KEY (reward_scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_item ADD CONSTRAINT FK_DEE9C365EEECF635 FOREIGN KEY (reward_hero_id) REFERENCES hero (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_item ADD CONSTRAINT FK_DEE9C3655401DA61 FOREIGN KEY (cost_item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_item ADD CONSTRAINT FK_DEE9C365FC9999FE FOREIGN KEY (cost_scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_shop_item_reward_item TO IDX_DEE9C365F8D8AFA6');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_shop_item_reward_scroll TO IDX_DEE9C365584DF11D');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_shop_item_reward_hero TO IDX_DEE9C365EEECF635');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_shop_item_cost_item TO IDX_DEE9C3655401DA61');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_shop_item_cost_scroll TO IDX_DEE9C365FC9999FE');
        $this->addSql('DROP INDEX IDX_shop_purchase_item ON shop_purchase');
        $this->addSql('ALTER TABLE shop_purchase ADD CONSTRAINT FK_BAAF2BADA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_purchase RENAME INDEX idx_shop_purchase_user TO IDX_BAAF2BADA76ED395');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A12298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A1126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A14724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE story_wave ADD CONSTRAINT FK_4CA9C1A72298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_wave_monster ADD CONSTRAINT FK_39E4A84D9461E358 FOREIGN KEY (wave_id) REFERENCES story_wave (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_wave_monster ADD CONSTRAINT FK_39E4A84DC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_dungeon_progress ADD CONSTRAINT FK_2BF844ADA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_dungeon_progress ADD CONSTRAINT FK_2BF844ADB606863 FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_extension ADD CONSTRAINT FK_9F57B093A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_extension ADD CONSTRAINT FK_9F57B093812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224F45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D24724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_story_progress ADD CONSTRAINT FK_4A613383A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_story_progress ADD CONSTRAINT FK_4A6133832298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE promo_code_claim DROP FOREIGN KEY FK_17A767082FAE4625');
        $this->addSql('ALTER TABLE promo_code_claim DROP FOREIGN KEY FK_17A76708A76ED395');
        $this->addSql('DROP TABLE promo_code');
        $this->addSql('DROP TABLE promo_code_claim');
        $this->addSql('ALTER TABLE attack DROP FOREIGN KEY FK_47C02D3B45B0BCD');
        $this->addSql('ALTER TABLE attack DROP FOREIGN KEY FK_47C02D3BC5FF1223');
        $this->addSql('ALTER TABLE attack_effect DROP FOREIGN KEY FK_B1D3196EF5315759');
        $this->addSql('ALTER TABLE attack_effect DROP FOREIGN KEY FK_B1D3196EF5E9B83B');
        $this->addSql('ALTER TABLE dungeon_reward DROP FOREIGN KEY FK_66F3A9B606863');
        $this->addSql('ALTER TABLE dungeon_reward DROP FOREIGN KEY FK_66F3A9126F525E');
        $this->addSql('ALTER TABLE dungeon_reward DROP FOREIGN KEY FK_66F3A94724FEBE');
        $this->addSql('ALTER TABLE dungeon_wave_monster DROP FOREIGN KEY FK_A0E45B8C9461E358');
        $this->addSql('ALTER TABLE dungeon_wave_monster DROP FOREIGN KEY FK_A0E45B8CC5FF1223');
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A36AFC2B591');
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A3688DEA9E9');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E864448F8DA');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E8687998E');
        $this->addSql('ALTER TABLE hero_module DROP FOREIGN KEY FK_9BB4E345C5615C7');
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B137EE671D7E');
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B1377E804863');
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B13766628929');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE1359D8A214');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE13126F525E');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE134724FEBE');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_22D1FE13812D5EB');
        $this->addSql('ALTER TABLE scroll_rate DROP FOREIGN KEY FK_96C232A64724FEBE');
        $this->addSql('ALTER TABLE shop_item DROP FOREIGN KEY FK_DEE9C365F8D8AFA6');
        $this->addSql('ALTER TABLE shop_item DROP FOREIGN KEY FK_DEE9C365584DF11D');
        $this->addSql('ALTER TABLE shop_item DROP FOREIGN KEY FK_DEE9C365EEECF635');
        $this->addSql('ALTER TABLE shop_item DROP FOREIGN KEY FK_DEE9C3655401DA61');
        $this->addSql('ALTER TABLE shop_item DROP FOREIGN KEY FK_DEE9C365FC9999FE');
        $this->addSql('ALTER TABLE shop_item CHANGE category category VARCHAR(100) DEFAULT \'General\' NOT NULL');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_dee9c365eeecf635 TO IDX_shop_item_reward_hero');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_dee9c365584df11d TO IDX_shop_item_reward_scroll');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_dee9c365f8d8afa6 TO IDX_shop_item_reward_item');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_dee9c3655401da61 TO IDX_shop_item_cost_item');
        $this->addSql('ALTER TABLE shop_item RENAME INDEX idx_dee9c365fc9999fe TO IDX_shop_item_cost_scroll');
        $this->addSql('ALTER TABLE shop_purchase DROP FOREIGN KEY FK_BAAF2BADA76ED395');
        $this->addSql('CREATE INDEX IDX_shop_purchase_item ON shop_purchase (shop_item_id)');
        $this->addSql('ALTER TABLE shop_purchase RENAME INDEX idx_baaf2bada76ed395 TO IDX_shop_purchase_user');
        $this->addSql('ALTER TABLE story_reward DROP FOREIGN KEY FK_1D2C0A12298D193');
        $this->addSql('ALTER TABLE story_reward DROP FOREIGN KEY FK_1D2C0A1126F525E');
        $this->addSql('ALTER TABLE story_reward DROP FOREIGN KEY FK_1D2C0A14724FEBE');
        $this->addSql('ALTER TABLE story_wave DROP FOREIGN KEY FK_4CA9C1A72298D193');
        $this->addSql('ALTER TABLE story_wave_monster DROP FOREIGN KEY FK_39E4A84D9461E358');
        $this->addSql('ALTER TABLE story_wave_monster DROP FOREIGN KEY FK_39E4A84DC5FF1223');
        $this->addSql('ALTER TABLE user_dungeon_progress DROP FOREIGN KEY FK_2BF844ADA76ED395');
        $this->addSql('ALTER TABLE user_dungeon_progress DROP FOREIGN KEY FK_2BF844ADB606863');
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_9F57B093A76ED395');
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_9F57B093812D5EB');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224FA76ED395');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224F45B0BCD');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2A76ED395');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2126F525E');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D24724FEBE');
        $this->addSql('ALTER TABLE user_story_progress DROP FOREIGN KEY FK_4A613383A76ED395');
        $this->addSql('ALTER TABLE user_story_progress DROP FOREIGN KEY FK_4A6133832298D193');
    }
}
