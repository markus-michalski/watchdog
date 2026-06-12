<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace sites with clients + client_urls; rename site_id → client_id in site_checks';
    }

    public function up(Schema $schema): void
    {
        // Create clients table (replaces sites — no url/basicAuth, those go to client_urls)
        $this->addSql('CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');

        // Create client_urls table (0..n URLs per client)
        $this->addSql('CREATE TABLE client_urls (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, url VARCHAR(2048) NOT NULL, label VARCHAR(255) DEFAULT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, CONSTRAINT FK_C1D3A07519EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C1D3A07519EB6921 ON client_urls (client_id)');

        // Create client_contacts join table (replaces site_contacts)
        $this->addSql('CREATE TABLE client_contacts (client_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, PRIMARY KEY (client_id, contact_id), CONSTRAINT FK_B55B9C6419EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B55B9C64E7A1254A FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B55B9C6419EB6921 ON client_contacts (client_id)');
        $this->addSql('CREATE INDEX IDX_B55B9C64E7A1254A ON client_contacts (contact_id)');

        // Recreate site_checks with client_id instead of site_id (SQLite cannot rename columns directly)
        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, check_interval_minutes, run_at_time, retention_days FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, client_id INTEGER NOT NULL, check_interval_minutes INTEGER NOT NULL, run_at_time VARCHAR(5) DEFAULT NULL, retention_days INTEGER DEFAULT NULL, CONSTRAINT FK_EF22AE2619EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EF22AE2619EB6921 ON site_checks (client_id)');
        // No data to restore — user will recreate entries manually
        $this->addSql('DROP TABLE __temp__site_checks');

        // Drop old tables
        $this->addSql('DROP TABLE site_contacts');
        $this->addSql('DROP TABLE sites');
    }

    public function down(Schema $schema): void
    {
        // Restore sites table
        $this->addSql('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(2048) DEFAULT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');

        // Restore site_contacts join table
        $this->addSql('CREATE TABLE site_contacts (site_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, PRIMARY KEY (site_id, contact_id), CONSTRAINT FK_3F0DAAA9F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3F0DAAA9E7A1254A FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3F0DAAA9F6BD1646 ON site_contacts (site_id)');
        $this->addSql('CREATE INDEX IDX_3F0DAAA9E7A1254A ON site_contacts (contact_id)');

        // Recreate site_checks with site_id instead of client_id
        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, check_interval_minutes, run_at_time, retention_days FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, site_id INTEGER NOT NULL, check_interval_minutes INTEGER NOT NULL, run_at_time VARCHAR(5) DEFAULT NULL, retention_days INTEGER DEFAULT NULL, CONSTRAINT FK_EF22AE26F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EF22AE26F6BD1646 ON site_checks (site_id)');
        $this->addSql('DROP TABLE __temp__site_checks');

        // Drop new tables
        $this->addSql('DROP TABLE client_contacts');
        $this->addSql('DROP TABLE client_urls');
        $this->addSql('DROP TABLE clients');
    }
}
