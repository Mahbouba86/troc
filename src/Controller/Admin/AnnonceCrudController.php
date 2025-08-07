<?php

namespace App\Controller\Admin;

use App\Entity\Annonce;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Enum\Annonce\Status\AnnonceStatus;

class AnnonceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Annonce::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('titre'),
            TextEditorField::new('description'),

            ChoiceField::new('status')
                ->setChoices([
                    'Disponible' => AnnonceStatus::AVAILABLE,
                    'Réservé' => AnnonceStatus::RESERVED,
                    'Terminé' => AnnonceStatus::FINISHED,
                ])
                ->renderAsBadges([
                    AnnonceStatus::AVAILABLE->value => 'success',  // vert
                    AnnonceStatus::RESERVED->value => 'warning',  // orange
                    AnnonceStatus::FINISHED->value => 'danger',   // rouge
                ])
                ->formatValue(fn ($value, $entity) => $value instanceof AnnonceStatus ? $value->value : (string) $value),

            ImageField::new('image')
                ->setBasePath('uploads/photos') // pour affichage dans EasyAdmin
                ->setUploadDir('public/uploads/photos') // répertoire d'upload
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]') // nommage auto
                ->setRequired(false),
        ];
    }
}
