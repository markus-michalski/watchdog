<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\HttpContentCheck;
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

final class HttpContentCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsHttpContent(): void
    {
        self::assertSame('http_content', $this->buildCheck(new MockHttpClient())->getType());
    }

    #[Test]
    public function testRunWithNoClientUrlIdReturnsUnknown(): void
    {
        $result = $this->buildCheck(new MockHttpClient())->run($this->createSiteCheck([]));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('No URL', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithUnknownClientUrlIdReturnsUnknown(): void
    {
        $result = $this->buildCheck(new MockHttpClient(), null)->run($this->createSiteCheck(['client_url_id' => 999]));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('999', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithExpectedStringFoundReturnsOk(): void
    {
        $check = $this->createSiteCheck([
            'client_url_id' => 1,
            'expected_string' => 'Welcome to MyApp',
        ]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html>Welcome to MyApp</html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithExpectedStringNotFoundReturnsFail(): void
    {
        $check = $this->createSiteCheck([
            'client_url_id' => 1,
            'expected_string' => 'Welcome to MyApp',
        ]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html>Under Maintenance</html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Welcome to MyApp', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithForbiddenStringAbsentReturnsOk(): void
    {
        $check = $this->createSiteCheck([
            'client_url_id' => 1,
            'forbidden_string' => 'Under Maintenance',
        ]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html>Welcome to MyApp</html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithForbiddenStringFoundReturnsFail(): void
    {
        $check = $this->createSiteCheck([
            'client_url_id' => 1,
            'forbidden_string' => 'Under Maintenance',
        ]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html>Under Maintenance</html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Under Maintenance', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithBothStringChecksPassingReturnsOk(): void
    {
        $check = $this->createSiteCheck([
            'client_url_id' => 1,
            'expected_string' => 'Dashboard',
            'forbidden_string' => 'Maintenance',
        ]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html>Dashboard</html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithExpectedStringCheckedBeforeForbiddenString(): void
    {
        // expected_string missing — should fail with expected-string message, not forbidden-string
        $check = $this->createSiteCheck([
            'client_url_id' => 1,
            'expected_string' => 'Dashboard',
            'forbidden_string' => 'Maintenance',
        ]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html>Maintenance</html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Expected string not found', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithNoStringConfigReturnsOk(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html>Whatever</html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithNetworkExceptionReturnsFail(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1]);
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

    #[Test]
    public function testRunSetsResponseTimeMs(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1]);
        $httpCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('<html></html>')),
            $this->makeClientUrl('https://example.test'),
        );

        $result = $httpCheck->run($check);

        self::assertGreaterThanOrEqual(0, $result->getResponseTimeMs());
    }

    // --- helpers ---

    private function buildCheck(MockHttpClient $httpClient, ?ClientUrl $clientUrl = null): HttpContentCheck
    {
        $repo = $this->createStub(ClientUrlRepository::class);
        $repo->method('find')->willReturn($clientUrl);

        return new HttpContentCheck($httpClient, $repo);
    }

    /** @param array<string, mixed> $config */
    private function createSiteCheck(array $config): SiteCheck
    {
        $client = new Client();
        $client->setName('Example');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('http_content');
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
