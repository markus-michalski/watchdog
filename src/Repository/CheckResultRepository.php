<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * @param array{status?: string|null, from?: \DateTimeImmutable|null, to?: \DateTimeImmutable|null, http_code?: int|null} $filters
     *
     * @return array<int, CheckResult>
     */
    public function findFilteredForCheck(SiteCheck $check, array $filters, int $page = 1, int $perPage = 50): array
    {
        /** @var array<int, CheckResult> $results */
        $results = $this->buildFilteredQb($check, $filters)
            ->orderBy('r.checkedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * @param array{status?: string|null, from?: \DateTimeImmutable|null, to?: \DateTimeImmutable|null, http_code?: int|null} $filters
     */
    public function countFilteredForCheck(SiteCheck $check, array $filters): int
    {
        return (int) $this->buildFilteredQb($check, $filters)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array{status?: string|null, from?: \DateTimeImmutable|null, to?: \DateTimeImmutable|null, http_code?: int|null} $filters
     */
    private function buildFilteredQb(SiteCheck $check, array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.check = :check')
            ->setParameter('check', $check);

        if (!empty($filters['status'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $qb->andWhere('r.checkedAt >= :from')
               ->setParameter('from', $filters['from']);
        }

        if (!empty($filters['to'])) {
            // include the full "to" day
            $qb->andWhere('r.checkedAt < :to')
               ->setParameter('to', $filters['to']->modify('+1 day'));
        }

        if (isset($filters['http_code'])) {
            $qb->andWhere('r.statusCode = :http_code')
               ->setParameter('http_code', $filters['http_code']);
        }

        return $qb;
    }

    /**
     * Returns a map of check_id => latest checkedAt for a set of checks.
     * Single query instead of N individual lookups.
     *
     * @param array<int, SiteCheck> $checks
     *
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
            $checkId = $row['check_id'];
            if (!is_int($checkId) && !is_string($checkId)) {
                continue;
            }
            try {
                if ($ts instanceof \DateTimeInterface) {
                    $map[(int) $checkId] = \DateTimeImmutable::createFromInterface($ts);
                } elseif (is_string($ts)) {
                    $map[(int) $checkId] = new \DateTimeImmutable($ts);
                }
            } catch (\Exception) {
                continue;
            }
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

    public function deleteOlderThanForCheck(SiteCheck $check, \DateTimeImmutable $cutoff): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('r')
            ->delete()
            ->where('r.check = :check')
            ->andWhere('r.checkedAt < :cutoff')
            ->setParameter('check', $check)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        return $count;
    }
}
