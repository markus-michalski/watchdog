<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\DockerExecCheck;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DockerExecCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsDockerExec(): void
    {
        self::assertSame('docker_exec', (new DockerExecCheck())->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('Docker Exec', (new DockerExecCheck())->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = (new DockerExecCheck())->getDefaultConfig();

        self::assertArrayHasKey('container_name', $defaults);
        self::assertSame('', $defaults['container_name']);
        self::assertArrayHasKey('command', $defaults);
        self::assertSame('', $defaults['command']);
        self::assertArrayHasKey('timeout', $defaults);
        self::assertSame(10, $defaults['timeout']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllRequiredFields(): void
    {
        $schema = (new DockerExecCheck())->getConfigSchema();
        $names = array_column($schema, 'name');

        self::assertContains('container_name', $names);
        self::assertContains('command', $names);
        self::assertContains('timeout', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksContainerNameAsRequired(): void
    {
        $schema = (new DockerExecCheck())->getConfigSchema();
        $field = $this->findField($schema, 'container_name');

        self::assertTrue($field['required']);
    }

    #[Test]
    public function testGetConfigSchemaMarksCommandAsRequired(): void
    {
        $schema = (new DockerExecCheck())->getConfigSchema();
        $field = $this->findField($schema, 'command');

        self::assertTrue($field['required']);
    }

    #[Test]
    public function testRunWithEmptyContainerNameReturnsUnknown(): void
    {
        $check = $this->createSiteCheck(containerName: '', command: 'true');

        $result = (new DockerExecCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('container_name', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithMissingContainerNameKeyReturnsUnknown(): void
    {
        $check = $this->createSiteCheckRaw(['command' => 'true']);

        $result = (new DockerExecCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    #[Test]
    public function testRunWithEmptyCommandReturnsUnknown(): void
    {
        $check = $this->createSiteCheck(containerName: 'my-app', command: '');

        $result = (new DockerExecCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('command', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithMissingCommandKeyReturnsUnknown(): void
    {
        $check = $this->createSiteCheckRaw(['container_name' => 'my-app']);

        $result = (new DockerExecCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    #[Test]
    public function testRunWithMissingDockerSocketReturnsUnknown(): void
    {
        if (file_exists('/var/run/docker.sock')) {
            self::markTestSkipped('Docker socket exists on this host; cannot test the missing-socket branch.');
        }

        $check = $this->createSiteCheck(containerName: 'my-app', command: 'true');

        $result = (new DockerExecCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('Docker socket not available', (string) $result->getMessage());
    }

    // --- helpers ---

    private function createSiteCheck(string $containerName, string $command, int $timeout = 10): SiteCheck
    {
        return $this->createSiteCheckRaw([
            'container_name' => $containerName,
            'command' => $command,
            'timeout' => $timeout,
        ]);
    }

    /** @param array<string, mixed> $config */
    private function createSiteCheckRaw(array $config): SiteCheck
    {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('docker_exec');
        $check->setConfig($config);

        return $check;
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     * @return array<string, mixed>
     */
    private function findField(array $schema, string $name): array
    {
        foreach ($schema as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
        }

        throw new \LogicException(sprintf('Field "%s" not found in config schema.', $name));
    }
}
