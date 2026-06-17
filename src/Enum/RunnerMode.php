<?php

declare(strict_types=1);

namespace App\Enum;

enum RunnerMode: string
{
    case AgentOnly = 'agent_only';
    case DashboardOnly = 'dashboard_only';
    case Both = 'both';
}
