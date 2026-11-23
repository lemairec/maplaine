<?php

namespace App\Repository\Iot;

use App\Entity\Iot\Temperature;
use DateTime;
/**
 * @method SiloTemperature|null find($id, $lockMode = null, $lockVersion = null)
 * @method SiloTemperature|null findOneBy(array $criteria, array $orderBy = null)
 * @method SiloTemperature[]    findAll()
 * @method SiloTemperature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TemperatureRepository extends \Doctrine\ORM\EntityRepository
{
    function roundTo15Min(\DateTime $dt) {
        $s = 15 * 60;
        $dt->setTimestamp($s * (int) floor($dt->getTimestamp() / $s));
        return $dt;
    }

    function roundTo60Min(\DateTime $dt) {
        $s = 15 * 60;
        $dt->setTimestamp($s * (int) floor($dt->getTimestamp() / $s));
        return $dt;
    }

    function work_order($balise){
        $res = $this->createQueryBuilder('p')
            ->where('p.balise = :balise')
            ->andWhere('p.rounded_datetime is NULL')
            ->orderBy('p.datetime', 'ASC')
            ->setParameter('balise', $balise)
            ->setMaxResults(100)
            ->getQuery()->getResult();
        if(count($res) > 1){
            for($i = 0; $i<20; ++$i){
                if($i<count($res)){
                    $this->addTemperature($res[$i]);
                }
            }
        }
    }

    function getAllForBalise($balise){
        $this->work_order($balise);

        return $this->createQueryBuilder('p')
            ->where('p.balise = :balise')
            ->orderBy('p.datetime', 'DESC')
            ->setParameter('balise', $balise)
            ->getQuery()->getResult();
    }

    // Version légère pour les graphes multi-balises : tableaux (datetime, temp), pas d'entités
    function getRawForBalise($balise, $duree){
        return $this->createQueryBuilder('p')
            ->select('p.datetime', 'p.temp')
            ->where('p.balise = :balise')
            ->andWhere('p.datetime > :my_date')
            ->orderBy('p.datetime', 'ASC')
            ->setParameter('balise', $balise)
            ->setParameter('my_date', $this->dateForDuree($duree))
            ->getQuery()->getArrayResult();
    }

    function dateForDuree($duree){
        $date = new DateTime();
        if($duree == "all"){
            $date->modify('-24 month');
        } else if($duree == "2m"){
            $date->modify('-2 month');
        } else if($duree == "1m"){
            $date->modify('-1 month');
        } else if($duree == "6m"){
            $date->modify('-6 month');
        } else if($duree == "1w"){
            $date->modify('-7 day');
        } else if($duree == "1d"){
            $date->modify('-1 day');
        } else {
            $date->modify('-1 month');
        }
        return $date;
    }

    function getForBalise($balise, $duree){
        $date = new DateTime();
        if($duree == "all"){
            $date->modify('-24 month');
        } else if($duree == "2m"){
            $date->modify('-2 month');
        } else if($duree == "1m"){
            $date->modify('-1 month');
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
            ->where('p.balise = :balise')
            ->andWhere('p.datetime > :my_date')
            ->orderBy('p.datetime', 'DESC')
            ->setParameter('balise', $balise)
            ->setParameter('my_date', $date)
            ->getQuery()->getResult();
    }

    function getLast4($balise){
        $date = new DateTime();
        $date->modify('-1 month');
        $res = $this->createQueryBuilder('p')
            ->where('p.balise = :balise')
            ->andWhere('p.datetime > :my_date')
            ->orderBy('p.datetime', 'DESC')
            ->setParameter('balise', $balise)
            ->setParameter('my_date', $date)
            ->getQuery()->getResult();
        if(count($res) > 4){
            return $res[8];
        }
        return NULL;
    }

    function addTemperature($t){
        $em = $this->getEntityManager();

        $rounded_datetime = new \DateTime($t->datetime->format("Y-m-d H:i:s"));
        $rounded_datetime = $this->roundTo60Min($rounded_datetime);
        $t->rounded_datetime = $rounded_datetime;
        $olds = $this->createQueryBuilder('p')
            ->where('p.balise = :balise')
            ->andWhere('p.rounded_datetime = :rounded_datetime')
            ->setParameter('balise', $t->balise)
            ->setParameter('rounded_datetime', $rounded_datetime)
            ->getQuery()->getResult();
        $old = NULL;
        if(count($olds)>0){
            $old = $olds[0];
        }
        if($old == NULL){
            $em->persist($t);
            $em->flush();
        } else {
            $old_t = $old->datetime->getTimestamp()-$old->rounded_datetime->getTimestamp();
            $temp_t = $t->datetime->getTimestamp()-$t->rounded_datetime->getTimestamp();
            if($temp_t < $old_t){
                $em->remove($old);
                //print("delete ".$old->id);
                $em->persist($t);
            } else {
                if($t->id){
                    $em->remove($t);
                }
                //print("delete ".$t->id);
            }
            $em->flush();
        }

    }

    // /**
    //  * @return SiloTemperature[] Returns an array of SiloTemperature objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SiloTemperature
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
