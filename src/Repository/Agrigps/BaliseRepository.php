<?php

namespace App\Repository\Agrigps;

use App\Entity\Agrigps\Balise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use Datetime;

/**
 * @method Balise|null find($id, $lockMode = null, $lockVersion = null)
 * @method Balise|null findOneBy(array $criteria, array $orderBy = null)
 * @method Balise[]    findAll()
 * @method Balise[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BaliseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Balise::class);
    }

    public function getAllByCompany($company)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.company = :company')
            ->setParameter('company', $company)
            ->orderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    

    public function getOneByMyId($my_id): ?Balise
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.myId = :my_id')
            ->setParameter('my_id', $my_id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByLatLon($latitude, $longitude): ?Balise
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.latitude = :latitude')
            ->andWhere('b.longitude = :longitude')
            ->setParameter('latitude', $latitude)
            ->setParameter('longitude', $longitude)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    function saveGpsData($data, $campagne){
        $lines = explode(PHP_EOL, $data);
        $em = $this->getEntityManager();
        
        print ("saveGpsData");
        foreach ($lines as $line) {
            //print($line);
            $rows = preg_split('/,/', $line);
            $name = $rows[0];
            $lat = (float)trim($rows[1]);
            $lon = (float)trim($rows[2]);
            

            $balise = $this->findOneByLatLon($lat, $lon);
            if($balise == NULL){
                $balise = new Balise();
                 $balise->my_datetime = new DateTime();
                $balise->company = $campagne->company;
                $balise->latitude = $lat;
                $balise->longitude = $lon;
                $balise->myId = strval($balise->latitude)."_".strval($balise->longitude);
               dump($balise);
            }
            $balise->name = $name;
            $em->persist($balise);
            $em->flush();
                //print_r($rows);
            
        }

    }
}
