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

class AnnonceReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', EnumType::class, [
            'class' => AnnonceStatus::class,
            'required' => true,
            'label' => 'Statut',
            'choices' => [
                AnnonceStatus::AVAILABLE,
                AnnonceStatus::RESERVED,
            ],
            'choice_label' => static function (AnnonceStatus $status) {
                return match ($status) {
                    AnnonceStatus::AVAILABLE => 'Disponible',
                    AnnonceStatus::RESERVED => 'Réservée',
                };
            },
        ]);
    }
}
