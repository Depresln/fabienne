<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le contenu éditable du message d’accueil.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contenu (id INT AUTO_INCREMENT NOT NULL, text LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("INSERT INTO contenu (text) VALUES ('Ancienne élève de Violette Soret, animatrice et juge de divers concours d’art floral en Île-de-France.\n\nJ’ai ouvert mon atelier à Grandfontaine, en Alsace, en novembre 2019 avec le désir de faire connaître l’art floral occidental. J’anime une fois par mois un atelier associatif bénévole pour transmettre cette discipline.')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contenu');
    }
}
