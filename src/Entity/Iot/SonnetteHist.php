<?php

namespace App\Entity\Iot;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\Index(name: 'sonnette_datetime_idx', columns: ['datetime'])]
#[ORM\Entity(repositoryClass: 'App\Repository\Iot\SonnetteHistRepository')]
class SonnetteHist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Iot\Sonnette')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public $sonnette;

    //heure de la demande
    #[ORM\Column(type: 'datetime')]
    public $datetime;

    //heure ou l'esp32 a recupere la sonnerie
    #[ORM\Column(type: 'datetime', nullable: true)]
    public $datetime_ring;
}
