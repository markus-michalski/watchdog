<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteCheck;
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
