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

#[AutoconfigureTag('watchdog.check')]
final class HttpContentCheck implements CheckInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClientUrlRepository $clientUrlRepository,
    ) {
    }

    public function runnerMode(): RunnerMode { return RunnerMode::DashboardOnly; }

    public function getType(): string
    {
        return 'http_content';
    }

    public function getLabel(): string
    {
        return 'HTTP Content';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'expected_string' => '',
            'forbidden_string' => '',
            'timeout' => 10,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
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
                'name' => 'expected_string',
                'label' => 'Expected string',
                'type' => 'text',
                'required' => false,
                'default' => '',
                'placeholder' => 'Welcome to MyApp',
                'help' => 'String that must appear in the response body. Leave empty to skip.',
            ],
            [
                'name' => 'forbidden_string',
                'label' => 'Forbidden string',
                'type' => 'text',
                'required' => false,
                'default' => '',
                'placeholder' => 'Under Maintenance',
                'help' => 'String that must NOT appear in the response body. Leave empty to skip.',
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
            // Pass false to avoid exceptions on 4xx/5xx — we care about body content, not status code
            $body = $response->getContent(false);
            $responseTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $result->setStatusCode($response->getStatusCode());
            $result->setResponseTimeMs($responseTimeMs);

            $expectedString = is_string($config['expected_string']) ? $config['expected_string'] : '';
            $forbiddenString = is_string($config['forbidden_string']) ? $config['forbidden_string'] : '';

            if ('' !== $expectedString && !str_contains($body, $expectedString)) {
                $result->setStatus(CheckStatus::Fail);
                $result->setMessage(sprintf('Expected string not found in response: "%s"', $expectedString));

                return $result;
            }

            if ('' !== $forbiddenString && str_contains($body, $forbiddenString)) {
                $result->setStatus(CheckStatus::Fail);
                $result->setMessage(sprintf('Forbidden string found in response: "%s"', $forbiddenString));

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
}
