<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class DatabaseCheck implements CheckInterface
{
    public function __construct(private readonly DatabaseConnectionInterface $connector)
    {
    }

    public function supportsAgentRunner(): bool { return true; }

    public function getType(): string
    {
        return 'database';
    }

    public function getLabel(): string
    {
        return 'Database';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'dsn' => '',
            'username' => '',
            'password' => '',
            'timeout' => 5,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'dsn',
                'label' => 'DSN',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'mysql:host=localhost;dbname=app',
                'help' => 'PDO Data Source Name. Examples: mysql:host=localhost;dbname=app, pgsql:host=localhost;dbname=app, sqlite:/path/to/db.sqlite.',
            ],
            [
                'name' => 'username',
                'label' => 'Username',
                'type' => 'text',
                'required' => false,
                'default' => '',
                'placeholder' => 'monitor',
                'help' => 'Database username. Leave empty for SQLite.',
            ],
            [
                'name' => 'password',
                'label' => 'Password',
                'type' => 'password',
                'required' => false,
                'default' => '',
                'placeholder' => '',
                'help' => 'Database password. Stored in check config — use a dedicated read-only monitoring user with minimal privileges.',
            ],
            [
                'name' => 'timeout',
                'label' => 'Timeout (seconds)',
                'type' => 'number',
                'required' => false,
                'default' => 5,
                'placeholder' => '5',
                'help' => 'Connection timeout in seconds (1–30). Default: 5.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'DSN';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $dsn = is_string($config['dsn'] ?? null) ? trim($config['dsn']) : '';

        return '' !== $dsn ? $dsn : null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $dsn = is_string($config['dsn']) ? trim($config['dsn']) : '';
        if ('' === $dsn) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No DSN configured');

            return $result;
        }

        $username = is_string($config['username']) ? $config['username'] : '';
        $password = is_string($config['password']) ? $config['password'] : '';
        $timeout = is_numeric($config['timeout']) ? max(1, min(30, (int) $config['timeout'])) : 5;

        $error = $this->connector->connect($dsn, $username, $password, $timeout);

        if (null === $error) {
            $result->setStatus(CheckStatus::Ok);
            $result->setMessage(sprintf('Connected to %s', $dsn));
        } else {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($error);
        }

        return $result;
    }
}
