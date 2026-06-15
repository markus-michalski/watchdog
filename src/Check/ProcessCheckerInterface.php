<?php

declare(strict_types=1);

namespace App\Check;

interface ProcessCheckerInterface
{
    /**
     * Returns true if at least one process matching $name is running.
     * Returns false if no matching process is found.
     * Returns an error string if the check cannot be executed (e.g. pgrep not available).
     */
    public function isRunning(string $processName): bool|string;
}
