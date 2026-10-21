<?php

namespace App\Repository\Iot;

class EmplacementRepository extends \Doctrine\ORM\EntityRepository
{
    function getAllForCompany($company){
        return $this->createQueryBuilder('p')
            ->where('p.company = :company')
            ->orderBy('p.name')
            ->setParameter('company', $company)
            ->getQuery()->getResult();
    }
}
