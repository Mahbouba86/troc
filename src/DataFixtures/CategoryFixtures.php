<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            ['Enfant', 'enfant.jpg'],
            ['Bricolage', 'bricolage.jpg'],
            ['Beauté', 'beaute.jpg'],
            ['Maison', 'maison.jpg'],
            ['Animaux', 'animaux.jpg'],
            ['Alimentation', 'alimentation.jpg'],
            ['Multimédia', 'multimedia.jpg'],
        ];

        foreach ($categories as [$name, $image]) {
            $category = new Category();
            $category->setName($name);
            $category->setImage($image);
            $manager->persist($category);
        }

        $manager->flush();
    }
}

