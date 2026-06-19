<?php

declare(strict_types=1);

namespace App\Twig;

use App\Check\CheckRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class CheckLabelExtension extends AbstractExtension
{
    public function __construct(private readonly CheckRegistry $registry)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('check_label', $this->resolveLabel(...)),
        ];
    }

    public function resolveLabel(string $type): string
    {
        if ($this->registry->has($type)) {
            return $this->registry->get($type)->getLabel();
        }

        return ucwords(str_replace('_', ' ', $type));
    }
}
