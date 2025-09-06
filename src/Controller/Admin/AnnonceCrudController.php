<?php

namespace App\Controller\Admin;

use App\Entity\Annonce;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use App\Enum\Annonce\AnnonceStatus;

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
                    'En attente' => AnnonceStatus::PENDING,                ])
                ->formatValue(fn ($value, $entity) => $value instanceof AnnonceStatus ? $value->value : (string) $value),

        ];
    }
}
