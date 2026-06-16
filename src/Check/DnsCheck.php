<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class DnsCheck implements CheckInterface
{
    public function __construct(private readonly DnsResolverInterface $resolver)
    {
    }

    public function supportsAgentRunner(): bool { return true; }

    public function getType(): string
    {
        return 'dns';
    }

    public function getLabel(): string
    {
        return 'DNS';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'hostname' => '',
            'expected_ip' => '',
            'resolver' => '',
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'hostname',
                'label' => 'Hostname',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'example.com',
                'help' => 'Hostname to resolve.',
            ],
            [
                'name' => 'expected_ip',
                'label' => 'Expected IP',
                'type' => 'text',
                'required' => false,
                'default' => '',
                'placeholder' => '1.2.3.4',
                'help' => 'Optional. Fail if this IP address is not in the DNS response. Leave empty to only check reachability.',
            ],
            [
                'name' => 'resolver',
                'label' => 'Custom resolver',
                'type' => 'text',
                'required' => false,
                'default' => '',
                'placeholder' => '8.8.8.8',
                'help' => 'Optional. Use a specific DNS resolver instead of the system default.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'Hostname';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $hostname = is_string($config['hostname'] ?? null) ? trim($config['hostname']) : '';

        return '' !== $hostname ? $hostname : null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $hostname = is_string($config['hostname']) ? trim($config['hostname']) : '';
        if ('' === $hostname) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No hostname configured');

            return $result;
        }

        $expectedIp = is_string($config['expected_ip']) ? trim($config['expected_ip']) : '';
        $customResolver = is_string($config['resolver']) && '' !== trim($config['resolver'])
            ? trim($config['resolver'])
            : null;

        $resolved = $this->resolver->resolve($hostname, $customResolver);

        if (is_string($resolved)) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($resolved);

            return $result;
        }

        if ([] === $resolved) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('DNS returned no records for %s', $hostname));

            return $result;
        }

        if ('' !== $expectedIp && !in_array($expectedIp, $resolved, true)) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf(
                'Expected IP %s not found for %s (resolved: %s)',
                $expectedIp,
                $hostname,
                implode(', ', $resolved),
            ));

            return $result;
        }

        $result->setStatus(CheckStatus::Ok);
        $result->setMessage(sprintf('%s → %s', $hostname, implode(', ', $resolved)));

        return $result;
    }
}
