<?php

namespace App\DataFixtures\Helper\UserCreatorHelper;

use App\Entity\User;
use Faker\Factory;

class UserCreatorHelper
{
    public function createUser($roles = ['ROLE_USER']): User
    {
        $faker = Factory::create('fr_FR');
        $user = new User();
        return $user->setEmail($faker->unique()->safeEmail())
            ->setUsername(substr($faker->unique()->userName(), 0, 15))
            ->setAddress($faker->address())
            ->setPhoneNumber($faker->phoneNumber())
            ->setRoles($roles)
            ->setIsVerified(true)
            ->setCodePostal($faker->postcode())
            ->setVille($faker->city())
            ->setAddress($faker->address());
    }
}
