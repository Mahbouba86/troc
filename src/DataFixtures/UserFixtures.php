<?php

namespace App\DataFixtures;

use App\DataFixtures\Helper\UserCreatorHelper\UserCreatorHelper;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserCreatorHelper           $userCreator,
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Créer un utilisateur admin
        $admin = $this->userCreator->createUser(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminpass'));
        $manager->persist($admin);

        // Créer 10 utilisateurs aléatoires
        for ($i = 1; $i <= 10; $i++) {

            $user = $this->userCreator->createUser();
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
            $manager->persist($user);

            // Pour les autres fixtures (ex: Notifications)
            $this->addReference('user_' . $i, $user);
        }

        $manager->flush();
    }

}
