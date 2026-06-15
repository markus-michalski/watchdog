<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615154109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tighten runner column length from VARCHAR(255) to VARCHAR(16)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__site_checks AS
            SELECT
              id,
              type,
              config,
              is_active,
              client_id,
              check_interval_minutes,
              run_at_time,
              retention_days,
              runner,
              agent_id
            FROM
              site_checks
        SQL);
        $this->addSql('DROP TABLE site_checks');
        $this->addSql(<<<'SQL'
            CREATE TABLE site_checks (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              type VARCHAR(64) NOT NULL,
              config CLOB NOT NULL,
              is_active BOOLEAN NOT NULL,
              client_id INTEGER NOT NULL,
              check_interval_minutes INTEGER NOT NULL,
              run_at_time VARCHAR(5) DEFAULT NULL,
              retention_days INTEGER DEFAULT NULL,
              runner VARCHAR(16) NOT NULL,
              agent_id INTEGER DEFAULT NULL,
              CONSTRAINT FK_EF22AE2619EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON
              UPDATE
                NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_EF22AE263414710B FOREIGN KEY (agent_id) REFERENCES agents (id) ON
              UPDATE
                NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO site_checks (
              id, type, config, is_active, client_id,
              check_interval_minutes, run_at_time,
              retention_days, runner, agent_id
            )
            SELECT
              id,
              type,
              config,
              is_active,
              client_id,
              check_interval_minutes,
              run_at_time,
              retention_days,
              runner,
              agent_id
            FROM
              __temp__site_checks
        SQL);
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE263414710B ON site_checks (agent_id)');
        $this->addSql('CREATE INDEX IDX_EF22AE2619EB6921 ON site_checks (client_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__site_checks AS
            SELECT
              id,
              type,
              config,
              is_active,
              check_interval_minutes,
              run_at_time,
              retention_days,
              runner,
              client_id,
              agent_id
            FROM
              site_checks
        SQL);
        $this->addSql('DROP TABLE site_checks');
        $this->addSql(<<<'SQL'
            CREATE TABLE site_checks (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              type VARCHAR(64) NOT NULL,
              config CLOB NOT NULL,
              is_active BOOLEAN NOT NULL,
              check_interval_minutes INTEGER NOT NULL,
              run_at_time VARCHAR(5) DEFAULT NULL,
              retention_days INTEGER DEFAULT NULL,
              runner VARCHAR(255) NOT NULL,
              client_id INTEGER NOT NULL,
              agent_id INTEGER DEFAULT NULL,
              CONSTRAINT FK_EF22AE2619EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_EF22AE263414710B FOREIGN KEY (agent_id) REFERENCES agents (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO site_checks (
              id, type, config, is_active, check_interval_minutes,
              run_at_time, retention_days, runner,
              client_id, agent_id
            )
            SELECT
              id,
              type,
              config,
              is_active,
              check_interval_minutes,
              run_at_time,
              retention_days,
              runner,
              client_id,
              agent_id
            FROM
              __temp__site_checks
        SQL);
        $this->addSql('DROP TABLE __temp__site_checks');
        $this->addSql('CREATE INDEX IDX_EF22AE2619EB6921 ON site_checks (client_id)');
        $this->addSql('CREATE INDEX IDX_EF22AE263414710B ON site_checks (agent_id)');
    }
}
