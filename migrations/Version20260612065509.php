<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612065509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add retention_days to site_checks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_checks ADD COLUMN retention_days INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site_checks DROP COLUMN retention_days');
    }
}
