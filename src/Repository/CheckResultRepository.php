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
        /** @var CheckResult|null $result */
        $result = $this->createQueryBuilder('r')
            ->where('r.check = :check')
            ->setParameter('check', $check)
            ->orderBy('r.checkedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /** @return array<int, CheckResult> */
    public function findRecentForCheck(SiteCheck $check, int $limit = 20): array
    {
        /** @var array<int, CheckResult> $results */
        $results = $this->createQueryBuilder('r')
            ->where('r.check = :check')
            ->setParameter('check', $check)
            ->orderBy('r.checkedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('r')
            ->delete()
            ->where('r.checkedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        return $count;
    }
}
