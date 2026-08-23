<?php

namespace App\Form\Iot;

use App\Entity\Iot\Moteur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoteurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('label')
            ->add('description')
            ->add('ecart_temperature_hc')
            ->add('ecart_temperature_hp')
            ->add('type_temperature', ChoiceType::class, [
                'choices' => [
                    'Balise' => 'balise',
                    'Température fixe' => 'fixe',
                ],
            ])
            ->add('balise', null, ['required' => false])
            ->add('temperature_fixe', null, ['required' => false])
            ->add('is_auto')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Moteur::class,
        ]);
    }
}
