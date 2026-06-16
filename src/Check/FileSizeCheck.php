<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('watchdog.check')]
final class FileSizeCheck implements CheckInterface
{
    public function supportsAgentRunner(): bool { return true; }

    public function getType(): string
    {
        return 'file_size';
    }

    public function getLabel(): string
    {
        return 'File Size';
    }

    /** @return array<string, mixed> */
    public function getDefaultConfig(): array
    {
        return [
            'path' => '',
            'min_bytes' => 0,
            'max_bytes' => 0,
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
                'placeholder' => '/var/backups/daily.tar.gz',
                'help' => 'Absolute path to the file to monitor.',
            ],
            [
                'name' => 'min_bytes',
                'label' => 'Min size (bytes)',
                'type' => 'number',
                'required' => false,
                'default' => 0,
                'placeholder' => '1',
                'help' => 'Fail if the file is smaller than this. Set to 1 to detect empty files (e.g. a 0-byte backup). Leave at 0 to disable.',
            ],
            [
                'name' => 'max_bytes',
                'label' => 'Max size (bytes)',
                'type' => 'number',
                'required' => false,
                'default' => 0,
                'placeholder' => '104857600',
                'help' => 'Fail if the file exceeds this size. Useful to detect runaway log files. Leave at 0 to disable.',
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

        $minBytes = is_numeric($config['min_bytes']) ? (int) $config['min_bytes'] : 0;
        $maxBytes = is_numeric($config['max_bytes']) ? (int) $config['max_bytes'] : 0;

        if ($minBytes > 0 && $maxBytes > 0 && $minBytes > $maxBytes) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf('Invalid config: min_bytes (%d) exceeds max_bytes (%d)', $minBytes, $maxBytes));

            return $result;
        }

        $hostRoot = rtrim((string) (getenv('HOST_ROOT') ?: ''), '/');
        $resolvedPath = '' !== $hostRoot ? $hostRoot . '/' . ltrim($path, '/') : $path;

        if (!file_exists($resolvedPath)) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('File not found: %s', $path));

            return $result;
        }

        $size = filesize($resolvedPath);
        if (false === $size) {
            $result->setStatus(CheckStatus::Unknown);
            $result->setMessage(sprintf('Cannot read size of %s', $path));

            return $result;
        }

        if ($minBytes > 0 && $size < $minBytes) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('File is %d bytes (min: %d bytes)', $size, $minBytes));

            return $result;
        }

        if ($maxBytes > 0 && $size > $maxBytes) {
            $result->setStatus(CheckStatus::Fail);
            $result->setMessage(sprintf('File is %d bytes (max: %d bytes)', $size, $maxBytes));

            return $result;
        }

        $result->setStatus(CheckStatus::Ok);
        $result->setMessage(sprintf('%d bytes', $size));

        return $result;
    }
}
