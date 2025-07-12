<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Créer un utilisateur admin
        $admin = new User();
        $admin->setEmail('admin@example.com')
            ->setUsername('admin')
            ->setAddress($faker->address)
            ->setPhoneNumber($faker->phoneNumber)
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(true)
            ->setPassword($this->passwordHasher->hashPassword($admin, 'adminpass'));
        $manager->persist($admin);

        // Créer 10 utilisateurs aléatoires
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail($faker->unique()->safeEmail());

            // ✅ Correction ici pour ne pas dépasser 15 caractères
            $user->setUsername(substr($faker->unique()->userName(), 0, 15));

            $user->setAddress($faker->address());
            $user->setPhoneNumber($faker->phoneNumber());
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));

            $manager->persist($user);

            // Pour les autres fixtures (ex: Notifications)
            $this->addReference('user_' . $i, $user);
        }

        $manager->flush();
    }
}
