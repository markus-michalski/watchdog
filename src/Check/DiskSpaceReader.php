<?php

declare(strict_types=1);

namespace App\Check;

final class DiskSpaceReader implements DiskSpaceReaderInterface
{
    public function read(string $path): array|null|string
    {
        if (!is_dir($path)) {
            return null;
        }

        $total = disk_total_space($path);
        $free = disk_free_space($path);

        if (false === $total || false === $free) {
            return sprintf('Cannot read disk space for path: %s', $path);
        }

        return [(int) $total, (int) $free];
    }
}
