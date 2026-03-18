<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Système de crafting : création des tables recipe et recipe_ingredient.
 */
final class Version20260316210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create recipe and recipe_ingredient tables for the crafting system';
    }

    public function up(Schema $schema): void
    {
        // 1. Table recipe
        $this->addSql('CREATE TABLE recipe (
            id              INT AUTO_INCREMENT NOT NULL,
            name            VARCHAR(150)        NOT NULL,
            category        VARCHAR(20)         NOT NULL,
            active          TINYINT(1)          NOT NULL DEFAULT 1,
            result_type     VARCHAR(20)         NOT NULL,
            result_extension_id INT             DEFAULT NULL,
            result_scroll_id    INT             DEFAULT NULL,
            result_hero_id      INT             DEFAULT NULL,
            result_quantity INT                 NOT NULL DEFAULT 1,
            INDEX IDX_recipe_cat (category),
            INDEX IDX_recipe_result_ext (result_extension_id),
            INDEX IDX_recipe_result_scroll (result_scroll_id),
            INDEX IDX_recipe_result_hero (result_hero_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE recipe
            ADD CONSTRAINT FK_recipe_result_extension
            FOREIGN KEY (result_extension_id) REFERENCES extension (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE recipe
            ADD CONSTRAINT FK_recipe_result_scroll
            FOREIGN KEY (result_scroll_id) REFERENCES scroll (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE recipe
            ADD CONSTRAINT FK_recipe_result_hero
            FOREIGN KEY (result_hero_id) REFERENCES hero (id) ON DELETE SET NULL');

        // 2. Table recipe_ingredient
        $this->addSql('CREATE TABLE recipe_ingredient (
            id               INT AUTO_INCREMENT NOT NULL,
            recipe_id        INT                NOT NULL,
            ingredient_type  VARCHAR(20)        NOT NULL,
            item_id          INT                DEFAULT NULL,
            scroll_id        INT                DEFAULT NULL,
            extension_id     INT                DEFAULT NULL,
            extension_rarity VARCHAR(20)        DEFAULT NULL,
            quantity         INT                NOT NULL DEFAULT 1,
            INDEX IDX_ri_recipe (recipe_id),
            INDEX IDX_ri_item (item_id),
            INDEX IDX_ri_scroll (scroll_id),
            INDEX IDX_ri_extension (extension_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE recipe_ingredient
            ADD CONSTRAINT FK_ri_recipe
            FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE recipe_ingredient
            ADD CONSTRAINT FK_ri_item
            FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE recipe_ingredient
            ADD CONSTRAINT FK_ri_scroll
            FOREIGN KEY (scroll_id) REFERENCES scroll (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE recipe_ingredient
            ADD CONSTRAINT FK_ri_extension
            FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_ri_recipe');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_ri_item');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_ri_scroll');
        $this->addSql('ALTER TABLE recipe_ingredient DROP FOREIGN KEY FK_ri_extension');
        $this->addSql('DROP TABLE recipe_ingredient');

        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_recipe_result_extension');
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_recipe_result_scroll');
        $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_recipe_result_hero');
        $this->addSql('DROP TABLE recipe');
    }
}
