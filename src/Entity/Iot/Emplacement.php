<?php

namespace App\Entity\Iot;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;
use App\Repository\Iot\EmplacementRepository;

#[ORM\Entity(repositoryClass: EmplacementRepository::class)]
class Emplacement
{
    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->balises = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public $id;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    public $name;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Company')]
    #[ORM\JoinColumn(nullable: false)]
    public $company;

    #[ORM\Column(type: 'text', nullable: true)]
    public $description = "";

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public $produit;

    #[ORM\ManyToMany(targetEntity: 'App\Entity\Iot\Balise')]
    #[ORM\JoinTable(name: 'emplacement_balise')]
    #[ORM\OrderBy(['label' => 'ASC'])]
    public $balises;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Iot\Balise')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public $balise_ext;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Iot\Moteur')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public $moteur;

    public function calculate(){
        foreach($this->balises as $balise){
            $balise->calculateBalise();
        }
        if($this->balise_ext){
            $this->balise_ext->calculateBalise();
        }
        if($this->moteur){
            $this->moteur->calculate();
        }
    }

    public function getBalises(){
        return $this->balises;
    }

    public function addBalise(Balise $balise){
        if(!$this->balises->contains($balise)){
            $this->balises->add($balise);
        }
    }

    public function removeBalise(Balise $balise){
        $this->balises->removeElement($balise);
    }

    public function __toString ( ){
        return $this->name;
    }
}
