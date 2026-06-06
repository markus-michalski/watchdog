<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606114454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alert_states (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, current_status VARCHAR(255) NOT NULL, last_alert_sent_at DATETIME DEFAULT NULL, last_status_change DATETIME NOT NULL, fail_count INTEGER NOT NULL, check_id INTEGER NOT NULL, CONSTRAINT FK_6F0164A709385E7 FOREIGN KEY (check_id) REFERENCES site_checks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6F0164A709385E7 ON alert_states (check_id)');
        $this->addSql('CREATE TABLE check_results (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, status VARCHAR(255) NOT NULL, status_code INTEGER DEFAULT NULL, response_time_ms INTEGER DEFAULT NULL, message VARCHAR(1024) DEFAULT NULL, checked_at DATETIME NOT NULL, check_id INTEGER NOT NULL, CONSTRAINT FK_D9BFAA94709385E7 FOREIGN KEY (check_id) REFERENCES site_checks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D9BFAA94709385E7 ON check_results (check_id)');
        $this->addSql('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, site_id INTEGER NOT NULL, CONSTRAINT FK_33401573F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_33401573F6BD1646 ON contacts (site_id)');
        $this->addSql('CREATE TABLE site_checks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(64) NOT NULL, config CLOB NOT NULL, is_active BOOLEAN NOT NULL, site_id INTEGER NOT NULL, CONSTRAINT FK_EF22AE26F6BD1646 FOREIGN KEY (site_id) REFERENCES sites (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EF22AE26F6BD1646 ON site_checks (site_id)');
        $this->addSql('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(2048) NOT NULL, basic_auth_user VARCHAR(255) DEFAULT NULL, basic_auth_password VARCHAR(512) DEFAULT NULL, is_active BOOLEAN NOT NULL, check_interval_minutes INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE alert_states');
        $this->addSql('DROP TABLE check_results');
        $this->addSql('DROP TABLE contacts');
        $this->addSql('DROP TABLE site_checks');
        $this->addSql('DROP TABLE sites');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
