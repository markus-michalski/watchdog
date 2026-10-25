<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\FileReachabilityCheck;
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

final class FileReachabilityCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsFileReachability(): void
    {
        $check = $this->buildCheck(new MockHttpClient());

        self::assertSame('file_reachability', $check->getType());
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
    public function testRunWithReachableFileReturnsOkStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-length' => '54321', 'content-type' => 'image/png'],
            ])),
            $this->makeClientUrl('https://example.test/logo.png'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function testRunWith404ReturnsFailStatusWithStatusCodeInMessage(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 404])),
            $this->makeClientUrl('https://example.test/logo.png'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('404', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithEmptyFileBelowMinBytesReturnsFailStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 1]);
        // HEAD reports 0, the ranged GET confirms the file really is empty
        $fileCheck = $this->buildCheck(
            new MockHttpClient([
                new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]),
                new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]),
            ]),
            $this->makeClientUrl('https://example.test/logo.png'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('0 bytes', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithContentLengthAtOrAboveMinBytesReturnsOkStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 500]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-length' => '500'],
            ])),
            $this->makeClientUrl('https://example.test/logo.png'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithUnexpectedContentTypeReturnsFailStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'expected_content_type' => 'image/']);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-length' => '100', 'content-type' => 'text/html'],
            ])),
            $this->makeClientUrl('https://example.test/logo.png'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('text/html', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithMatchingContentTypeReturnsOkStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'expected_content_type' => 'image/']);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-length' => '100', 'content-type' => 'image/jpeg'],
            ])),
            $this->makeClientUrl('https://example.test/logo.jpg'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithNetworkExceptionReturnsFailStatus(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(static function (): never {
                throw new TransportException('Connection refused');
            }),
            $this->makeClientUrl('https://example.test/logo.png'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection refused', (string) $result->getMessage());
    }

    #[Test]
    public function testRunSendsHeadRequest(): void
    {
        $response = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '100']]);
        $fileCheck = $this->buildCheck(new MockHttpClient($response), $this->makeClientUrl('https://example.test/a.png'));

        $fileCheck->run($this->createSiteCheck(['client_url_id' => 1]));

        self::assertSame('HEAD', $response->getRequestMethod());
    }

    #[Test]
    public function testRunWithoutContentLengthAndMinBytesReturnsWarn(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 1]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-type' => 'image/png']])),
            $this->makeClientUrl('https://example.test/a.png'),
        );

        $result = $fileCheck->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
        self::assertStringContainsString('Content-Length', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithoutContentLengthAndMinBytesZeroReturnsOk(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 0]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 200])),
            $this->makeClientUrl('https://example.test/a.png'),
        );

        self::assertSame(CheckStatus::Ok, $fileCheck->run($check)->getStatus());
    }

    #[Test]
    public function testRunWithMinBytesZeroIgnoresEmptyContentLength(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 0]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']])),
            $this->makeClientUrl('https://example.test/a.png'),
        );

        self::assertSame(CheckStatus::Ok, $fileCheck->run($check)->getStatus());
    }

    #[Test]
    public function testRunWithMalformedContentLengthIsTreatedAsMissing(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 1]);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => 'abc']])),
            $this->makeClientUrl('https://example.test/a.png'),
        );

        self::assertSame(CheckStatus::Warn, $fileCheck->run($check)->getStatus());
    }

    #[Test]
    public function testContentTypeMatchIsCaseInsensitive(): void
    {
        $check = $this->createSiteCheck(['client_url_id' => 1, 'expected_content_type' => ' Image/ ']);
        $fileCheck = $this->buildCheck(
            new MockHttpClient(new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-length' => '100', 'content-type' => 'IMAGE/PNG'],
            ])),
            $this->makeClientUrl('https://example.test/a.png'),
        );

        self::assertSame(CheckStatus::Ok, $fileCheck->run($check)->getStatus());
    }

    /** @return iterable<string, array{int}> */
    public static function headRejectingStatusProvider(): iterable
    {
        yield '403 presigned URL' => [403];
        yield '405 method not allowed' => [405];
        yield '501 not implemented' => [501];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('headRejectingStatusProvider')]
    public function testRunFallsBackToRangedGetWhenHeadIsRejected(int $headStatus): void
    {
        $head = new MockResponse('', ['http_code' => $headStatus]);
        $get = new MockResponse('x', [
            'http_code' => 206,
            'response_headers' => ['content-range' => 'bytes 0-0/54321', 'content-length' => '1', 'content-type' => 'image/png'],
        ]);
        $fileCheck = $this->buildCheck(new MockHttpClient([$head, $get]), $this->makeClientUrl('https://example.test/a.png'));

        $result = $fileCheck->run($this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 100]));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertSame('GET', $get->getRequestMethod());
        self::assertContains('Range: bytes=0-0', $get->getRequestOptions()['headers']);
    }

    #[Test]
    public function testRunFallbackStillFailsOnRealNotFound(): void
    {
        $fileCheck = $this->buildCheck(
            new MockHttpClient([new MockResponse('', ['http_code' => 405]), new MockResponse('', ['http_code' => 404])]),
            $this->makeClientUrl('https://example.test/a.png'),
        );

        $result = $fileCheck->run($this->createSiteCheck(['client_url_id' => 1]));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('404', (string) $result->getMessage());
    }

    #[Test]
    public function testRunVerifiesZeroContentLengthFromHeadWithRangedGet(): void
    {
        $head = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]);
        $get = new MockResponse('x', [
            'http_code' => 206,
            'response_headers' => ['content-range' => 'bytes 0-0/54321'],
        ]);
        $fileCheck = $this->buildCheck(new MockHttpClient([$head, $get]), $this->makeClientUrl('https://example.test/a.png'));

        $result = $fileCheck->run($this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 1]));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReportsEmptyFileWhenRangeIsNotSatisfiable(): void
    {
        $head = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]);
        $get = new MockResponse('', [
            'http_code' => 416,
            'response_headers' => ['content-range' => 'bytes */0'],
        ]);
        $fileCheck = $this->buildCheck(new MockHttpClient([$head, $get]), $this->makeClientUrl('https://example.test/a.png'));

        $result = $fileCheck->run($this->createSiteCheck(['client_url_id' => 1, 'min_bytes' => 1]));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('0 bytes', (string) $result->getMessage());
    }

    #[Test]
    public function testRunForwardsBasicAuth(): void
    {
        $response = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '100']]);
        $clientUrl = $this->makeClientUrl('https://example.test/a.png');
        $clientUrl->setBasicAuthUser('alice');
        $clientUrl->setBasicAuthPassword('s3cret');
        $fileCheck = $this->buildCheck(new MockHttpClient($response), $clientUrl);

        $fileCheck->run($this->createSiteCheck(['client_url_id' => 1]));

        self::assertContains('Authorization: Basic '.base64_encode('alice:s3cret'), $response->getRequestOptions()['headers']);
    }

    #[Test]
    public function testEmailTargetLabelAndResolution(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(), $this->makeClientUrl('https://example.test/a.png'));

        self::assertSame('File', $fileCheck->getEmailTargetLabel());
        self::assertNotNull($fileCheck->resolveEmailTarget(['client_url_id' => 1]));
        self::assertNull($fileCheck->resolveEmailTarget([]));
    }

    #[Test]
    public function testEmailTargetIsNullWhenUrlWasDeleted(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(), null);

        self::assertNull($fileCheck->resolveEmailTarget(['client_url_id' => 42]));
    }

    // --- helpers ---

    private function buildCheck(MockHttpClient $httpClient, ?ClientUrl $clientUrl = null): FileReachabilityCheck
    {
        $repo = $this->createStub(ClientUrlRepository::class);
        $repo->method('find')->willReturn($clientUrl);

        return new FileReachabilityCheck($httpClient, $repo);
    }

    /** @param array<string, mixed> $config */
    private function createSiteCheck(array $config): SiteCheck
    {
        $client = new Client();
        $client->setName('Example');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('file_reachability');
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
