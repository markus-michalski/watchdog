<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608124247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index on contacts.email; fix site_checks column order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_33401573E7927C74 ON contacts (email)');

        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, site_id, check_interval_minutes FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, site_id INTEGER NOT NULL, check_interval_minutes INTEGER NOT NULL, CONSTRAINT FK_EF22AE26F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO site_checks (id, type, config, is_active, site_id, check_interval_minutes) SELECT id, type, config, is_active, site_id, check_interval_minutes FROM __temp__site_checks');
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE26F6BD1646 ON site_checks (site_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_33401573E7927C74');

        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, check_interval_minutes, site_id FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, check_interval_minutes INTEGER DEFAULT 5 NOT NULL, site_id INTEGER NOT NULL, CONSTRAINT FK_EF22AE26F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO site_checks (id, type, config, is_active, check_interval_minutes, site_id) SELECT id, type, config, is_active, check_interval_minutes, site_id FROM __temp__site_checks');
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE26F6BD1646 ON site_checks (site_id)');
    }
}
