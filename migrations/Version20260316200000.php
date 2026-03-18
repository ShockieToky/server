<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Système UserExtension : les extensions sont maintenant possédées par les joueurs
 * avant d'être équipées dans un module.
 *
 * - Crée la table user_extension (id, user_id, extension_id, rolled_value)
 * - Modifie equipped_extension : supprime extension_id + rolled_value,
 *   ajoute user_extension_id (nullable, unique)
 */
final class Version20260316200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_extension table; replace extension_id+rolled_value in equipped_extension with user_extension_id';
    }

    public function up(Schema $schema): void
    {
        // 1. Créer la table user_extension
        $this->addSql('CREATE TABLE user_extension (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            extension_id INT NOT NULL,
            rolled_value SMALLINT NOT NULL,
            INDEX IDX_user_ext_user (user_id),
            INDEX IDX_user_ext_extension (extension_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE user_extension
            ADD CONSTRAINT FK_user_ext_user
            FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE user_extension
            ADD CONSTRAINT FK_user_ext_extension
            FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE');

        // 2. Modifier equipped_extension : supprimer l'ancien FK + colonnes
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_278D9A36812D5EB');
        $this->addSql('DROP INDEX IDX_278D9A36812D5EB ON equipped_extension');
        $this->addSql('ALTER TABLE equipped_extension DROP COLUMN extension_id, DROP COLUMN rolled_value');

        // 3. Ajouter user_extension_id avec contrainte unique
        $this->addSql('ALTER TABLE equipped_extension ADD COLUMN user_extension_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipped_extension
            ADD CONSTRAINT FK_equipped_user_ext
            FOREIGN KEY (user_extension_id) REFERENCES user_extension (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_equipped_user_ext ON equipped_extension (user_extension_id)');
    }

    public function down(Schema $schema): void
    {
        // Restaurer equipped_extension
        $this->addSql('ALTER TABLE equipped_extension DROP FOREIGN KEY FK_equipped_user_ext');
        $this->addSql('DROP INDEX uniq_equipped_user_ext ON equipped_extension');
        $this->addSql('ALTER TABLE equipped_extension DROP COLUMN user_extension_id');
        $this->addSql('ALTER TABLE equipped_extension ADD COLUMN extension_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipped_extension ADD COLUMN rolled_value SMALLINT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_278D9A36812D5EB ON equipped_extension (extension_id)');
        $this->addSql('ALTER TABLE equipped_extension
            ADD CONSTRAINT FK_278D9A36812D5EB
            FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE SET NULL');

        // Supprimer user_extension
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_user_ext_user');
        $this->addSql('ALTER TABLE user_extension DROP FOREIGN KEY FK_user_ext_extension');
        $this->addSql('DROP TABLE user_extension');
    }
}
