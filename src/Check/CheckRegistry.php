<?php

declare(strict_types=1);

namespace App\Check;

use App\Enum\RunnerMode;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class CheckRegistry
{
    /** @var array<string, CheckInterface> */
    private array $checks = [];

    /**
     * @param iterable<CheckInterface> $checks
     */
    public function __construct(
        #[AutowireIterator('watchdog.check')]
        iterable $checks,
    ) {
        foreach ($checks as $check) {
            $this->checks[$check->getType()] = $check;
        }
    }

    public function get(string $type): CheckInterface
    {
        if (!isset($this->checks[$type])) {
            throw new \InvalidArgumentException(sprintf('No check registered for type "%s". Available: %s', $type, implode(', ', array_keys($this->checks))));
        }

        return $this->checks[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->checks[$type]);
    }

    /** @return array<string, CheckInterface> */
    public function all(): array
    {
        return $this->checks;
    }

    /** @return array<string, string> type => label */
    public function getTypeChoices(): array
    {
        $choices = [];
        foreach ($this->checks as $type => $check) {
            $choices[$check->getLabel()] = $type;
        }

        return $choices;
    }

    /** @return array<string, array<int, array<string, mixed>>> type => config schema */
    public function getAllSchemas(): array
    {
        $schemas = [];
        foreach ($this->checks as $type => $check) {
            $schemas[$type] = $check->getConfigSchema();
        }

        return $schemas;
    }

    /** @return array<string, string> type => RunnerMode backing value ('agent_only'|'dashboard_only'|'both') */
    public function getRunnerModes(): array
    {
        $map = [];
        foreach ($this->checks as $type => $check) {
            $map[$type] = $check->runnerMode()->value;
        }

        return $map;
    }
}
