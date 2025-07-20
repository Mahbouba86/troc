<?php

namespace App\DataFixtures;

use App\Entity\Message;
use App\Entity\Annonce;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class MessageFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $users = $manager->getRepository(User::class)->findAll();
        if (count($users) < 2) {
            throw new \Exception("Il faut au moins 2 utilisateurs.");
        }

        $user1 = $faker->randomElement($users);
        do {
            $user2 = $faker->randomElement($users);
        } while ($user1 === $user2);

        $annonces = $manager->getRepository(Annonce::class)->findAll();
        if (!$annonces) {
            throw new \Exception("Aucune annonce trouvée en base.");
        }

        $annonce = $faker->randomElement($annonces);

        $message = new Message();
        $message->setSender($user2);
        $message->setReceiver($user1);
        $message->setAnnonce($annonce);
        $message->setContent("Bonjour, est-ce que le service est toujours disponible ?");
        $message->setCreatedAt(new \DateTime());

        $manager->persist($message);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CategoryFixtures::class,
            AnnonceFixtures::class, // ✅ j'indiques que cette fixture doit passer après
        ];
    }
}
