<?php

namespace App\Entity\Gestion;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

use Symfony\Component\Uid\Uuid;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * FactureFournisseur
 *
 * @Vich\Uploadable
 */
#[ORM\Table(name: 'facture_fournisseur')]
#[ORM\Entity(repositoryClass: 'App\Repository\Gestion\FactureFournisseurRepository')]
class FactureFournisseur
{
    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->lignes = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Company')]
    #[ORM\JoinColumn(nullable: false)]
    public $company;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Campagne')]
    #[ORM\JoinColumn(nullable: true)]
    public $campagne;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    public $name;

    /**
     * @var string
     */
    #[ORM\Column(name: 'tag', type: 'string', length: 255, nullable: true)]
    public $tag;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'date', type: 'date')]
    public $date;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'paiement_date', type: 'date')]
    public $paiementDate = 0;

    /**
     * @var integer
     */
    #[ORM\Column(name: 'paiement_order', type: 'integer')]
    public $paiementOrder;

    /**
     * @var float
     */
    #[ORM\Column(name: 'montantHT', type: 'float')]
    public $montantHT;

    /**
     * @var float
     */
    #[ORM\Column(name: 'montantTTC', type: 'float')]
    public $montantTTC;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public $type;

    #[ORM\OneToMany(targetEntity: 'App\Entity\Achat', mappedBy: 'facture')]
    public $achats;

    #[ORM\OneToMany(targetEntity: 'App\Entity\Gestion\Commercialisation', mappedBy: 'facture')]
    public $commercialisations;

    #[ORM\OneToMany(targetEntity: 'App\Entity\Gestion\FactureFournisseurLigne', mappedBy: 'facture', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public $lignes;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Gestion\Compte')]
    #[ORM\JoinColumn(nullable: false)]
    public $compte;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Gestion\Compte')]
    #[ORM\JoinColumn(nullable: false)]
    public $banque;

    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     *
     * @Vich\UploadableField(mapping="facture_file", fileNameProperty="factureFileName")
     *
     * @var File
     */
    private $factureFile;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', name: 'brochure', length: 255, nullable: true)]
    private $factureFileName;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'date_export', type: 'date', nullable: true)]
    public $dateExport;

    /**
     * @var \DateTime
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    public $updatedAt;

    public function __toString ( ){
        $res = $this->date->format("Y-m-d")."_".$this->name;
        return $res;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|\Symfony\Component\HttpFoundation\File\UploadedFile $doc
     */
    public function setFactureFile(?File $factureFile = null): void
    {

        $this->updatedAt = new \DateTime('now');
        $this->factureFile = $factureFile;
    }

    public function getFactureFile(): ?File
    {
        return $this->factureFile;
    }

    public function getCompleteName(): ?string
    {
        $tags = [];
        foreach ($this->getMontantsParTag() as $l) {
            if ($l["tag"] && !in_array($l["tag"], $tags)) {
                $tags[] = $l["tag"];
            }
        }
        if (count($tags) > 0) {
            return $this->name." - ".implode(", ", $tags);
        } else {
            return $this->name;
        }
    }

    public function setFactureFileName(?string $factureFileName): void
    {
        $this->factureFileName = $factureFileName;
    }

    public function getFactureFileName(): ?string
    {
        return $this->factureFileName;
    }

    public function getFactureMyFileName(){
        if($this->factureFileName){
            $str = $this->name;
            $str = str_replace(" - ", '_', $str);
            $str = str_replace(' ', '_', $str);
            $str = str_replace('-', '', $str);
            $str = str_replace(',', '', $str);
            $str = str_replace('/', '_', $str);
            $str = str_replace('&', '_', $str);
            $str = str_replace('é', 'e', $str);
            $str = str_replace('è', 'e', $str);
            $str = strtolower($str);

            return $this->date->format('Ymd').'_'.$str.'.pdf';
        } else {
            return "";
        }
    }

    function getAchatsTotal(){
        $achats_total = 0;
        foreach ($this->achats as $a) {
            $achats_total+=$a->price_total;
        }
        return $achats_total;
    }

    function getCommercialisationsTotal(){
        $achats_total = 0;
        foreach ($this->commercialisations as $a) {
            $achats_total+=$a->price_total;
        }
        return $achats_total;
    }

    public function addLigne(FactureFournisseurLigne $ligne): void
    {
        $ligne->facture = $this;
        $this->lignes[] = $ligne;
    }

    public function removeLigne(FactureFournisseurLigne $ligne): void
    {
        $this->lignes->removeElement($ligne);
    }

    /**
     * Décomposition HT par tag : les lignes saisies, plus le reste
     * (montantHT - somme des lignes) affecté au tag de la facture.
     * Sans ligne, renvoie une seule entrée équivalente à l'ancien comportement.
     *
     * @return array<array{tag: ?string, montantHT: float}>
     */
    public function getMontantsParTag(): array
    {
        $res = [];
        $somme = 0;
        foreach ($this->lignes ?? [] as $l) {
            $res[] = ["tag" => $l->tag ?: $this->tag, "montantHT" => $l->montantHT];
            $somme += $l->montantHT;
        }
        $reste = $this->montantHT - $somme;
        if (count($res) == 0 || abs($reste) > 0.005) {
            $res[] = ["tag" => $this->tag, "montantHT" => $reste];
        }
        return $res;
    }

    function getLignesReste(){
        $somme = 0;
        foreach ($this->lignes ?? [] as $l) {
            $somme += $l->montantHT;
        }
        return $this->montantHT - $somme;
    }

    function getDateStr(){
        return $this->date->format('d/m/y');
    }

    function getCampagneStr(){
        if($this->campagne){
            return $this->campagne->name;
        } else {
            return "";
        }
    }

    function getPercentTVA(){
        if($this->montantHT != 0){
            return (($this->montantTTC-$this->montantHT)/$this->montantHT);
        } else {
            return 0;
        }

    }

}
