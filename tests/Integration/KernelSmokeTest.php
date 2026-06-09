<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Check\CheckRegistry;
use App\Check\DockerCheck;
use App\Check\HttpCheck;
use App\MessageHandler\MailNotificationHandler;
use App\MessageHandler\RunSiteChecksHandler;
use App\Service\AlertService;
use App\Service\CheckRunner;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelSmokeTest extends KernelTestCase
{
    #[Test]
    public function kernelBoots(): void
    {
        self::bootKernel();

        self::assertNotNull(self::$kernel);
    }

    #[Test]
    public function containerCompiles(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertNotNull($container);
    }

    /**
     * @param class-string $serviceClass
     */
    #[Test]
    #[DataProvider('criticalServiceProvider')]
    public function criticalServiceIsWireable(string $serviceClass): void
    {
        self::bootKernel();

        $service = self::getContainer()->get($serviceClass);

        self::assertInstanceOf($serviceClass, $service);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function criticalServiceProvider(): iterable
    {
        yield 'CheckRunner' => [CheckRunner::class];
        yield 'AlertService' => [AlertService::class];
        yield 'CheckRegistry' => [CheckRegistry::class];
        yield 'HttpCheck' => [HttpCheck::class];
        yield 'DockerCheck' => [DockerCheck::class];
        yield 'MailNotificationHandler' => [MailNotificationHandler::class];
        yield 'RunSiteChecksHandler' => [RunSiteChecksHandler::class];
    }

    #[Test]
    public function doctrineMappingIsValid(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();

        $validator = new SchemaValidator($em);
        $errors = $validator->validateMapping();

        self::assertEmpty($errors, implode("\n", array_map(
            static fn (array $entityErrors): string => implode(', ', $entityErrors),
            $errors,
        )));
    }
}
