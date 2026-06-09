<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withSets([
        SymfonySetList::SYMFONY_80,
    ]);
