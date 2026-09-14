<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830113500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme la table de commentaires afin d’éviter un mot réservé MariaDB.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE `comment` TO post_comment');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('RENAME TABLE post_comment TO `comment`');
    }
}
