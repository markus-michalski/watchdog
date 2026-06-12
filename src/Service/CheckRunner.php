<?php

declare(strict_types=1);

namespace App\Service;

use App\Check\CheckRegistry;
use App\Entity\SiteCheck;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class CheckRunner
{
    public function __construct(
        private readonly CheckRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly AlertService $alertService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(SiteCheck $check): void
    {
        if (!$this->registry->has($check->getType())) {
            $this->logger->warning('No check implementation for type "{type}"', ['type' => $check->getType()]);

            return;
        }

        $this->logger->info('Running check "{type}" for client "{client}"', [
            'type' => $check->getType(),
            'client' => $check->getClient()->getName(),
        ]);

        $implementation = $this->registry->get($check->getType());
        $result = $implementation->run($check);

        $this->em->persist($result);
        $this->em->flush();

        $this->alertService->evaluate($check, $result);
    }
}
