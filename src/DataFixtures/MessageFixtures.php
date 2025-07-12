<?php

namespace App\DataFixtures;

use App\Entity\Message;
use App\Entity\User;
use App\Entity\Annonce;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Enum\Annonce\Status\AnnonceStatus;
use Faker\Factory;

class MessageFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Créer quelques utilisateurs
        $user1 = new User();
        $user1->setEmail('alice@example.com')
            ->setAddress($faker->address)
            ->setPhoneNumber($faker->phoneNumber)
            ->setUsername('alice')
            ->setPassword('password1');
        $manager->persist($user1);

        $user2 = new User();
        $user2->setEmail('bob@example.com')
            ->setUsername('bob')
            ->setAddress($faker->address)
            ->setPhoneNumber($faker->phoneNumber)
            ->setPassword('password2');
        $manager->persist($user2);

        // Créer une annonce
        $annonce = new Annonce();
        $annonce->setTitre('Troc de vélo');
        $annonce->setDescription('Un vélo en bon état.');
        $annonce->setImage('velo.jpg');
        $annonce->setUser($user1); // propriétaire
        $annonce->setCreatedAt(new \DateTimeImmutable());
        $annonce->setStatus(AnnonceStatus::PUBLISHED);
        $manager->persist($annonce);

        // Créer un message
        $message = new Message();
        $message->setSender($user2);
        $message->setReceiver($user1);
        $message->setAnnonce($annonce);
        $message->setContent("Bonjour, est-ce que le vélo est toujours disponible ?");
        $message->setCreatedAt(new \DateTime());
        $manager->persist($message);

        $manager->flush();
    }
}
