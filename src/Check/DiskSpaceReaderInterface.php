<?php

declare(strict_types=1);

namespace App\Check;

interface DiskSpaceReaderInterface
{
    /**
     * Returns [total_bytes, free_bytes] on success.
     * Returns null if the path does not exist or is not a directory (configuration error).
     * Returns a human-readable error string if the OS cannot read the disk info (read error).
     *
     * @return array{0: int, 1: int}|null|string
     */
    public function read(string $path): array|null|string;
}
