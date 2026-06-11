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

    /**
     * Returns a map of check_id => latest checkedAt for a set of checks.
     * Single query instead of N individual lookups.
     *
     * @param array<int, SiteCheck> $checks
     * @return array<int, \DateTimeImmutable>
     */
    public function findLatestTimestampsByChecks(array $checks): array
    {
        if ([] === $checks) {
            return [];
        }

        /** @var array<int, array{check_id: mixed, last_checked: mixed}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.check) AS check_id, MAX(r.checkedAt) AS last_checked')
            ->where('r.check IN (:checks)')
            ->setParameter('checks', $checks)
            ->groupBy('r.check')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $ts = $row['last_checked'];
            if (null === $ts) {
                continue;
            }
            $map[(int) $row['check_id']] = $ts instanceof \DateTimeImmutable
                ? $ts
                : new \DateTimeImmutable($ts);
        }

        return $map;
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
