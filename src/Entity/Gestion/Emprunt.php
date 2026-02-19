<?php

namespace App\Entity\Gestion;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\Gestion\EmpruntRepository')]
class Emprunt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Company')]
    #[ORM\JoinColumn(nullable: false)]
    public $company;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Campagne')]
    #[ORM\JoinColumn(nullable: true)]
    public $campagne;
    
    #[ORM\Column(type: 'date')]
    public $date;

    #[ORM\Column(type: 'integer')]
    public $montant;

     

    #[ORM\Column(type: 'string', length: 255)]
    public $name;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Gestion\Compte')]
    #[ORM\JoinColumn(nullable: true)]
    public $banque;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Gestion\Compte')]
    #[ORM\JoinColumn(nullable: true)]
    public $compte;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Gestion\Compte')]
    #[ORM\JoinColumn(nullable: true)]
    public $compteEmprunt;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Gestion\Compte')]
    #[ORM\JoinColumn(nullable: true)]
    public $compteInteret;

    #[ORM\Column(type: 'boolean')]
    public $enable = true;

    public $reste = 0;
}
