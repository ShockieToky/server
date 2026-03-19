<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create promo_code and promo_code_claim tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE promo_code (
            id          INT AUTO_INCREMENT NOT NULL,
            code        VARCHAR(50)  NOT NULL,
            rewards     JSON         NOT NULL,
            expires_at  DATETIME     DEFAULT NULL,
            max_uses    INT          DEFAULT NULL,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  DATETIME     NOT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX uq_promo_code_code (code)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');

        $this->addSql('CREATE TABLE promo_code_claim (
            id             INT AUTO_INCREMENT NOT NULL,
            promo_code_id  INT NOT NULL,
            user_id        INT NOT NULL,
            claimed_at     DATETIME NOT NULL,
            PRIMARY KEY(id),
            UNIQUE INDEX uq_claim_user_code (promo_code_id, user_id),
            INDEX IDX_promo_code_claim_code (promo_code_id),
            INDEX IDX_promo_code_claim_user (user_id)
        ) ENGINE = MyISAM DEFAULT CHARSET = utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE promo_code_claim');
        $this->addSql('DROP TABLE promo_code');
    }
}
