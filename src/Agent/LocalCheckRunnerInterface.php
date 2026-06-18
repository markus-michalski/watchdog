<?php

declare(strict_types=1);

namespace App\Agent;

interface LocalCheckRunnerInterface
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function run(int $checkId, string $type, array $config): array;
}
