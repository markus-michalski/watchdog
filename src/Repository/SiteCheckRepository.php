<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\SiteCheck;
use App\Enum\CheckRunner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteCheck>
 */
class SiteCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteCheck::class);
    }

    /** @return array<int, SiteCheck> */
    public function findWithRetentionPolicy(): array
    {
        /** @var array<int, SiteCheck> $results */
        $results = $this->createQueryBuilder('c')
            ->where('c.retentionDays IS NOT NULL')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /** @return array<int, SiteCheck> */
    public function findDashboardChecks(): array
    {
        /** @var array<int, SiteCheck> $results */
        $results = $this->createQueryBuilder('c')
            ->join('c.client', 'cl')
            ->leftJoin('cl.contacts', 'co')
            ->addSelect('cl', 'co')
            ->where('c.isActive = :active')
            ->andWhere('cl.isActive = :active')
            ->andWhere('c.runner = :runner')
            ->setParameter('active', true)
            ->setParameter('runner', CheckRunner::Dashboard)
            ->getQuery()
            ->getResult();

        return $results;
    }

    /** @return array<int, SiteCheck> */
    public function findActiveByAgent(Agent $agent): array
    {
        /** @var array<int, SiteCheck> $results */
        $results = $this->createQueryBuilder('c')
            ->join('c.client', 'cl')
            ->where('c.agent = :agent')
            ->andWhere('c.isActive = :active')
            ->andWhere('cl.isActive = :active')
            ->andWhere('c.runner = :runner')
            ->setParameter('agent', $agent)
            ->setParameter('active', true)
            ->setParameter('runner', CheckRunner::Agent)
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Finds checks with run_now=true for an agent, regardless of client active status.
     * Used by the run-now endpoint — manual dispatch must work even for inactive clients.
     *
     * @return array<int, SiteCheck>
     */
    public function findRunNowByAgent(Agent $agent): array
    {
        /** @var array<int, SiteCheck> $results */
        $results = $this->createQueryBuilder('c')
            ->where('c.agent = :agent')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.runner = :runner')
            ->andWhere('c.runNow = :runNow')
            ->setParameter('agent', $agent)
            ->setParameter('active', true)
            ->setParameter('runner', CheckRunner::Agent)
            ->setParameter('runNow', true)
            ->getQuery()
            ->getResult();

        return $results;
    }

    /** @return array<int, SiteCheck> */
    public function findAllActiveWithClients(): array
    {
        /** @var array<int, SiteCheck> $results */
        $results = $this->createQueryBuilder('c')
            ->join('c.client', 'cl')
            ->leftJoin('cl.contacts', 'co')
            ->addSelect('cl', 'co')
            ->where('c.isActive = :active')
            ->andWhere('cl.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        return $results;
    }
}
