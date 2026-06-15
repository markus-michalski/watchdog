<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class RedisCheck implements CheckInterface
{
    public function __construct(private readonly RedisPingerInterface $pinger)
    {
    }

    public function getType(): string
    {
        return 'redis';
    }

    public function getLabel(): string
    {
        return 'Redis';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'host' => '',
            'port' => 6379,
            'timeout' => 3,
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
                'placeholder' => 'redis.internal',
                'help' => 'Hostname or IP address of the Redis server.',
            ],
            [
                'name' => 'port',
                'label' => 'Port',
                'type' => 'number',
                'required' => false,
                'default' => 6379,
                'placeholder' => '6379',
                'help' => 'Redis port. Default: 6379.',
            ],
            [
                'name' => 'timeout',
                'label' => 'Timeout (seconds)',
                'type' => 'number',
                'required' => false,
                'default' => 3,
                'placeholder' => '3',
                'help' => 'Connection and read timeout in seconds. Default: 3.',
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

        $timeout = is_numeric($config['timeout']) ? max(1, min(60, (int) $config['timeout'])) : 3;

        $error = $this->pinger->ping($host, $port, $timeout);

        if (null === $error) {
            $result->setStatus(CheckStatus::Ok);
            $result->setMessage(sprintf('%s:%d responded with PONG', $host, $port));
        } else {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($error);
        }

        return $result;
    }
}
