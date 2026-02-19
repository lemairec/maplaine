<?php

namespace App\Form\Gestion;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\Gestion\Compte;

class EmpruntType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name');
        $builder->add('enable');
        $builder->add('date', DateType::class, array(
            'widget' => 'single_text',
            'format' => 'dd/MM/yyyy',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
        ));
        $builder->add('montant');
        $builder->add('banque', EntityType::class, array(
            'class'        => Compte::class,
            'choice_label' => 'name',
            'choices' => $options['comptes'],
        ));
        $builder->add('compte', EntityType::class, array(
            'class'        => Compte::class,
            'choice_label' => 'name',
            'choices' => $options['comptes'],
        ));
        $builder->add('compteEmprunt', EntityType::class, array(
            'class'        => Compte::class,
            'choice_label' => 'name',
            'choices' => $options['comptes'],
        ));
        $builder->add('compteInteret', EntityType::class, array(
            'class'        => Compte::class,
            'choice_label' => 'name',
            'choices' => $options['comptes'],
        ));

    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'App\Entity\Gestion\Emprunt',
            'comptes' => null,
        ));
    }
}
