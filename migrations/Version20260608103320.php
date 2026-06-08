<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608103320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor contacts: ManyToOne → ManyToMany via site_contacts join table';
    }

    public function up(Schema $schema): void
    {
        // Create join table
        $this->addSql('CREATE TABLE site_contacts (site_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, PRIMARY KEY (site_id, contact_id), CONSTRAINT FK_3F0DAAA9F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3F0DAAA9E7A1254A FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3F0DAAA9F6BD1646 ON site_contacts (site_id)');
        $this->addSql('CREATE INDEX IDX_3F0DAAA9E7A1254A ON site_contacts (contact_id)');

        // Migrate existing site_id FK data into join table
        $this->addSql('INSERT INTO site_contacts (site_id, contact_id) SELECT site_id, id FROM contacts');

        // SQLite: recreate contacts without site_id column
        $this->addSql('CREATE TEMPORARY TABLE contacts_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO contacts_tmp SELECT id, name, email FROM contacts');
        $this->addSql('DROP TABLE contacts');
        $this->addSql('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO contacts SELECT id, name, email FROM contacts_tmp');
        $this->addSql('DROP TABLE contacts_tmp');
    }

    public function down(Schema $schema): void
    {
        // SQLite: recreate contacts with site_id (nullable for safety)
        $this->addSql('CREATE TEMPORARY TABLE contacts_tmp (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO contacts_tmp SELECT id, name, email FROM contacts');
        $this->addSql('DROP TABLE contacts');
        $this->addSql('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, site_id INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO contacts (id, name, email, site_id) SELECT t.id, t.name, t.email, sc.site_id FROM contacts_tmp t LEFT JOIN site_contacts sc ON sc.contact_id = t.id');
        $this->addSql('DROP TABLE contacts_tmp');
        $this->addSql('DROP TABLE site_contacts');
    }
}
