<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $factory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'bcrypt']
        ]);
        $hasher = $factory->getPasswordHasher('common');
        
        // Создание обычного пользователя
        $user = new User();
        $user->setEmail('user@email.example')
            ->setPassword($hasher->hash('password'))
            ->setBalance(300.0)
            ->setRoles(['ROLE_USER']);
        $manager->persist($user);

        // Создание администратора
        $user_admin = new User();
        $user_admin->setEmail('user_admin@email.example')
            ->setPassword($hasher->hash('password'))
            ->setBalance(1000.0)
            ->setRoles(['ROLE_SUPER_ADMIN']);
        $manager->persist($user_admin);

        $manager->flush();
    }
}