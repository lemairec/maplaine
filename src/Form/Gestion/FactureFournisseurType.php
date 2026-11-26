<?php

namespace App\Form\Gestion;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

use App\Entity\Campagne;
use App\Entity\Gestion\Compte;

class FactureFournisseurType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name');
        $builder->add('tag');
        $builder->add('date', DateType::class, array(
            'widget' => 'single_text',
            'format' => 'dd/MM/yyyy',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
        ));
        $builder->add('paiementDate', DateType::class, array(
            'widget' => 'single_text',
            'format' => 'dd/MM/yyyy',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
        ));
        $builder->add('paiementOrder');
        $builder->add('campagne', EntityType::class, array(
            'class'        => Campagne::class,
            'choices' => $options['campagnes'],
            'required' => false
        ));
        $builder->add('type', ChoiceType::class, array(
            'choices' => array(
                'Achat' => 'Achat',
                'Vente' => 'Vente',
            )

        ));
        $builder->add('montantHT')->add('montantTTC')->add('compte');
        $builder->add('banque', EntityType::class, array(
            'class'        => Compte::class,
            'choice_label' => 'name',
            'choices' => $options['banques'],
        ));
        $builder->add('compte', EntityType::class, array(
            'class'        => Compte::class,
            'choice_label' => 'name',
            'choices' => $options['comptes'],
        ));
        $builder->add('lignes', CollectionType::class, array(
            'entry_type' => FactureFournisseurLigneType::class,
            'entry_options' => array('label' => false),
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => false,
        ));
        $builder->add('factureFile', VichFileType::class, array(
            'required' => false,
            'allow_delete' => true,
        ));


    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'App\Entity\Gestion\FactureFournisseur',
            'banques' => null,
            'comptes' => null,
            'campagnes' => null
        ));
    }
}
