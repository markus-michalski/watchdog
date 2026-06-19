<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Check\CheckInterface;
use App\Check\CheckRegistry;
use App\Twig\CheckLabelExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SiteCheckTest extends TestCase
{
    #[Test]
    public function checkLabelFilterDelegatesToRegistry(): void
    {
        $check = $this->createStub(CheckInterface::class);
        $check->method('getType')->willReturn('dns');
        $check->method('getLabel')->willReturn('DNS');

        $registry = $this->createStub(CheckRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($check);

        $extension = new CheckLabelExtension($registry);

        self::assertSame('DNS', $extension->resolveLabel('dns'));
    }

    #[Test]
    public function checkLabelFilterFallsBackToTitleCaseForUnknownTypes(): void
    {
        $registry = $this->createStub(CheckRegistry::class);
        $registry->method('has')->willReturn(false);

        $extension = new CheckLabelExtension($registry);

        self::assertSame('Some New Check', $extension->resolveLabel('some_new_check'));
        self::assertSame('Unknown Type', $extension->resolveLabel('unknown_type'));
    }

    #[Test]
    public function checkLabelFilterExposesCorrectFilterName(): void
    {
        $registry = $this->createStub(CheckRegistry::class);
        $extension = new CheckLabelExtension($registry);

        $filterNames = array_map(
            static fn ($f) => $f->getName(),
            $extension->getFilters()
        );

        self::assertContains('check_label', $filterNames);
    }
}
