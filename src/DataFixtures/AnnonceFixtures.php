<?php

namespace App\DataFixtures;

use App\Entity\Annonce;
use App\Entity\User;
use App\Entity\Category;
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

        /** @var User[] $users */
        $users = $manager->getRepository(User::class)->findAll();
        /** @var Category[] $categories */
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
                ->setImage(sprintf('image%d.jpg', $faker->numberBetween(1, 11)))
                ->setStatus(AnnonceStatus::AVAILABLE)
                ->setUser($faker->randomElement($users))
                ->setCategory($faker->randomElement($categories))
                ->setVille($faker->city);

            if ($annonce->getVille() === null) {
                dd("VILLE NULL détectée pour l'annonce", $annonce);
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
