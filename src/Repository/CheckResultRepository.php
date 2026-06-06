<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CheckResult>
 */
class CheckResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckResult::class);
    }

    public function findLatestForCheck(SiteCheck $check): ?CheckResult
    {
        return $this->createQueryBuilder('r')
            ->where('r.check = :check')
            ->setParameter('check', $check)
            ->orderBy('r.checkedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return CheckResult[] */
    public function findRecentForCheck(SiteCheck $check, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.check = :check')
            ->setParameter('check', $check)
            ->orderBy('r.checkedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.checkedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
