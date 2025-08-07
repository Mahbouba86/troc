<?php

namespace App\DataFixtures;

use App\Entity\Annonce;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\Photo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Enum\Annonce\Status\AnnonceStatus;

class AnnonceFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $users = $manager->getRepository(User::class)->findAll();
        $categories = $manager->getRepository(Category::class)->findAll();

        if (!$users) {
            throw new \RuntimeException('Aucun utilisateur trouvé. Lance d’abord les UserFixtures.');
        }
        if (!$categories) {
            throw new \RuntimeException('Aucune catégorie trouvée. Lance d’abord les CategoryFixtures.');
        }

        for ($i = 0; $i < 20; $i++) {
            $annonce = new Annonce();
            $annonce
                ->setTitre($faker->sentence(3))
                ->setDescription($faker->paragraph())
                ->setCreatedAt(new \DateTimeImmutable())
                ->setStatus(AnnonceStatus::AVAILABLE)
                ->setUser($faker->randomElement($users))
                ->setCategory($faker->randomElement($categories))
                ->setVille($faker->city);

            // Photo principale
            $mainPhoto = new Photo();
            $mainPhoto->setFilename('image' . $faker->numberBetween(1, 11) . '.jpg');
            $mainPhoto->setIsMain(true);
            $mainPhoto->setAnnonce($annonce);
            $manager->persist($mainPhoto);

            // Autres photos
            $galleryCount = $faker->numberBetween(2, 3);
            for ($j = 0; $j < $galleryCount; $j++) {
                $galleryPhoto = new Photo();
                $galleryPhoto->setFilename('image' . $faker->numberBetween(1, 11) . '.jpg');
                $galleryPhoto->setIsMain(false);
                $galleryPhoto->setAnnonce($annonce);
                $manager->persist($galleryPhoto);
            }

            $manager->persist($annonce);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CategoryFixtures::class,
        ];
    }
}
