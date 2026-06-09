<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\Test;

final class ContactControllerTest extends AbstractControllerTestCase
{
    #[Test]
    public function indexIsAccessible(): void
    {
        $this->client->request('GET', '/contacts');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function newRendersForm(): void
    {
        $crawler = $this->client->request('GET', '/contacts/new');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('form')->count());
    }
}
