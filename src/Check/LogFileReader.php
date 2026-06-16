<?php

declare(strict_types=1);

namespace App\Check;

final class LogFileReader implements LogFileReaderInterface
{
    public function read(string $path): array|null|string
    {
        if (!file_exists($path)) {
            return null;
        }

        $mtime = filemtime($path);
        if (false === $mtime) {
            return sprintf('Cannot read mtime of %s', $path);
        }

        $content = @file_get_contents($path);
        if (false === $content) {
            return sprintf('Cannot read file: %s', $path);
        }

        return [
            'mtime' => $mtime,
            'lines' => explode("\n", rtrim($content)),
        ];
    }
}
