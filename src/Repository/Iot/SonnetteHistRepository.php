<?php

namespace App\Repository\Iot;

use DateTime;

class SonnetteHistRepository extends \Doctrine\ORM\EntityRepository
{
    function getLastPending($sonnette){
        return $this->createQueryBuilder('p')
            ->where('p.sonnette = :sonnette')
            ->andWhere('p.datetime_ring is NULL')
            ->orderBy('p.datetime', 'DESC')
            ->setParameter('sonnette', $sonnette)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    function getForSonnette($sonnette, $duree){
        $date = new DateTime();
        if($duree == "all"){
            $date->modify('-24 month');
        } else if($duree == "6m"){
            $date->modify('-6 month');
        } else if($duree == "1w"){
            $date->modify('-7 day');
        } else if($duree == "1d"){
            $date->modify('-1 day');
        } else {
            $date->modify('-1 month');
        }
        return $this->createQueryBuilder('p')
            ->where('p.sonnette = :sonnette')
            ->andWhere('p.datetime > :my_date')
            ->orderBy('p.datetime', 'DESC')
            ->setParameter('sonnette', $sonnette)
            ->setParameter('my_date', $date)
            ->getQuery()->getResult();
    }
}
