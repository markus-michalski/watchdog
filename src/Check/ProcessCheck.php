<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class ProcessCheck implements CheckInterface
{
    public function __construct(private readonly ProcessCheckerInterface $processChecker)
    {
    }

    public function supportsAgentRunner(): bool { return true; }

    public function getType(): string
    {
        return 'process';
    }

    public function getLabel(): string
    {
        return 'Process Running';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'process_name' => '',
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'process_name',
                'label' => 'Process name',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'nginx',
                'help' => 'Name or substring to match against running processes (uses pgrep -f). Works out of the box on native installs. For Docker: add pid: host to the watchdog service in docker-compose.yml.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'Process';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $name = is_string($config['process_name'] ?? null) ? trim($config['process_name']) : '';

        return '' !== $name ? $name : null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $processName = is_string($config['process_name']) ? trim($config['process_name']) : '';

        if ('' === $processName) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No process name configured');

            return $result;
        }

        $running = $this->processChecker->isRunning($processName);

        if (is_string($running)) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage($running);

            return $result;
        }

        if ($running) {
            $result->setStatus(CheckStatus::Ok);
            $result->setMessage(sprintf('Process running: %s', $processName));
        } else {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('Process not running: %s', $processName));
        }

        return $result;
    }
}
