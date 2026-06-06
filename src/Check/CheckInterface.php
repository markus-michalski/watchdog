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
    public function getDefaultConfig(): array;

    /** Human-readable label for the UI */
    public function getLabel(): string;
}
