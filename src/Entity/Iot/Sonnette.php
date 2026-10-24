<?php

namespace App\Entity\Iot;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\Iot\SonnetteRepository')]
class Sonnette
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

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    public $name;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Company')]
    #[ORM\JoinColumn(nullable: false)]
    public $company;

    #[ORM\Column(type: 'string', length: 255)]
    public $label = "";

    #[ORM\Column(type: 'text', nullable: true)]
    public $description = "";

    //dernier appel de l'objet (polling)
    #[ORM\Column(type: 'datetime', nullable: true)]
    public $last_update;

    //dernier declenchement
    #[ORM\Column(type: 'datetime', nullable: true)]
    public $last_ring;

    //sonnerie demandee, en attente du prochain poll de l'esp32
    #[ORM\Column(type: 'datetime', nullable: true)]
    public $ring_request;

    public $is_ok = false;

    public function calculate(){
        $this->is_ok = false;
        if($this->last_update){
            $this->is_ok = $this->last_update->format('d.m.Y') == date("d.m.Y");
        }
    }

    public function __toString ( ){
        return $this->name." ".$this->label;
    }
}
