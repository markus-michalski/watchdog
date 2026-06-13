<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\HttpCheck;
use App\Entity\Client;
use App\Entity\ClientUrl;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Repository\ClientUrlRepository;
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
        $httpCheck = $this->buildCheck(new MockHttpClient());

        self::assertSame('http', $httpCheck->getType());
    }

    #[Test]
    public function testRunWithNoClientUrlIdReturnsUnknown(): void
    {
        $check = $this->createSiteCheck([]);
        $result = $this->buildCheck(new MockHttpClient())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    #[Test]
    public function testRunWithUnknownClientUrlIdReturnsUnknown(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 999]);
        // repo returns null — URL was deleted
        $result = $this->buildCheck(new MockHttpClient(), null)->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('999', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithExpected200ReturnsOkStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'expected_status_codes' => [200]]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 200])),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function testRunWith500ReturnsFailStatusWithStatusCodeInMessage(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'expected_status_codes' => [200]]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 500])),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('500', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithUnexpectedStatusCodeReturnsFailStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'expected_status_codes' => [200]]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 404])),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('404', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithNetworkExceptionReturnsFailStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'expected_status_codes' => [200]]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(static function (): never {
                throw new TransportException('Connection refused');
            }),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection refused', (string) $result->getMessage());
    }

    // --- helpers ---

    private function buildCheck(MockHttpClient $httpClient, ?ClientUrl $clientUrl = null): HttpCheck
    {
        $repo = $this->createStub(ClientUrlRepository::class);
        $repo->method('find')->willReturn($clientUrl);

        return new HttpCheck($httpClient, $repo);
    }

    /** @param array<string, mixed> $config */
    private function createSiteCheck(array $config): SiteCheck
    {
        $client = new Client();
        $client->setName('Example');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('http');
        $check->setConfig($config);

        return $check;
    }

    private function makeClientUrl(string $url): ClientUrl
    {
        $client = new Client();
        $client->setName('Example');

        $clientUrl = new ClientUrl();
        $clientUrl->setClient($client);
        $clientUrl->setUrl($url);

        return $clientUrl;
    }
}
