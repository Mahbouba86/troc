<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = ['Enfant', 'Bricolage', 'Beauté', 'Maison', 'Animaux', 'Alimentation', 'Multimédia',];

        foreach ($categories as $name) {
            $category = new Category();
            $category->setName($name); // ou setTitre(), selon ton entité
            $manager->persist($category);
        }

        $manager->flush();
    }
}

