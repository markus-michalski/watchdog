<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move check_interval_minutes from sites to site_checks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_checks ADD COLUMN check_interval_minutes INTEGER NOT NULL DEFAULT 5');
        $this->addSql('ALTER TABLE sites DROP COLUMN check_interval_minutes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sites ADD COLUMN check_interval_minutes INTEGER NOT NULL DEFAULT 5');
        $this->addSql('ALTER TABLE site_checks DROP COLUMN check_interval_minutes');
    }
}
