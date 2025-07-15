<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;

class SearchAnnonceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ville', TextType::class, [
                'required' => false,
                'label' => 'Ville',
                'attr' => ['placeholder' => 'Ex : Lyon']
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Toutes les catégories',
                'choice_label' => 'name',
                'label' => 'Catégorie',
            ]);
    }
}

