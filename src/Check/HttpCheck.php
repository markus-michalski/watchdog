<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('watchdog.check')]
final class HttpCheck implements CheckInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function getType(): string
    {
        return 'http';
    }

    public function getLabel(): string
    {
        return 'HTTP Reachability';
    }

    public function getDefaultConfig(): array
    {
        return [
            'expected_status_codes' => [200, 201, 301, 302],
            'timeout' => 10,
        ];
    }

    public function run(SiteCheck $check): CheckResult
    {
        $site = $check->getSite();
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $options = [
            'timeout' => $config['timeout'] ?? 10,
            'verify_peer' => true,
            'max_redirects' => 5,
        ];

        if ($site->hasBasicAuth()) {
            $options['auth_basic'] = [
                $site->getBasicAuthUser(),
                $site->getBasicAuthPassword(),
            ];
        }

        $start = hrtime(true);

        try {
            $response = $this->httpClient->request('GET', $site->getUrl(), $options);
            $statusCode = $response->getStatusCode();
            $responseTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $result->setStatusCode($statusCode);
            $result->setResponseTimeMs($responseTimeMs);

            $expectedCodes = $config['expected_status_codes'] ?? [200];
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
