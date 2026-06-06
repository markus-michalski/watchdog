<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AlertState;
use App\Entity\SiteCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlertState>
 */
class AlertStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertState::class);
    }

    public function findOrCreateForCheck(SiteCheck $check): AlertState
    {
        $state = $this->findOneBy(['check' => $check]);

        if ($state === null) {
            $state = new AlertState();
            $state->setCheck($check);
            $this->getEntityManager()->persist($state);
        }

        return $state;
    }
}
