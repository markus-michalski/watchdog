<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class SiteControllerTest extends AbstractControllerTestCase
{
    #[Test]
    public function indexIsAccessible(): void
    {
        $this->client->request('GET', '/sites');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function newRendersForm(): void
    {
        $crawler = $this->client->request('GET', '/sites/new');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('form')->count());
    }

    #[Test]
    public function showReturns404ForUnknownSite(): void
    {
        $this->client->request('GET', '/sites/999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
