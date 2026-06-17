<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\RunnerMode;

interface CheckInterface
{
    /** Unique type identifier, e.g. "http" or "docker" */
    public function getType(): string;

    /** Execute the check and return a populated (unpersisted) result */
    public function run(SiteCheck $check): CheckResult;

    /** Default config values shown in the UI when creating this check type */
    /** @return array<string, mixed> */
    public function getDefaultConfig(): array;

    /** Human-readable label for the UI */
    public function getLabel(): string;

    /**
     * Declare config fields for the UI form.
     * Each entry: ['name'=>string, 'label'=>string, 'type'=>'text'|'number', 'required'=>bool, 'default'=>mixed, 'placeholder'=>string, 'help'=>string]
     * Return [] if the check needs no configuration (e.g. uses site URL directly).
     *
     * @return array<int, array{name: string, label: string, type: string, required: bool, default: mixed, placeholder: string, help: string}>
     */
    public function getConfigSchema(): array;

    /**
     * Column label for the primary check target shown in alert emails (e.g. "URL", "Container", "Path").
     * Return null if this check type has no meaningful target to display.
     */
    public function getEmailTargetLabel(): ?string;

    /**
     * Resolve the human-readable target value for alert emails from the stored config.
     * Return null if not configured or not applicable.
     *
     * @param array<string, mixed> $config
     */
    public function resolveEmailTarget(array $config): ?string;

    /**
     * Which runner(s) this check type is compatible with.
     * AgentOnly  = requires direct host access (filesystem, /proc, Docker socket)
     * DashboardOnly = requires URL from the client record (http, ssl_cert)
     * Both = network-level check that works from any host (tcp, redis, db, dns)
     */
    public function runnerMode(): RunnerMode;
}
