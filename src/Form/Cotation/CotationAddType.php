<?php

namespace App\Form\Cotation;

use App\Entity\Cotation\Cotation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;


use App\Entity\Cotation\CotationProduit;


use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class CotationAddType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('source')
            ->add('campagne')
            ->add('date')
            ->add('produit1', EntityType::class, array(
                'class'        => CotationProduit::class,
                'choices' => $options['produits'],
                'required' => false
            ))
            ->add('value1', NumberType::class, ['required' => false])
            
            ->add('produit2', EntityType::class, array(
                'class'        => CotationProduit::class,
                'choices' => $options['produits'],
                'required' => false
            ))
            ->add('value2', NumberType::class, ['required' => false])
            
            ->add('produit3', EntityType::class, array(
                'class'        => CotationProduit::class,
                'choices' => $options['produits'],
                'required' => false
            ))
            ->add('value3', NumberType::class, ['required' => false])
            ->add('produit4', EntityType::class, array(
                'class'        => CotationProduit::class,
                'choices' => $options['produits'],
                'required' => false
            ))
            ->add('value4', NumberType::class, ['required' => false])
            ->add('produit5', EntityType::class, array(
                'class'        => CotationProduit::class,
                'choices' => $options['produits'],
                'required' => false
            ))
            ->add('value5', NumberType::class, ['required' => false])
            
        ;
        $builder->add('date', DateType::class, array(
            'widget' => 'single_text',
            'format' => 'dd/MM/yyyy',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'produits' => null
        ));
    }
}
