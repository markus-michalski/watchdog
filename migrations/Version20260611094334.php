<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611094334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make sites.url nullable to support server-level monitoring entries without a URL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__sites AS SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM sites');
        $this->addSql('DROP TABLE sites');
        $this->addSql('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(2048) DEFAULT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO sites (id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at) SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM __temp__sites');
        $this->addSql('DROP TABLE __temp__sites');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__sites AS SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM sites');
        $this->addSql('DROP TABLE sites');
        $this->addSql('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(2048) NOT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO sites (id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at) SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM __temp__sites');
        $this->addSql('DROP TABLE __temp__sites');
    }
}
