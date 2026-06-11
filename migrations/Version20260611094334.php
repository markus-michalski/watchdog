<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611094334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE site_contacts (site_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, PRIMARY KEY (site_id, contact_id), CONSTRAINT FK_3F0DAAA9F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3F0DAAA9E7A1254A FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3F0DAAA9F6BD1646 ON site_contacts (site_id)');
        $this->addSql('CREATE INDEX IDX_3F0DAAA9E7A1254A ON site_contacts (contact_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__contacts AS SELECT id, name, email FROM contacts');
        $this->addSql('DROP TABLE contacts');
        $this->addSql('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO contacts (id, name, email) SELECT id, name, email FROM __temp__contacts');
        $this->addSql('DROP TABLE __temp__contacts');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_33401573E7927C74 ON contacts (email)');
        $this->addSql('ALTER TABLE site_checks ADD COLUMN check_interval_minutes INTEGER NOT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__sites AS SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM sites');
        $this->addSql('DROP TABLE sites');
        $this->addSql('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(2048) DEFAULT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO sites (id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at) SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM __temp__sites');
        $this->addSql('DROP TABLE __temp__sites');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE site_contacts');
        $this->addSql('CREATE TEMPORARY TABLE __temp__contacts AS SELECT id, name, email FROM contacts');
        $this->addSql('DROP TABLE contacts');
        $this->addSql('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, site_id INTEGER NOT NULL, CONSTRAINT FK_33401573F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO contacts (id, name, email) SELECT id, name, email FROM __temp__contacts');
        $this->addSql('DROP TABLE __temp__contacts');
        $this->addSql('CREATE INDEX IDX_33401573F6BD1646 ON contacts (site_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, site_id FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, site_id INTEGER NOT NULL, CONSTRAINT FK_EF22AE26F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO site_checks (id, type, config, is_active, site_id) SELECT id, type, config, is_active, site_id FROM __temp__site_checks');
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE26F6BD1646 ON site_checks (site_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__sites AS SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM sites');
        $this->addSql('DROP TABLE sites');
        $this->addSql('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(2048) NOT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, check_interval_minutes INTEGER NOT NULL)');
        $this->addSql('INSERT INTO sites (id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at) SELECT id, name, url, basic_auth_user, basic_auth_password, is_active, created_at, updated_at FROM __temp__sites');
        $this->addSql('DROP TABLE __temp__sites');
    }
}
