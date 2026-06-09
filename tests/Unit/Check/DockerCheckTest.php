<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\DockerCheck;
use App\Entity\Site;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DockerCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsDocker(): void
    {
        self::assertSame('docker', (new DockerCheck())->getType());
    }

    #[Test]
    public function testGetLabelReturnsDockerContainerHealth(): void
    {
        self::assertSame('Docker Container Health', (new DockerCheck())->getLabel());
    }

    #[Test]
    public function testRunWithEmptyContainerNameReturnsUnknownStatus(): void
    {
        $check = $this->createSiteCheck(['container_name' => '']);

        $result = (new DockerCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertSame('No container_name configured', $result->getMessage());
    }

    #[Test]
    public function testRunWithMissingDockerSocketReturnsUnknownStatus(): void
    {
        if (file_exists('/var/run/docker.sock')) {
            self::markTestSkipped('Docker socket exists on this host; cannot test the missing-socket branch.');
        }

        $check = $this->createSiteCheck(['container_name' => 'my-app']);

        $result = (new DockerCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('Docker socket not available', (string) $result->getMessage());
    }

    private function createSiteCheck(array $config): SiteCheck
    {
        $check = new SiteCheck();
        $check->setSite(new Site());
        $check->setType('docker');
        $check->setConfig($config);

        return $check;
    }
}
