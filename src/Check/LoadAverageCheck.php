<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Enum\RunnerMode;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class LoadAverageCheck implements CheckInterface
{
    public function __construct(private readonly LoadAverageReaderInterface $reader)
    {
    }

    public function runnerMode(): RunnerMode { return RunnerMode::AgentOnly; }

    public function getType(): string
    {
        return 'load_average';
    }

    public function getLabel(): string
    {
        return 'Load Average';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'warn_factor' => 0.8,
            'fail_factor' => 1.5,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'warn_factor',
                'label' => 'Warn factor',
                'type' => 'float',
                'required' => false,
                'default' => 0.8,
                'placeholder' => '0.8',
                'help' => 'Warn when 1-min load exceeds this factor × CPU count. Default: 0.8.',
            ],
            [
                'name' => 'fail_factor',
                'label' => 'Fail factor',
                'type' => 'float',
                'required' => false,
                'default' => 1.5,
                'placeholder' => '1.5',
                'help' => 'Fail when 1-min load exceeds this factor × CPU count. Default: 1.5.',
            ],
        ];
    }

    public function getEmailTargetLabel(): ?string
    {
        return null;
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        return null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $warnFactor = is_numeric($config['warn_factor']) ? (float) $config['warn_factor'] : 0.8;
        $failFactor = is_numeric($config['fail_factor']) ? (float) $config['fail_factor'] : 1.5;

        if ($warnFactor >= $failFactor) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf(
                'Invalid config: warn_factor (%.2f) must be less than fail_factor (%.2f)',
                $warnFactor,
                $failFactor,
            ));

            return $result;
        }

        $data = $this->reader->read();

        if (null === $data) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('/proc/loadavg unavailable — check runs only on Linux hosts');

            return $result;
        }

        if (is_string($data)) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($data);

            return $result;
        }

        [$load, $cpus] = $data;
        $warnThreshold = $warnFactor * $cpus;
        $failThreshold = $failFactor * $cpus;

        if ($load >= $failThreshold) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf(
                'Load: %.2f (%d CPUs, fail threshold: %.2f)',
                $load,
                $cpus,
                $failThreshold,
            ));

            return $result;
        }

        if ($load >= $warnThreshold) {
            $result->setStatus(CheckStatus::Warn);
            $result->setMessage(sprintf(
                'Load: %.2f (%d CPUs, warn threshold: %.2f)',
                $load,
                $cpus,
                $warnThreshold,
            ));

            return $result;
        }

        $result->setStatus(CheckStatus::Ok);
        $result->setMessage(sprintf('Load: %.2f (%d CPUs)', $load, $cpus));

        return $result;
    }
}
