<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\HttpCheck;
use App\Entity\Site;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsHttp(): void
    {
        $httpCheck = new HttpCheck(new MockHttpClient());

        self::assertSame('http', $httpCheck->getType());
    }

    #[Test]
    public function testRunWithExpected200ReturnsOkStatus(): void
    {
        $httpCheck = new HttpCheck(new MockHttpClient(new MockResponse('', ['http_code' => 200])));
        $check = $this->createSiteCheck(['expected_status_codes' => [200]]);

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function testRunWith500ReturnsFailStatusWithStatusCodeInMessage(): void
    {
        $httpCheck = new HttpCheck(new MockHttpClient(new MockResponse('', ['http_code' => 500])));
        $check = $this->createSiteCheck(['expected_status_codes' => [200]]);

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('500', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithUnexpectedStatusCodeReturnsFailStatus(): void
    {
        $httpCheck = new HttpCheck(new MockHttpClient(new MockResponse('', ['http_code' => 404])));
        $check = $this->createSiteCheck(['expected_status_codes' => [200]]);

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('404', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithNetworkExceptionReturnsFailStatus(): void
    {
        $httpCheck = new HttpCheck(new MockHttpClient(static function (): never {
            throw new TransportException('Connection refused');
        }));
        $check = $this->createSiteCheck(['expected_status_codes' => [200]]);

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection refused', (string) $result->getMessage());
    }

    private function createSiteCheck(array $config): SiteCheck
    {
        $site = new Site();
        $site->setName('Example');
        $site->setUrl('https://example.test');

        $check = new SiteCheck();
        $check->setSite($site);
        $check->setType('http');
        $check->setConfig($config);

        return $check;
    }
}
