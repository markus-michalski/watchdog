<?php

declare(strict_types=1);

namespace App\Check;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;

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
}
