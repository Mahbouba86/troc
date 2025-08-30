<?php

namespace App\Form;

use App\Entity\Annonce;
use App\Entity\Category;
use App\Enum\Annonce\AnnonceStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnonceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('description')
            ->add('ville')
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
            ])
            ->add('photos', FileType::class, [
                'label' => 'Ajoutez des photos',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
            ]);

        // Champ "status" seulement en édition
        if ($options['is_edit']) {
            $builder->add('status', EnumType::class, [
                'class' => AnnonceStatus::class,
                'required' => true,
                'label' => 'Statut',
                'choices' => [
                    AnnonceStatus::AVAILABLE,
                    AnnonceStatus::RESERVED,
                    AnnonceStatus::FINISHED,
                ],
                'choice_label' => static function (AnnonceStatus $status) {
                    return match ($status) {
                        AnnonceStatus::AVAILABLE => 'Disponible',
                        AnnonceStatus::RESERVED  => 'Réservée',
                        AnnonceStatus::FINISHED  => 'Terminée',
                    };
                },
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Annonce::class,
            'is_edit'         => false,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'annonce_form',
        ]);
    }
}
