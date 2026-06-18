<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Enum\RunnerMode;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class LogFileCheck implements CheckInterface
{
    public function __construct(private readonly LogFileReaderInterface $reader)
    {
    }

    public function runnerMode(): RunnerMode
    {
        return RunnerMode::AgentOnly;
    }

    public function getType(): string
    {
        return 'log_file';
    }

    public function getLabel(): string
    {
        return 'Log File';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'log_path' => '',
            'pattern' => '',
            'max_age_minutes' => 1440,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'log_path',
                'label' => 'Log file path',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => '/var/log/backup.log',
                'help' => 'Absolute path to the log file to monitor.',
            ],
            [
                'name' => 'pattern',
                'label' => 'Pattern',
                'type' => 'text',
                'required' => true,
                'default' => '',
                'placeholder' => 'Backup completed',
                'help' => 'Plain text or regex (e.g. /status=OK.*duration=\d+s/) that must appear in the log file.',
            ],
            [
                'name' => 'max_age_minutes',
                'label' => 'Max age',
                'type' => 'duration',
                'required' => false,
                'default' => 1440,
                'placeholder' => '',
                'help' => 'Fail if the log file has not been modified within this time. Default: 1 day.',
            ],
        ];
    }

    public function getEmailTargetLabel(): string
    {
        return 'Log path';
    }

    /** @param array<string, mixed> $config */
    public function resolveEmailTarget(array $config): ?string
    {
        $path = $config['log_path'] ?? '';

        return is_string($path) && '' !== $path ? $path : null;
    }

    public function run(SiteCheck $check): CheckResult
    {
        $config = array_merge($this->getDefaultConfig(), $check->getConfig());
        $result = new CheckResult();
        $result->setCheck($check);

        $logPath = is_string($config['log_path']) ? trim($config['log_path']) : '';
        if ('' === $logPath) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No log_path configured');

            return $result;
        }

        $pattern = is_string($config['pattern']) ? trim($config['pattern']) : '';
        if ('' === $pattern) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('No pattern configured');

            return $result;
        }

        $maxAgeMinutes = is_numeric($config['max_age_minutes']) ? (int) $config['max_age_minutes'] : 1440;

        $hostRoot = rtrim((string) (getenv('HOST_ROOT') ?: ''), '/');
        $resolvedPath = '' !== $hostRoot ? $hostRoot.'/'.ltrim($logPath, '/') : $logPath;

        $data = $this->reader->read($resolvedPath);

        if (null === $data) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('Log file not found: %s', $logPath));

            return $result;
        }

        if (is_string($data)) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($data);

            return $result;
        }

        $ageMinutes = (int) round((time() - $data['mtime']) / 60);
        if ($ageMinutes > $maxAgeMinutes) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf(
                'Log file is %d min old (max: %d min): %s',
                $ageMinutes,
                $maxAgeMinutes,
                $logPath,
            ));

            return $result;
        }

        $isRegex = str_starts_with($pattern, '/') && (bool) preg_match('/\/[a-zA-Z]*$/', $pattern);
        $found = false;

        if ($isRegex) {
            $testResult = @preg_match($pattern, '');
            if (false === $testResult) {
                $result->setStatus(CheckStatus::Unknown);
                $result->setMessage(sprintf('Invalid pattern (regex error): %s', $pattern));

                return $result;
            }

            foreach ($data['lines'] as $line) {
                if (1 === preg_match($pattern, $line)) {
                    $found = true;
                    break;
                }
            }
        } else {
            foreach ($data['lines'] as $line) {
                if (str_contains($line, $pattern)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf(
                'Pattern not found in %s: %s',
                $logPath,
                $pattern,
            ));

            return $result;
        }

        $result->setStatus(CheckStatus::Ok);
        $result->setMessage(sprintf('Pattern matched in %s', $logPath));

        return $result;
    }
}
