<?php

declare(strict_types=1);

namespace App\Message;

final readonly class RunSiteChecksMessage
{
    public function __construct(
        public int $siteCheckId,
    ) {
    }
}
