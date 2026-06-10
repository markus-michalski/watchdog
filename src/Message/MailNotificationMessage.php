<?php

declare(strict_types=1);

namespace App\Message;

final readonly class MailNotificationMessage
{
    public function __construct(
        public int $siteCheckId,
        public int $checkResultId,
        public string $action, // 'failure' or 'recovery'
    ) {
    }
}
