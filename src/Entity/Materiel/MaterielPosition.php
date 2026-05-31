<?php

namespace App\Entity\Materiel;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'materiel_position')]
#[ORM\Entity]
class MaterielPosition
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Company')]
    #[ORM\JoinColumn(nullable: false)]
    public $company;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Materiel\Materiel')]
    #[ORM\JoinColumn(nullable: false)]
    public $materiel;

    #[ORM\Column(type: 'datetime')]
    public $datetime;

    #[ORM\Column(type: 'float')]
    public $latitude;

    #[ORM\Column(type: 'float')]
    public $longitude;
}
