<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add run_at_time to site_checks for daily time-anchored scheduling';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_checks ADD COLUMN run_at_time VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_checks DROP COLUMN run_at_time');
    }
}
