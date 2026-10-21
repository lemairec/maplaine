<?php

namespace App\Form\Iot;

use App\Entity\Iot\Balise;
use App\Entity\Iot\Emplacement;
use App\Entity\Iot\Moteur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmplacementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $company = $options['company'];
        $forCompany = function ($er) use ($company) {
            return $er->createQueryBuilder('p')
                ->where('p.company = :company')
                ->orderBy('p.label')
                ->setParameter('company', $company);
        };

        $builder
            ->add('name')
            ->add('description')
            ->add('balises', EntityType::class, [
                'class' => Balise::class,
                'query_builder' => $forCompany,
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('balise_ext', EntityType::class, [
                'class' => Balise::class,
                'query_builder' => $forCompany,
                'required' => false,
            ])
            ->add('moteur', EntityType::class, [
                'class' => Moteur::class,
                'query_builder' => $forCompany,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Emplacement::class,
            'company' => null,
        ]);
    }
}
