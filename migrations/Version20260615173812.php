<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615173812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add run_now flag to site_checks for on-demand agent execution';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_checks ADD COLUMN run_now BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__site_checks AS SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days, runner, agent_id FROM site_checks');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, client_id INTEGER NOT NULL, check_interval_minutes INTEGER NOT NULL, run_at_time VARCHAR(5) DEFAULT NULL, retention_days INTEGER DEFAULT NULL, runner VARCHAR(16) NOT NULL, agent_id INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO site_checks (id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days, runner, agent_id) SELECT id, type, config, is_active, client_id, check_interval_minutes, run_at_time, retention_days, runner, agent_id FROM __temp__site_checks');
        $this->addSql('DROP TABLE __temp__site_checks');
    }
}
