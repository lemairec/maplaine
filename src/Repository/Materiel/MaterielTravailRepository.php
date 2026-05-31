<?php

namespace App\Repository\Materiel;

use App\Entity\Materiel\MaterielTravail;

class MaterielTravailRepository extends \Doctrine\ORM\EntityRepository
{
    public function findByMaterielCompanyDate($materiel, $company, \DateTime $date): ?MaterielTravail
    {
        return $this->createQueryBuilder('t')
            ->where('t.materiel = :materiel')
            ->andWhere('t.company = :company')
            ->andWhere('t.date = :date')
            ->setParameter('materiel', $materiel)
            ->setParameter('company', $company)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAllByMateriel($materiel): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.materiel = :materiel')
            ->setParameter('materiel', $materiel)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
