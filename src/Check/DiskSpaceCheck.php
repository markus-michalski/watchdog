<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class DiskSpaceCheck implements CheckInterface
{
    public function __construct(private readonly DiskSpaceReaderInterface $diskSpaceReader)
    {
    }

    public function supportsAgentRunner(): bool { return true; }

    public function getType(): string
    {
        return 'disk_space';
    }

    public function getLabel(): string
    {
        return 'Disk Space';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'path' => '/',
            'warn_percent' => 80,
            'fail_percent' => 90,
        ];
    }

    /** @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}> */
    public function getConfigSchema(): array
    {
        return [
            [
                'name' => 'path',
                'label' => 'Mount path',
                'type' => 'text',
                'required' => true,
                'default' => '/',
                'placeholder' => '/',
                'help' => 'Absolute path of the mount point to monitor (e.g. / or /var or /mnt/backup).',
            ],
            [
                'name' => 'warn_percent',
                'label' => 'Warn threshold (%)',
                'type' => 'number',
                'required' => false,
                'default' => 80,
                'placeholder' => '80',
                'help' => 'Warn if disk usage reaches or exceeds this percentage. Default: 80.',
            ],
            [
                'name' => 'fail_percent',
                'label' => 'Fail threshold (%)',
                'type' => 'number',
                'required' => false,
                'default' => 90,
                'placeholder' => '90',
                'help' => 'Fail if disk usage reaches or exceeds this percentage (must be greater than warn threshold). Default: 90.',
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

        $warnPercent = is_numeric($config['warn_percent']) ? (int) $config['warn_percent'] : 80;
        $failPercent = is_numeric($config['fail_percent']) ? (int) $config['fail_percent'] : 90;

        if ($failPercent <= $warnPercent) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage('Invalid config: fail_percent must be greater than warn_percent');

            return $result;
        }

        $spaceOrError = $this->diskSpaceReader->read($resolvedPath);

        if (null === $spaceOrError) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf('Path not found or not a directory: %s', $path));

            return $result;
        }

        if (is_string($spaceOrError)) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage($spaceOrError);

            return $result;
        }

        [$total, $free] = $spaceOrError;

        if (0 === $total) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf('Cannot determine total disk space for %s', $path));

            return $result;
        }

        $used = $total - $free;
        $usedPercent = (int) round($used / $total * 100);
        $message = sprintf(
            '%d%% used (%s free of %s)',
            $usedPercent,
            $this->formatBytes($free),
            $this->formatBytes($total),
        );

        if ($usedPercent >= $failPercent) {
            $result->setStatus(CheckStatus::Fail);
        } elseif ($usedPercent >= $warnPercent) {
            $result->setStatus(CheckStatus::Warn);
        } else {
            $result->setStatus(CheckStatus::Ok);
        }

        $result->setMessage($message);

        return $result;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 1).' GB';
        }

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        return round($bytes / 1_024, 1).' KB';
    }
}
