<?php

declare(strict_types=1);

namespace App\Enum;

enum CheckRunner: string
{
    case Dashboard = 'dashboard';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard (Network)',
            self::Agent => 'Agent (Local)',
        };
    }
}
