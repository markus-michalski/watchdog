<?php

declare(strict_types=1);

namespace App\Check;

final class LoadAverageReader implements LoadAverageReaderInterface
{
    public function read(): array|string|null
    {
        if (!is_readable('/proc/loadavg')) {
            return null;
        }

        $content = @file_get_contents('/proc/loadavg');
        if (false === $content) {
            return 'Cannot read /proc/loadavg';
        }

        $parts = explode(' ', trim($content));
        $load1min = (float) ($parts[0] ?? 0.0);

        $cpuCount = $this->readCpuCount();

        return [$load1min, $cpuCount];
    }

    private function readCpuCount(): int
    {
        $cpuInfo = @file_get_contents('/proc/cpuinfo');
        if (false === $cpuInfo) {
            return 1;
        }

        preg_match_all('/^processor\s*:/m', $cpuInfo, $matches);

        return max(1, count($matches[0]));
    }
}
