<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612110100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix FK constraint declarations on site_checks, client_contacts, client_urls to match ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, client_id INTEGER NOT NULL, check_interval_minutes INTEGER NOT NULL, run_at_time VARCHAR(5) DEFAULT NULL, retention_days INTEGER DEFAULT NULL, CONSTRAINT FK_EF22AE2619EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO site_checks (id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days) SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days FROM __temp__site_checks');
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE2619EB6921 ON site_checks (client_id)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__client_contacts AS SELECT client_id, contact_id FROM client_contacts');
        $this->addSql('DROP TABLE client_contacts');
        $this->addSql('CREATE TABLE client_contacts (client_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, PRIMARY KEY (client_id, contact_id), CONSTRAINT FK_B55B9C6419EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B55B9C64E7A1254A FOREIGN KEY (contact_id) REFERENCES contacts (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO client_contacts (client_id, contact_id) SELECT client_id, contact_id FROM __temp__client_contacts');
        $this->addSql('DROP TABLE __temp__client_contacts');
        $this->addSql('CREATE INDEX IDX_1DA625B619EB6921 ON client_contacts (client_id)');
        $this->addSql('CREATE INDEX IDX_1DA625B6E7A1254A ON client_contacts (contact_id)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__client_urls AS SELECT id, client_id, url, label, basic_auth_user, basic_auth_password FROM client_urls');
        $this->addSql('DROP TABLE client_urls');
        $this->addSql('CREATE TABLE client_urls (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, url VARCHAR(2048) NOT NULL, label VARCHAR(255) DEFAULT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, CONSTRAINT FK_FB08F4C319EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO client_urls (id, client_id, url, label, basic_auth_user, basic_auth_password) SELECT id, client_id, url, label, basic_auth_user, basic_auth_password FROM __temp__client_urls');
        $this->addSql('DROP TABLE __temp__client_urls');
        $this->addSql('CREATE INDEX IDX_FB08F4C319EB6921 ON client_urls (client_id)');
    }

    public function down(Schema $schema): void
    {
        // Reverting to the state after Version20260612110000 — no functional change needed
    }
}
