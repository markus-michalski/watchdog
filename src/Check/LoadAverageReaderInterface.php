<?php

declare(strict_types=1);

namespace App\Check;

interface LoadAverageReaderInterface
{
    /**
     * Reads the 1-minute load average and the number of logical CPUs.
     *
     * Returns [load_1min, cpu_count] on success.
     * Returns null when /proc is unavailable (non-Linux system).
     * Returns an error string on read failure.
     *
     * @return array{0: float, 1: int}|string|null
     */
    public function read(): array|string|null;
}
