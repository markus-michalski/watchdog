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

    /** @return SiteCheck[] */
    public function findAllActiveWithSites(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.site', 's')
            ->leftJoin('s.contacts', 'co')
            ->addSelect('s', 'co')
            ->where('c.isActive = :active')
            ->andWhere('s.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
