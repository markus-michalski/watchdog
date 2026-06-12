<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DurationExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('duration', $this->formatDuration(...)),
            new TwigFilter('format_durations', $this->formatDurationsInText(...)),
        ];
    }

    /** Replaces "401 min" patterns anywhere in a string with the human-readable equivalent. */
    public function formatDurationsInText(string $text): string
    {
        return (string) preg_replace_callback(
            '/(\d+)\s*min\b/',
            fn (array $m) => $this->formatDuration((int) $m[1]),
            $text
        );
    }

    public function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }

        $days  = intdiv($minutes, 1440);
        $rem   = $minutes % 1440;
        $hours = intdiv($rem, 60);
        $mins  = $rem % 60;

        $parts = [];
        if ($days > 0)  $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($mins > 0)  $parts[] = "{$mins}min";

        return implode(' ', $parts) ?: '0 min';
    }
}
