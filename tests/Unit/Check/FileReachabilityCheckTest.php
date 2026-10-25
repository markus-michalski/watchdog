<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\FileReachabilityCheck;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FileReachabilityCheckTest extends TestCase
{
    private const URL = 'https://example.test/media/logo.png';

    #[Test]
    public function testGetTypeReturnsFileReachability(): void
    {
        self::assertSame('file_reachability', $this->buildCheck(new MockHttpClient())->getType());
    }

    #[Test]
    public function testSchemaAsksForFreeTextUrl(): void
    {
        $fields = array_column($this->buildCheck(new MockHttpClient())->getConfigSchema(), null, 'name');

        self::assertSame('text', $fields['url']['type']);
        self::assertTrue($fields['url']['required']);
        self::assertArrayNotHasKey('client_url_id', $fields);
    }

    #[Test]
    public function testRunWithNoUrlReturnsUnknown(): void
    {
        $result = $this->buildCheck(new MockHttpClient())->run($this->createSiteCheck([]));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrlProvider(): iterable
    {
        yield 'blank' => ['   '];
        yield 'no scheme' => ['example.test/logo.png'];
        yield 'relative path' => ['/media/logo.png'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'ftp scheme' => ['ftp://example.test/logo.png'];
        yield 'no host' => ['https:///logo.png'];
    }

    #[Test]
    #[DataProvider('invalidUrlProvider')]
    public function testRunWithInvalidUrlReturnsUnknownWithoutRequest(string $url): void
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('', ['http_code' => 200]);
        });

        $result = $this->buildCheck($client)->run($this->createSiteCheck(['url' => $url]));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertSame(0, $requests);
    }

    #[Test]
    public function testRunRequestsTheConfiguredUrlTrimmed(): void
    {
        $response = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '100']]);

        $this->buildCheck(new MockHttpClient($response))->run($this->createSiteCheck(['url' => '  '.self::URL.' ']));

        self::assertSame(self::URL, $response->getRequestUrl());
    }

    #[Test]
    public function testRunWithReachableFileReturnsOkStatus(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-length' => '54321', 'content-type' => 'image/png'],
        ])));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL]));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function testRunWith404ReturnsFailStatusWithStatusCodeInMessage(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', ['http_code' => 404])));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL]));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('404', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithEmptyFileBelowMinBytesReturnsFailStatus(): void
    {
        // HEAD reports 0, the ranged GET confirms the file really is empty
        $fileCheck = $this->buildCheck(new MockHttpClient([
            new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]),
            new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]),
        ]));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 1]));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('0 bytes', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithContentLengthAtOrAboveMinBytesReturnsOkStatus(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-length' => '500'],
        ])));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 500]));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithUnexpectedContentTypeReturnsFailStatus(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-length' => '100', 'content-type' => 'text/html'],
        ])));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'expected_content_type' => 'image/']));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('text/html', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithMatchingContentTypeReturnsOkStatus(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-length' => '100', 'content-type' => 'image/jpeg'],
        ])));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'expected_content_type' => 'image/']));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunWithNetworkExceptionReturnsFailStatus(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(static function (): never {
            throw new TransportException('Connection refused');
        }));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL]));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection refused', (string) $result->getMessage());
    }

    #[Test]
    public function testRunSendsHeadRequest(): void
    {
        $response = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '100']]);

        $this->buildCheck(new MockHttpClient($response))->run($this->createSiteCheck(['url' => self::URL]));

        self::assertSame('HEAD', $response->getRequestMethod());
    }

    #[Test]
    public function testRunWithoutContentLengthAndMinBytesReturnsWarn(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'image/png'],
        ])));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 1]));

        self::assertSame(CheckStatus::Warn, $result->getStatus());
        self::assertStringContainsString('Content-Length', (string) $result->getMessage());
    }

    #[Test]
    public function testRunWithoutContentLengthAndMinBytesZeroReturnsOk(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', ['http_code' => 200])));

        self::assertSame(CheckStatus::Ok, $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 0]))->getStatus());
    }

    #[Test]
    public function testRunWithMinBytesZeroIgnoresEmptyContentLength(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-length' => '0'],
        ])));

        self::assertSame(CheckStatus::Ok, $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 0]))->getStatus());
    }

    #[Test]
    public function testRunWithMalformedContentLengthIsTreatedAsMissing(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-length' => 'abc'],
        ])));

        self::assertSame(CheckStatus::Warn, $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 1]))->getStatus());
    }

    #[Test]
    public function testContentTypeMatchIsCaseInsensitive(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 200,
            'response_headers' => ['content-length' => '100', 'content-type' => 'IMAGE/PNG'],
        ])));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'expected_content_type' => ' Image/ ']));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    /** @return iterable<string, array{int}> */
    public static function headRejectingStatusProvider(): iterable
    {
        yield '403 presigned URL' => [403];
        yield '405 method not allowed' => [405];
        yield '501 not implemented' => [501];
    }

    #[Test]
    #[DataProvider('headRejectingStatusProvider')]
    public function testRunFallsBackToRangedGetWhenHeadIsRejected(int $headStatus): void
    {
        $head = new MockResponse('', ['http_code' => $headStatus]);
        $get = new MockResponse('x', [
            'http_code' => 206,
            'response_headers' => ['content-range' => 'bytes 0-0/54321', 'content-length' => '1', 'content-type' => 'image/png'],
        ]);
        $fileCheck = $this->buildCheck(new MockHttpClient([$head, $get]));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 100]));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertSame('GET', $get->getRequestMethod());
        self::assertContains('Range: bytes=0-0', $get->getRequestOptions()['headers']);
    }

    #[Test]
    public function testRunFallbackStillFailsOnRealNotFound(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient([
            new MockResponse('', ['http_code' => 405]),
            new MockResponse('', ['http_code' => 404]),
        ]));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL]));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('404', (string) $result->getMessage());
    }

    #[Test]
    public function testRunVerifiesZeroContentLengthFromHeadWithRangedGet(): void
    {
        $head = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]);
        $get = new MockResponse('x', ['http_code' => 206, 'response_headers' => ['content-range' => 'bytes 0-0/54321']]);
        $fileCheck = $this->buildCheck(new MockHttpClient([$head, $get]));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 1]));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReportsEmptyFileWhenRangeIsNotSatisfiable(): void
    {
        $head = new MockResponse('', ['http_code' => 200, 'response_headers' => ['content-length' => '0']]);
        $get = new MockResponse('', ['http_code' => 416, 'response_headers' => ['content-range' => 'bytes */0']]);
        $fileCheck = $this->buildCheck(new MockHttpClient([$head, $get]));

        $result = $fileCheck->run($this->createSiteCheck(['url' => self::URL, 'min_bytes' => 1]));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('0 bytes', (string) $result->getMessage());
    }

    #[Test]
    public function testEmailTargetIsTheConfiguredUrl(): void
    {
        $fileCheck = $this->buildCheck(new MockHttpClient());

        self::assertSame('File', $fileCheck->getEmailTargetLabel());
        self::assertSame(self::URL, $fileCheck->resolveEmailTarget(['url' => ' '.self::URL.' ']));
        self::assertNull($fileCheck->resolveEmailTarget([]));
        self::assertNull($fileCheck->resolveEmailTarget(['url' => '  ']));
    }

    // --- helpers ---

    private function buildCheck(MockHttpClient $httpClient): FileReachabilityCheck
    {
        return new FileReachabilityCheck($httpClient);
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
}
