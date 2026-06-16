<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Enum\RunnerMode;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class FileAgeCheck implements CheckInterface
{
    public function runnerMode(): RunnerMode { return RunnerMode::AgentOnly; }

    public function getType(): string
    {
        return 'file_age';
    }

    public function getLabel(): string
    {
        return 'File Age';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'path' => '',
            'max_age_minutes' => 1440,
            'warn_age_minutes' => 0,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'path',
                'label' => 'File path',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => '/var/backup/.last_success',
                'help' => 'Absolute path to the file to monitor (e.g. a timestamp file written by a backup script).',
            ],
            [
                'name' => 'max_age_minutes',
                'label' => 'Max age',
                'type' => 'duration',
                'required' => false,
                'default' => 1440,
                'placeholder' => '',
                'help' => 'Fail if the file has not been modified within this time. Default: 1 day.',
            ],
            [
                'name' => 'warn_age_minutes',
                'label' => 'Warn age',
                'type' => 'duration',
                'required' => false,
                'default' => 0,
                'placeholder' => '',
                'help' => 'Warn if the file is older than this time (must be less than max age). Leave at 0 to disable.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'Path';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $path = $config['path'] ?? '';

        return is_string($path) && '' !== $path ? $path : null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $path = is_string($config['path']) ? trim($config['path']) : '';
        if ('' === $path) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No path configured');

            return $result;
        }

        $hostRoot = rtrim((string) (getenv('HOST_ROOT') ?: ''), '/');
        $resolvedPath = $hostRoot !== '' ? $hostRoot . '/' . ltrim($path, '/') : $path;

        if (!file_exists($resolvedPath)) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('File not found: %s', $path));

            return $result;
        }

        $maxAgeMinutes = is_numeric($config['max_age_minutes']) ? (int) $config['max_age_minutes'] : 1440;
        $warnAgeMinutes = is_numeric($config['warn_age_minutes']) ? (int) $config['warn_age_minutes'] : 0;

        $mtime = filemtime($resolvedPath);
        if (false === $mtime) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf('Cannot read mtime of %s', $path));

            return $result;
        }

        $ageMinutes = (int) round((time() - $mtime) / 60);

        // Clock-skew: mtime in the future is not a valid OK state
        if ($ageMinutes < 0) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf('File mtime is in the future (%d min)', $ageMinutes));

            return $result;
        }

        if ($ageMinutes > $maxAgeMinutes) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('File is %d min old (max: %d min)', $ageMinutes, $maxAgeMinutes));

            return $result;
        }

        if ($warnAgeMinutes > 0 && $ageMinutes > $warnAgeMinutes) {
            $result->setStatus(CheckStatus::Warn);
            $result->setMessage(sprintf('File is %d min old (warn: %d min)', $ageMinutes, $warnAgeMinutes));

            return $result;
        }

        $result->setStatus(CheckStatus::Ok);
        $result->setMessage(sprintf('%d min old', $ageMinutes));

        return $result;
    }
}
