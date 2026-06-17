<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617170416 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename cron_log check type to log_file';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE site_checks SET type = 'log_file' WHERE type = 'cron_log'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE site_checks SET type = 'cron_log' WHERE type = 'log_file'");
    }
}
