<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Doctrine\DBAL\Connection;

class AppFixtures extends Fixture
{
    public function __construct(private Connection $connection)
    {
    }
    
    public function load(ObjectManager $manager): void
    {
        // обнуление сиквансов
        $sequences = ['billing_user_id_seq'];

        foreach ($sequences as $sequence) {
            $sql = sprintf("SELECT setval('%s', 1, false);", $sequence);
            $this->connection->executeQuery($sql);
        }
        
        $factory = new PasswordHasherFactory([
            'common' => ['algorithm' => 'bcrypt']
        ]);
        $hasher = $factory->getPasswordHasher('common');
        
        $user = new User();
        $user->setEmail('user@email.example')
            ->setPassword(
                $hasher->hash('user@email.example')
            );
        $user->setBalance(300.0);
        $manager->persist($user);

        $user_admin = new User();
        $user_admin->setEmail('user_admin@email.example')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setPassword(
                $hasher->hash('user_admin@email.example')
            );
        $user_admin->setBalance(1000.0);
        $manager->persist($user_admin);

        $manager->flush();
    }
}