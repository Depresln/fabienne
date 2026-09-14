<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les compteurs de vues et les commentaires modérés.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post ADD view_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE media ADD view_count INT NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, name VARCHAR(100) NOT NULL, message LONGTEXT NOT NULL, approved TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9474526C4B89032C (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C4B89032C');
        $this->addSql('DROP TABLE comment');
        $this->addSql('ALTER TABLE post DROP view_count');
        $this->addSql('ALTER TABLE media DROP view_count');
    }
}
