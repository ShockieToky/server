<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317142534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attack CHANGE slot_index slot_index SMALLINT NOT NULL');
        $this->addSql('ALTER TABLE attack ADD CONSTRAINT FK_47C02D3B45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack ADD CONSTRAINT FK_47C02D3BC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack RENAME INDEX idx_attack_hero TO IDX_47C02D3B45B0BCD');
        $this->addSql('ALTER TABLE attack RENAME INDEX idx_attack_monster TO IDX_47C02D3BC5FF1223');
        $this->addSql('ALTER TABLE attack_effect ADD CONSTRAINT FK_B1D3196EF5315759 FOREIGN KEY (attack_id) REFERENCES attack (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack_effect ADD CONSTRAINT FK_B1D3196EF5E9B83B FOREIGN KEY (effect_id) REFERENCES effect (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack_effect RENAME INDEX idx_ae_attack TO IDX_B1D3196EF5315759');
        $this->addSql('ALTER TABLE attack_effect RENAME INDEX idx_ae_effect TO IDX_B1D3196EF5E9B83B');
        $this->addSql('ALTER TABLE effect CHANGE duration_type duration_type VARCHAR(10) NOT NULL, CHANGE polarity polarity VARCHAR(10) NOT NULL');
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
        $this->addSql('ALTER TABLE story_reward CHANGE reward_type reward_type VARCHAR(15) NOT NULL');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A12298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A1126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A14724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE story_reward RENAME INDEX idx_story_reward_stage TO IDX_1D2C0A12298D193');
        $this->addSql('ALTER TABLE story_reward RENAME INDEX idx_story_reward_item TO IDX_1D2C0A1126F525E');
        $this->addSql('ALTER TABLE story_reward RENAME INDEX idx_story_reward_scroll TO IDX_1D2C0A14724FEBE');
        $this->addSql('ALTER TABLE story_wave ADD CONSTRAINT FK_4CA9C1A72298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_wave RENAME INDEX idx_story_wave_stage TO IDX_4CA9C1A72298D193');
        $this->addSql('ALTER TABLE story_wave_monster ADD CONSTRAINT FK_39E4A84D9461E358 FOREIGN KEY (wave_id) REFERENCES story_wave (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_wave_monster ADD CONSTRAINT FK_39E4A84DC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_wave_monster RENAME INDEX idx_swm_wave TO IDX_39E4A84D9461E358');
        $this->addSql('ALTER TABLE story_wave_monster RENAME INDEX idx_swm_monster TO IDX_39E4A84DC5FF1223');
        $this->addSql('ALTER TABLE user_extension ADD CONSTRAINT FK_9F57B093A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_extension ADD CONSTRAINT FK_9F57B093812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_hero ADD CONSTRAINT FK_2B4F224F45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D2126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_inventory ADD CONSTRAINT FK_B1CDC7D24724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_story_progress CHANGE completed_at completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_story_progress ADD CONSTRAINT FK_4A613383A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_story_progress ADD CONSTRAINT FK_4A6133832298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_story_progress RENAME INDEX idx_usp_user TO IDX_4A613383A76ED395');
        $this->addSql('ALTER TABLE user_story_progress RENAME INDEX idx_usp_stage TO IDX_4A6133832298D193');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attack DROP FOREIGN KEY FK_47C02D3B45B0BCD');
        $this->addSql('ALTER TABLE attack DROP FOREIGN KEY FK_47C02D3BC5FF1223');
        $this->addSql('ALTER TABLE attack CHANGE slot_index slot_index SMALLINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE attack RENAME INDEX idx_47c02d3bc5ff1223 TO idx_attack_monster');
        $this->addSql('ALTER TABLE attack RENAME INDEX idx_47c02d3b45b0bcd TO idx_attack_hero');
        $this->addSql('ALTER TABLE attack_effect DROP FOREIGN KEY FK_B1D3196EF5315759');
        $this->addSql('ALTER TABLE attack_effect DROP FOREIGN KEY FK_B1D3196EF5E9B83B');
        $this->addSql('ALTER TABLE attack_effect RENAME INDEX idx_b1d3196ef5315759 TO idx_ae_attack');
        $this->addSql('ALTER TABLE attack_effect RENAME INDEX idx_b1d3196ef5e9b83b TO idx_ae_effect');
        $this->addSql('ALTER TABLE effect CHANGE duration_type duration_type VARCHAR(10) DEFAULT \'duration\' NOT NULL, CHANGE polarity polarity VARCHAR(10) DEFAULT \'negative\' NOT NULL');
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
        $this->addSql('ALTER TABLE story_reward DROP FOREIGN KEY FK_1D2C0A12298D193');
        $this->addSql('ALTER TABLE story_reward DROP FOREIGN KEY FK_1D2C0A1126F525E');
        $this->addSql('ALTER TABLE story_reward DROP FOREIGN KEY FK_1D2C0A14724FEBE');
        $this->addSql('ALTER TABLE story_reward CHANGE reward_type reward_type VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE story_reward RENAME INDEX idx_1d2c0a12298d193 TO idx_story_reward_stage');
        $this->addSql('ALTER TABLE story_reward RENAME INDEX idx_1d2c0a1126f525e TO idx_story_reward_item');
        $this->addSql('ALTER TABLE story_reward RENAME INDEX idx_1d2c0a14724febe TO idx_story_reward_scroll');
        $this->addSql('ALTER TABLE story_wave DROP FOREIGN KEY FK_4CA9C1A72298D193');
        $this->addSql('ALTER TABLE story_wave RENAME INDEX idx_4ca9c1a72298d193 TO idx_story_wave_stage');
        $this->addSql('ALTER TABLE story_wave_monster DROP FOREIGN KEY FK_39E4A84D9461E358');
        $this->addSql('ALTER TABLE story_wave_monster DROP FOREIGN KEY FK_39E4A84DC5FF1223');
        $this->addSql('ALTER TABLE story_wave_monster RENAME INDEX idx_39e4a84d9461e358 TO idx_swm_wave');
        $this->addSql('ALTER TABLE story_wave_monster RENAME INDEX idx_39e4a84dc5ff1223 TO idx_swm_monster');
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_9F57B093A76ED395');
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_9F57B093812D5EB');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224FA76ED395');
        $this->addSql('ALTER TABLE user_hero DROP FOREIGN KEY FK_2B4F224F45B0BCD');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2A76ED395');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D2126F525E');
        $this->addSql('ALTER TABLE user_inventory DROP FOREIGN KEY FK_B1CDC7D24724FEBE');
        $this->addSql('ALTER TABLE user_story_progress DROP FOREIGN KEY FK_4A613383A76ED395');
        $this->addSql('ALTER TABLE user_story_progress DROP FOREIGN KEY FK_4A6133832298D193');
        $this->addSql('ALTER TABLE user_story_progress CHANGE completed_at completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_story_progress RENAME INDEX idx_4a613383a76ed395 TO idx_usp_user');
        $this->addSql('ALTER TABLE user_story_progress RENAME INDEX idx_4a6133832298d193 TO idx_usp_stage');
    }
}
