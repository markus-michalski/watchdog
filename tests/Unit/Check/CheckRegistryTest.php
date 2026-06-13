<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\CheckInterface;
use App\Check\CheckRegistry;
use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckRegistryTest extends TestCase
{
    #[Test]
    public function testGetReturnsRegisteredCheckInstance(): void
    {
        $check = new StubCheck('http', 'HTTP Reachability');
        $registry = new CheckRegistry([$check]);

        self::assertSame($check, $registry->get('http'));
    }

    #[Test]
    public function testGetWithUnknownTypeThrowsException(): void
    {
        $registry = new CheckRegistry([new StubCheck('http', 'HTTP Reachability')]);

        $this->expectException(\InvalidArgumentException::class);

        $registry->get('does-not-exist');
    }

    #[Test]
    public function testHasReturnsTrueForRegisteredType(): void
    {
        $registry = new CheckRegistry([new StubCheck('http', 'HTTP Reachability')]);

        self::assertTrue($registry->has('http'));
    }

    #[Test]
    public function testHasReturnsFalseForUnknownType(): void
    {
        $registry = new CheckRegistry([new StubCheck('http', 'HTTP Reachability')]);

        self::assertFalse($registry->has('docker'));
    }

    #[Test]
    public function testAllReturnsAllRegisteredChecksKeyedByType(): void
    {
        $http = new StubCheck('http', 'HTTP Reachability');
        $docker = new StubCheck('docker', 'Docker Container Health');
        $registry = new CheckRegistry([$http, $docker]);

        self::assertSame(['http' => $http, 'docker' => $docker], $registry->all());
    }

    #[Test]
    public function testGetTypeChoicesReturnsLabelToTypeMap(): void
    {
        $registry = new CheckRegistry([
            new StubCheck('http', 'HTTP Reachability'),
            new StubCheck('docker', 'Docker Container Health'),
        ]);

        self::assertSame([
            'HTTP Reachability' => 'http',
            'Docker Container Health' => 'docker',
        ], $registry->getTypeChoices());
    }

    #[Test]
    public function testGetAllSchemasReturnsTypeToSchemaMap(): void
    {
        $schema = [
            [
                'name' => 'field',
                'label' => 'Field',
                'type' => 'text',
                'required' => false,
                'default' => '',
                'placeholder' => '',
                'help' => '',
            ],
        ];
        $registry = new CheckRegistry([new StubCheck('http', 'HTTP Reachability', $schema)]);

        self::assertSame(['http' => $schema], $registry->getAllSchemas());
    }
}

final class StubCheck implements CheckInterface
{
    public function __construct(
        private readonly string $type,
        private readonly string $label,
        private readonly array $schema = [],
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $result = new CheckResult();
        $result->setCheck($check);
        $result->setStatus(CheckStatus::Unknown);

        return $result;
    }

    public function getDefaultConfig(): array
    {
        return [];
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getConfigSchema(): array
    {
        return $this->schema;
    }

    public function getEmailTargetLabel(): ?string
    {
        return null;
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        return null;
    }
}
