<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'produit')]
#[ORM\Entity(repositoryClass: 'App\Repository\ProduitRepository')]
class Produit
{
    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Company')]
    #[ORM\JoinColumn(nullable: false)]
    public $company;

    #[ORM\Column(name: 'complete_name', type: 'string', length: 255)]
    public $completeName;

    #[ORM\Column(type: 'string')]
    public $name;

    #[ORM\Column(type: 'string', nullable: true)]
    public $comment;

    #[ORM\Column(name: 'type', type: 'string', length: 255)]
    public $type;

    #[ORM\Column(name: 'bio', type: 'boolean', nullable: true)]
    public $bio = false;

    #[ORM\Column(name: 'unity', type: 'string', length: 255)]
    public $unity = "unité";

    #[ORM\Column(name: 'qty', type: 'float')]
    public $quantity = 0;

    #[ORM\Column(name: 'price', type: 'float')]
    public $price = 0;

    #[ORM\Column(name: 'disable', type: 'boolean')]
    public $disable = false;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\EphyProduit')]
    #[ORM\JoinColumn(name: 'ephy_produit', referencedColumnName: 'amm')]
    public $ephyProduit;

    #[ORM\Column(name: 'n', type: 'float')]
    public $engrais_n = 0;

    #[ORM\Column(name: 'p', type: 'float')]
    public $engrais_p = 0;

    #[ORM\Column(name: 'k', type: 'float')]
    public $engrais_k = 0;

    #[ORM\Column(name: 'mg', type: 'float')]
    public $engrais_mg = 0;

    #[ORM\Column(name: 's', type: 'float')]
    public $engrais_so3 = 0;

    public $engrais_perc = 50;

    public function getEngraisN(){
        return $this->engrais_n*$this->engrais_perc/100;
    }

    public function getEngraisP(){
        return $this->engrais_p*$this->engrais_perc/100;
    }

    public function getEngraisK(){
        return $this->engrais_k*$this->engrais_perc/100;
    }

    public function getEngraisMg(){
        return $this->engrais_mg*$this->engrais_perc/100;
    }

    public function getEngraisSO3(){
        return $this->engrais_so3*$this->engrais_perc/100;
    }


    #[ORM\Column(name: 'mo', type: 'float', nullable: true)]
    public $engrais_mo = 0;

     #[ORM\Column(name: 'cn', type: 'float', nullable: true)]
     public $engrais_cn = 0;

    public function __toString ( ){
        $res = $this->name;
        $res = $res." (".$this->unity.")";
        return $res;
    }

    public function isCMR(){
        if($this->ephyProduit){
            return $this->ephyProduit->isCMR();
        }
        return false;
    }


    public function getColor(){
        if($this->ephyProduit){
            return $this->ephyProduit->getColor();
        } else {
            return "";
        }
    }
    #[ORM\Column(type: 'string')]
    public $migration = "";

    public function getAmm(){
        if($this->ephyProduit){
            return $this->ephyProduit->amm;
        } else {
            return "";
        }
    }
}
