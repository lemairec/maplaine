<?php

namespace App\Entity\Materiel;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'materiel_travail')]
#[ORM\Entity(repositoryClass: 'App\Repository\Materiel\MaterielTravailRepository')]
class MaterielTravail
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

    #[ORM\Column(type: 'date')]
    public $date;

    #[ORM\Column(type: 'datetime')]
    public $datetimeBegin;

    #[ORM\Column(type: 'datetime')]
    public $datetimeEnd;

    #[ORM\OneToMany(targetEntity: 'App\Entity\Materiel\MaterielTravailParcelle', mappedBy: 'travail', cascade: ['persist'])]
    public $parcelles;
}
