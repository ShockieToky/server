<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317183412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dungeon_reward (id INT AUTO_INCREMENT NOT NULL, reward_type VARCHAR(15) NOT NULL, quantity INT DEFAULT 1 NOT NULL, dungeon_id INT NOT NULL, item_id INT DEFAULT NULL, scroll_id INT DEFAULT NULL, INDEX IDX_66F3A9B606863 (dungeon_id), INDEX IDX_66F3A9126F525E (item_id), INDEX IDX_66F3A94724FEBE (scroll_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dungeon_wave_monster (id INT AUTO_INCREMENT NOT NULL, quantity SMALLINT DEFAULT 1 NOT NULL, wave_id INT NOT NULL, monster_id INT NOT NULL, INDEX IDX_A0E45B8C9461E358 (wave_id), INDEX IDX_A0E45B8CC5FF1223 (monster_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_dungeon_progress (id INT AUTO_INCREMENT NOT NULL, run_count INT DEFAULT 0 NOT NULL, last_completed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, dungeon_id INT NOT NULL, INDEX IDX_2BF844ADA76ED395 (user_id), INDEX IDX_2BF844ADB606863 (dungeon_id), UNIQUE INDEX uniq_user_dungeon (user_id, dungeon_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE dungeon_reward ADD CONSTRAINT FK_66F3A9B606863 FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dungeon_reward ADD CONSTRAINT FK_66F3A9126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE dungeon_reward ADD CONSTRAINT FK_66F3A94724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE dungeon_wave_monster ADD CONSTRAINT FK_A0E45B8C9461E358 FOREIGN KEY (wave_id) REFERENCES dungeon_wave (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dungeon_wave_monster ADD CONSTRAINT FK_A0E45B8CC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_dungeon_progress ADD CONSTRAINT FK_2BF844ADA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_dungeon_progress ADD CONSTRAINT FK_2BF844ADB606863 FOREIGN KEY (dungeon_id) REFERENCES dungeon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack ADD CONSTRAINT FK_47C02D3B45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack ADD CONSTRAINT FK_47C02D3BC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack_effect ADD CONSTRAINT FK_B1D3196EF5315759 FOREIGN KEY (attack_id) REFERENCES attack (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attack_effect ADD CONSTRAINT FK_B1D3196EF5E9B83B FOREIGN KEY (effect_id) REFERENCES effect (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dungeon_wave RENAME INDEX idx_dungeon_wave_dungeon TO IDX_BED06171B606863');
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
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A12298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A1126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE story_reward ADD CONSTRAINT FK_1D2C0A14724FEBE FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE story_wave ADD CONSTRAINT FK_4CA9C1A72298D193 FOREIGN KEY (stage_id) REFERENCES story_stage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_wave_monster ADD CONSTRAINT FK_39E4A84D9461E358 FOREIGN KEY (wave_id) REFERENCES story_wave (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE story_wave_monster ADD CONSTRAINT FK_39E4A84DC5FF1223 FOREIGN KEY (monster_id) REFERENCES monster (id) ON DELETE CASCADE');
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
        $this->addSql('ALTER TABLE dungeon_reward DROP FOREIGN KEY FK_66F3A9B606863');
        $this->addSql('ALTER TABLE dungeon_reward DROP FOREIGN KEY FK_66F3A9126F525E');
        $this->addSql('ALTER TABLE dungeon_reward DROP FOREIGN KEY FK_66F3A94724FEBE');
        $this->addSql('ALTER TABLE dungeon_wave_monster DROP FOREIGN KEY FK_A0E45B8C9461E358');
        $this->addSql('ALTER TABLE dungeon_wave_monster DROP FOREIGN KEY FK_A0E45B8CC5FF1223');
        $this->addSql('ALTER TABLE user_dungeon_progress DROP FOREIGN KEY FK_2BF844ADA76ED395');
        $this->addSql('ALTER TABLE user_dungeon_progress DROP FOREIGN KEY FK_2BF844ADB606863');
        $this->addSql('DROP TABLE dungeon_reward');
        $this->addSql('DROP TABLE dungeon_wave_monster');
        $this->addSql('DROP TABLE user_dungeon_progress');
        $this->addSql('ALTER TABLE attack DROP FOREIGN KEY FK_47C02D3B45B0BCD');
        $this->addSql('ALTER TABLE attack DROP FOREIGN KEY FK_47C02D3BC5FF1223');
        $this->addSql('ALTER TABLE attack_effect DROP FOREIGN KEY FK_B1D3196EF5315759');
        $this->addSql('ALTER TABLE attack_effect DROP FOREIGN KEY FK_B1D3196EF5E9B83B');
        $this->addSql('ALTER TABLE dungeon_wave RENAME INDEX idx_bed06171b606863 TO IDX_dungeon_wave_dungeon');
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
        $this->addSql('ALTER TABLE story_wave DROP FOREIGN KEY FK_4CA9C1A72298D193');
        $this->addSql('ALTER TABLE story_wave_monster DROP FOREIGN KEY FK_39E4A84D9461E358');
        $this->addSql('ALTER TABLE story_wave_monster DROP FOREIGN KEY FK_39E4A84DC5FF1223');
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
