<?php

namespace App\Entity\Iot;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Repository\Iot\BaliseRepository;


#[ORM\Entity(repositoryClass: BaliseRepository::class)]
class Balise
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

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    public $name;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Company')]
    #[ORM\JoinColumn(nullable: false)]
    public $company;


    #[ORM\Column(type: 'string', length: 255)]
    public $label = "";

    #[ORM\Column(type: 'text', nullable: true)]
    public $description = "";

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public $unity = "°C";

    #[ORM\Column(type: 'datetime', nullable: true)]
    public $last_update;

    #[ORM\Column(type: 'float', nullable: true)]
    public $last_temp;

    #[ORM\Column(type: 'float', nullable: true)]
    public $offset = 0;

    #[ORM\Column(type: 'float', nullable: true)]
    public $scale = 0;

    #[ORM\Column(type: 'float', nullable: true)]
    public $last_calculate;

    #[ORM\Column(type: 'float', nullable: true)]
    public $my_min = -20;

    #[ORM\Column(type: 'float', nullable: true)]
    public $my_max = 50;

    public $diff = NULL;

    public function isInRange($value){
        if($this->my_min === NULL || $this->my_max === NULL){
            return true;
        }
        return $value !== NULL && $value >= $this->my_min && $value <= $this->my_max;
    }

    public function calculateBalise(){
        $this->last_calculate = $this->last_temp;
        if($this->offset){
            $this->last_calculate = $this->last_calculate - $this->offset;
        }
        if($this->scale){
            $this->last_calculate = $this->last_calculate * $this->scale;
        }

        $today = date("d.m.Y");
        $match_date = "";
        if($this->last_update){
            $match_date = $this->last_update->format('d.m.Y');
        }
        $this->is_balise_ok = false;
        if($today == $match_date) {
            if($this->last_temp > -50) {
                $this->is_balise_ok = true;
            }
        }
    }

    public function calculFor($value){
        $res = $value;
        if($this->offset){
            $res = $res - $this->offset;
        }
        if($this->scale){
            $res = $res * $this->scale;
        }
        return $res;
    }

    public function __toString ( ){
        return $this->name." ".$this->label;
    }

    public $is_balise_ok = false;

    
}
