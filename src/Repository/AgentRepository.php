<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agent>
 */
class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    public function findByToken(string $rawToken): ?Agent
    {
        $agent = $this->findOneBy(['tokenHash' => hash('sha256', $rawToken)]);

        return ($agent?->verifyToken($rawToken)) ? $agent : null;
    }
}
