<?php

// src/DataFixtures/AnnonceFixtures.php
namespace App\DataFixtures;

use App\Entity\Annonce;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AnnonceFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $users = $manager->getRepository(User::class)->findAll();

        if (count($users) === 0) {
            throw new \Exception('Aucun utilisateur trouvé en base.');
        }

        for ($i = 0; $i < 20; $i++) {
            $annonce = new Annonce();
            $annonce->setTitre($faker->sentence(3));
            $annonce->setDescription($faker->paragraph());
            $annonce->setCreatedAt(new \DateTimeImmutable());

            $imageNumber = $faker->numberBetween(1, 11);
            $imageName = 'image' . $imageNumber . '.jpg';
            $annonce->setImage($imageName);

            $annonce->setStatus(\Enum\Annonce\Status\AnnonceStatus::AVAILABLE);
            $annonce->setUser($faker->randomElement($users));

            $manager->persist($annonce);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
