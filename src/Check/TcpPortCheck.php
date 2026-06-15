<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class TcpPortCheck implements CheckInterface
{
    public function __construct(private readonly TcpConnectorInterface $connector)
    {
    }

    public function getType(): string
    {
        return 'tcp_port';
    }

    public function getLabel(): string
    {
        return 'TCP Port';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'host' => '',
            'port' => 80,
            'timeout' => 5,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'host',
                'label' => 'Host',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'db.internal',
                'help' => 'Hostname or IPv4 address to connect to. IPv6 literals are not supported.',
            ],
            [
                'name' => 'port',
                'label' => 'Port',
                'type' => 'number',
                'required' => true,
                'default' => 80,
                'placeholder' => '3306',
                'help' => 'TCP port number (e.g. 3306 for MySQL, 6379 for Redis).',
            ],
            [
                'name' => 'timeout',
                'label' => 'Timeout (seconds)',
                'type' => 'number',
                'required' => false,
                'default' => 5,
                'placeholder' => '5',
                'help' => 'Connection timeout in seconds. Default: 5.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'Host:Port';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $host = is_string($config['host'] ?? null) ? trim($config['host']) : '';
        $port = is_numeric($config['port'] ?? null) ? (int) $config['port'] : null;

        if ('' === $host || null === $port) {
            return null;
        }

        return "{$host}:{$port}";
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $host = is_string($config['host']) ? trim($config['host']) : '';
        if ('' === $host) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No host configured');

            return $result;
        }

        $port = is_numeric($config['port']) ? (int) $config['port'] : 0;
        if ($port <= 0 || $port > 65535) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('Invalid port configured');

            return $result;
        }

        $timeout = is_numeric($config['timeout']) ? max(1, min(60, (int) $config['timeout'])) : 5;

        $error = $this->connector->connect($host, $port, $timeout);

        if (null === $error) {
            $result->setStatus(CheckStatus::Ok);
            $result->setMessage(sprintf('%s:%d reachable', $host, $port));
        } else {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($error);
        }

        return $result;
    }
}
