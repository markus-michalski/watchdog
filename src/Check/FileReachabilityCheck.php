<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Enum\RunnerMode;
use App\Repository\ClientUrlRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AutoconfigureTag('watchdog.check')]
final class FileReachabilityCheck implements CheckInterface
{
    /** HEAD answers that say nothing about the file itself (e.g. presigned URLs are signed per method). */
    private const HEAD_REJECTED_STATUS_CODES = [403, 405, 501];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClientUrlRepository $clientUrlRepository,
    ) {
    }

    public function runnerMode(): RunnerMode
    {
        return RunnerMode::DashboardOnly;
    }

    public function getType(): string
    {
        return 'file_reachability';
    }

    public function getLabel(): string
    {
        return 'File Reachability';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'min_bytes' => 1,
            'expected_content_type' => '',
            'timeout' => 10,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'client_url_id',
                'label' => 'File URL',
                'type' => 'client_url_select',
                'required' => true,
                'default' => '',
                'placeholder' => '',
                'help' => 'Select the URL of the file to monitor (e.g. an image). Add URLs in the client settings first.',
            ],
            [
                'name' => 'min_bytes',
                'label' => 'Min size (bytes)',
                'type' => 'number',
                'required' => false,
                'default' => 1,
                'placeholder' => '1',
                'help' => 'Fail if the file is smaller than this. Set to 1 to detect empty responses (e.g. a broken image). Leave at 0 to disable. If the server does not report a size, the check warns instead of passing.',
            ],
            [
                'name' => 'expected_content_type',
                'label' => 'Expected content type',
                'type' => 'text',
                'required' => false,
                'default' => '',
                'placeholder' => 'image/',
                'help' => 'Response Content-Type must start with this value (e.g. "image/" for any image). Recommended: many sites answer a missing file with a 200 HTML page. Leave empty to skip.',
            ],
            [
                'name' => 'timeout',
                'label' => 'Timeout (seconds)',
                'type' => 'number',
                'required' => false,
                'default' => 10,
                'placeholder' => '10',
                'help' => '',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'File';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $raw = $config['client_url_id'] ?? null;
        $id = is_int($raw) || is_string($raw) ? (int) $raw : null;
        if (null === $id) {
            return null;
        }

        $clientUrl = $this->clientUrlRepository->find($id);

        return $clientUrl?->getDisplayLabel();
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $rawId = $config['client_url_id'] ?? null;
        $clientUrlId = is_int($rawId) || is_string($rawId) ? (int) $rawId : null;

        if (null === $clientUrlId || $clientUrlId <= 0) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No file URL selected for this check');

            return $result;
        }

        $clientUrl = $this->clientUrlRepository->find($clientUrlId);

        if (null === $clientUrl) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf('URL #%d not found — was it deleted?', $clientUrlId));

            return $result;
        }

        $options = [
            'timeout' => $config['timeout'] ?? 10,
            'verify_peer' => true,
            'max_redirects' => 5,
        ];

        if ($clientUrl->hasBasicAuth()) {
            $options['auth_basic'] = [
                $clientUrl->getBasicAuthUser(),
                $clientUrl->getBasicAuthPassword(),
            ];
        }

        $start = hrtime(true);

        try {
            // HEAD avoids downloading the full file — Content-Length/-Type come from the headers alone.
            $response = $this->httpClient->request('HEAD', $clientUrl->getUrl(), $options);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);

            $minBytes = is_numeric($config['min_bytes']) ? (int) $config['min_bytes'] : 0;

            if ($this->headIsInconclusive($statusCode, $headers, $minBytes)) {
                $response = $this->requestFirstByte($clientUrl->getUrl(), $options);
                $statusCode = $response->getStatusCode();
                $headers = $response->getHeaders(false);
                // Only the headers are needed — abort so the body is never downloaded.
                $response->cancel();
            }

            $result->setStatusCode($statusCode);
            $result->setResponseTimeMs((int) ((hrtime(true) - $start) / 1_000_000));

            $size = $this->resolveSize($statusCode, $headers);

            // 416 only counts when it reveals the size (empty file: "bytes */0").
            $reachable = ($statusCode >= 200 && $statusCode < 300) || (416 === $statusCode && null !== $size);
            if (!$reachable) {
                $result->setStatus(CheckStatus::Fail);
                $result->setMessage(sprintf('Unexpected HTTP status: %d', $statusCode));

                return $result;
            }

            $sizeUnverified = $minBytes > 0 && null === $size;

            if (null !== $size && $size < $minBytes) {
                $result->setStatus(CheckStatus::Fail);
                $result->setMessage(sprintf('File is %d bytes (min: %d bytes)', $size, $minBytes));

                return $result;
            }

            $expectedContentType = is_string($config['expected_content_type']) ? strtolower(trim($config['expected_content_type'])) : '';
            if ('' !== $expectedContentType) {
                $contentType = trim($headers['content-type'][0] ?? '');
                if (!str_starts_with(strtolower($contentType), $expectedContentType)) {
                    $result->setStatus(CheckStatus::Fail);
                    $result->setMessage(sprintf('Unexpected content type: "%s" (expected: "%s")', $contentType, $expectedContentType));

                    return $result;
                }
            }

            if ($sizeUnverified) {
                $result->setStatus(CheckStatus::Warn);
                $result->setMessage('Content-Length not provided by server — size not verified');

                return $result;
            }

            $result->setStatus(CheckStatus::Ok);
        } catch (\Throwable $e) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($e->getMessage());
            $result->setResponseTimeMs((int) ((hrtime(true) - $start) / 1_000_000));
        }

        return $result;
    }

    /**
     * HEAD can't be trusted when the server rejects the method, or answers a 2xx with an empty
     * Content-Length (some handlers report the length of the empty HEAD body instead of the file) —
     * the latter only matters while a minimum size is enforced.
     *
     * @param array<string, list<string>> $headers
     */
    private function headIsInconclusive(int $statusCode, array $headers, int $minBytes): bool
    {
        if (in_array($statusCode, self::HEAD_REJECTED_STATUS_CODES, true)) {
            return true;
        }

        return $minBytes > 0 && $statusCode >= 200 && $statusCode < 300 && '0' === trim($headers['content-length'][0] ?? '');
    }

    /** @param array<string, mixed> $options */
    private function requestFirstByte(string $url, array $options): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, $options + ['headers' => ['Range' => 'bytes=0-0']]);
    }

    /**
     * Total file size in bytes, or null when the server did not report a usable one.
     *
     * @param array<string, list<string>> $headers
     */
    private function resolveSize(int $statusCode, array $headers): ?int
    {
        // Content-Range carries the total for ranged answers ("bytes 0-0/54321", "bytes */0").
        if (1 === preg_match('#^bytes\s+(?:\d+-\d+|\*)/(\d+)$#i', trim($headers['content-range'][0] ?? ''), $matches)) {
            return (int) $matches[1];
        }

        // Without Content-Range a partial answer's Content-Length is the slice, not the file.
        if (206 === $statusCode || 416 === $statusCode) {
            return null;
        }

        $length = trim($headers['content-length'][0] ?? '');

        return ctype_digit($length) ? (int) $length : null;
    }
}
