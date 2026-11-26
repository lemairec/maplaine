<?php

namespace App\Entity\Gestion;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Décomposition d'une facture fournisseur par tag (montant HT uniquement).
 */
#[ORM\Table(name: 'facture_fournisseur_ligne')]
#[ORM\Entity]
class FactureFournisseurLigne
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

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Gestion\FactureFournisseur', inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public $facture;

    /**
     * @var string
     */
    #[ORM\Column(name: 'tag', type: 'string', length: 255, nullable: true)]
    public $tag;

    /**
     * Même convention de signe que FactureFournisseur::montantHT en base
     * (négatif pour une vente).
     *
     * @var float
     */
    #[ORM\Column(name: 'montantHT', type: 'float')]
    public $montantHT = 0;
}
