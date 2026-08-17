<?php

namespace App\Form\Iot;

use App\Entity\Iot\Balise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BaliseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', null, [
                'disabled' => true,
            ])
            ->add('label')
            ->add('description')
            ->add('unity')
            ->add('my_offset')
            ->add('my_version')
            ->add('wifi')
            ->add('scale')
            ->add('my_min')
            ->add('my_max')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Balise::class,
        ]);
    }
}
