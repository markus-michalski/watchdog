<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class SiteCheckControllerTest extends AbstractControllerTestCase
{
    #[Test]
    public function newReturns404ForUnknownClient(): void
    {
        $this->client->request('GET', '/clients/999/checks/new');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
