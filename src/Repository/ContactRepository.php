<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /** @return array<int, Contact> */
    public function findAllWithSites(): array
    {
        /** @var array<int, Contact> $results */
        $results = $this->createQueryBuilder('c')
            ->leftJoin('c.sites', 's')
            ->addSelect('s')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }
}
