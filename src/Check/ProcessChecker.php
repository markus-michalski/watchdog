<?php

declare(strict_types=1);

namespace App\Check;

final class ProcessChecker implements ProcessCheckerInterface
{
    public function isRunning(string $processName): bool|string
    {
        exec('pgrep -f '.escapeshellarg($processName).' > /dev/null 2>&1', $output, $exitCode);

        if (0 === $exitCode) {
            return true;
        }

        if (1 === $exitCode) {
            return false;
        }

        // exit code 2+ = pgrep error or command not found (127)
        return sprintf('pgrep exited with code %d — is pgrep available on this system?', $exitCode);
    }
}
