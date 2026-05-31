<?php

namespace App\Entity\Materiel;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'materiel_travail_parcelle')]
#[ORM\Entity]
class MaterielTravailParcelle
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Materiel\MaterielTravail', inversedBy: 'parcelles')]
    #[ORM\JoinColumn(nullable: false)]
    public $travail;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Parcelle')]
    #[ORM\JoinColumn(nullable: false)]
    public $parcelle;
}
