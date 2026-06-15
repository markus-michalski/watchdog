<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615133100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add agents table and runner/agent_id fields to site_checks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agents (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, token_hash VARCHAR(64) NOT NULL, last_seen_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9596AB6EB3BC57DA ON agents (token_hash)');

        // Recreate site_checks with runner + agent_id (SQLite requires temp-table for FK constraints)
        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql("CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, client_id INTEGER NOT NULL, check_interval_minutes INTEGER NOT NULL, run_at_time VARCHAR(5) DEFAULT NULL, retention_days INTEGER DEFAULT NULL, runner VARCHAR(255) NOT NULL, agent_id INTEGER DEFAULT NULL, CONSTRAINT FK_EF22AE2619EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EF22AE263414710B FOREIGN KEY (agent_id) REFERENCES agents (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)");
        $this->addSql("INSERT INTO site_checks (id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days, runner) SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days, 'dashboard' FROM __temp__site_checks");
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE2619EB6921 ON site_checks (client_id)');
        $this->addSql('CREATE INDEX IDX_EF22AE263414710B ON site_checks (agent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, client_id INTEGER NOT NULL, check_interval_minutes INTEGER NOT NULL, run_at_time VARCHAR(5) DEFAULT NULL, retention_days INTEGER DEFAULT NULL, CONSTRAINT FK_EF22AE2619EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO site_checks (id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days) SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days FROM __temp__site_checks');
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE2619EB6921 ON site_checks (client_id)');
        $this->addSql('DROP TABLE agents');
    }
}
