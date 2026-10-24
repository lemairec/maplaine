<?php

namespace App\Repository\Iot;

use App\Entity\Iot\Sonnette;

class SonnetteRepository extends \Doctrine\ORM\EntityRepository
{
    public function getOrCreate($company, $name){
        $sonnette = $this->createQueryBuilder('s')
            ->andWhere('s.company = :company')
            ->setParameter('company', $company)
            ->andWhere('s.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if($sonnette == NULL){
            $sonnette = new Sonnette();
            $sonnette->company = $company;
            $sonnette->name = $name;

            $em = $this->getEntityManager();
            $em->persist($sonnette);
            $em->flush();
        }

        return $sonnette;
    }

    function getAllForCompany($company){
        return $this->createQueryBuilder('p')
            ->where('p.company = :company')
            ->orderBy('p.label')
            ->setParameter('company', $company)
            ->getQuery()->getResult();
    }
}
