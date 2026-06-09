<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\Test;

final class LegalControllerTest extends AbstractControllerTestCase
{
    #[Test]
    public function impressumIsAccessible(): void
    {
        $this->client->request('GET', '/impressum');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function datenschutzIsAccessible(): void
    {
        $this->client->request('GET', '/datenschutz');

        self::assertResponseIsSuccessful();
    }
}
