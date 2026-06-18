<?php

declare(strict_types=1);

namespace App\Check;

interface LogFileReaderInterface
{
    /**
     * Reads a log file and returns its metadata and content.
     *
     * Returns ['mtime' => int, 'lines' => string[]] on success.
     * Returns null when the file does not exist.
     * Returns an error string on read failure (permission denied, etc.).
     *
     * @return array{mtime: int, lines: string[]}|string|null
     */
    public function read(string $path): array|string|null;
}
