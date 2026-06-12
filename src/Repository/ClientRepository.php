<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /** @return array<int, Client> */
    public function findAllActive(): array
    {
        /** @var array<int, Client> $results */
        $results = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /** @return array<int, Client> */
    public function findAllWithChecks(): array
    {
        /** @var array<int, Client> $results */
        $results = $this->createQueryBuilder('c')
            ->leftJoin('c.checks', 'ch')
            ->leftJoin('c.contacts', 'co')
            ->leftJoin('c.urls', 'u')
            ->addSelect('ch', 'co', 'u')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }
}
