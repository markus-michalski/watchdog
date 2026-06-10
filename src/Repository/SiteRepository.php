<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Site>
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    /** @return array<int, Site> */
    public function findAllActive(): array
    {
        /** @var array<int, Site> $results */
        $results = $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /** @return array<int, Site> */
    public function findAllWithChecks(): array
    {
        /** @var array<int, Site> $results */
        $results = $this->createQueryBuilder('s')
            ->leftJoin('s.checks', 'c')
            ->leftJoin('s.contacts', 'co')
            ->addSelect('c', 'co')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }
}
