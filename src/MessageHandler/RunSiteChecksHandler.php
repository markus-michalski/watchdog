<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RunSiteChecksMessage;
use App\Repository\SiteCheckRepository;
use App\Service\CheckRunner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RunSiteChecksHandler
{
    public function __construct(
        private readonly SiteCheckRepository $siteCheckRepository,
        private readonly CheckRunner $checkRunner,
    ) {
    }

    public function __invoke(RunSiteChecksMessage $message): void
    {
        $check = $this->siteCheckRepository->find($message->siteCheckId);
        if (null === $check) {
            return;
        }

        $this->checkRunner->run($check);
    }
}
