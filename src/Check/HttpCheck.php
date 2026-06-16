<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Repository\ClientUrlRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('watchdog.check')]
final class HttpCheck implements CheckInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClientUrlRepository $clientUrlRepository,
    ) {
    }

    public function supportsAgentRunner(): bool { return false; }

    public function getType(): string
    {
        return 'http';
    }

    public function getLabel(): string
    {
        return 'HTTP Reachability';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'expected_status_codes' => [200, 201, 301, 302],
            'timeout' => 10,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'client_url_id',
                'label' => 'URL',
                'type' => 'client_url_select',
                'required' => true,
                'default' => '',
                'placeholder' => '',
                'help' => 'Select which URL to monitor. Add URLs in the client settings first.',
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
            [
                'name' => 'expected_status_codes',
                'label' => 'Expected status codes',
                'type' => 'text',
                'required' => false,
                'default' => '200,201,301,302',
                'placeholder' => '200,201,301,302',
                'help' => 'Comma-separated. Leave empty to use defaults.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'URL';
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
            $result->setMessage('No URL selected for this check');

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
            $response = $this->httpClient->request('GET', $clientUrl->getUrl(), $options);
            $statusCode = $response->getStatusCode();
            $responseTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $result->setStatusCode($statusCode);
            $result->setResponseTimeMs($responseTimeMs);

            $expectedCodes = $config['expected_status_codes'] ?? [200];
            if (!is_array($expectedCodes)) {
                $expectedCodes = [200];
            }
            if (in_array($statusCode, $expectedCodes, true)) {
                $result->setStatus(CheckStatus::Ok);
            } else {
                $result->setStatus(CheckStatus::Fail);
                $result->setMessage(sprintf('Unexpected HTTP status: %d', $statusCode));
            }
        } catch (\Throwable $e) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($e->getMessage());
            $result->setResponseTimeMs((int) ((hrtime(true) - $start) / 1_000_000));
        }

        return $result;
    }
}
