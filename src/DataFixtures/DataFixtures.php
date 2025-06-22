<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DataFixtures extends Fixture
{
    private static $courses = [
        [
            'code' => 'php',
            'type' => 'free',
            'price' => 0,
        ],
        [
            'code' => 'js',
            'type' => 'rent',
            'price' => 1000
        ],
        [
            'code' => 'ruby',
            'type' => 'rent',
            'price' => 250
        ],
        [
            'code' => 'swift',
            'type' => 'buy',
            'price' => 2500
        ]
    ];

    private $transactions = [];

    public function __construct()
    {
        $this->transactions = [
            [
                "created_at" => (new \DateTime('2 days ago')),
                "type" => "payment",
                "course_code" => "php",
                "amount" => 2500,
                'expires_at' => null,
            ],
            [
                "created_at" => (new \DateTime()),
                "type" => "payment",
                "course_code" => "js",
                "expires_at" => (new \DateTime('+7 days')),
                "amount" => 1000,
            ],
            [
                "created_at" => (new \DateTime('5 days ago')),
                "type" => "deposit",
                "amount" => 100000,
                'expires_at' => null,
            ],
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Создаем курсы
        foreach (self::$courses as $course) {
            $courseEntity = new Course();
            $courseEntity->setCode($course['code']);
            $courseEntity->setPrice($course['price']);
            $courseEntity->setTypeName($course['type']);
            $manager->persist($courseEntity);
        }
        $manager->flush();

        // Получаем пользователя
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'user@email.example']);
        
        if (!$user) {
            throw new \RuntimeException('User not found! Make sure AppFixtures are loaded first.');
        }

        // Создаем транзакции
        foreach ($this->transactions as $transaction) {
            $transactionEntity = new Transaction();
            $transactionEntity
                ->setAmount($transaction['amount'])
                ->setTypeName($transaction['type'])
                ->setClient($user)
                ->setCreatedAt($transaction['created_at']);
            
            if ($transaction['expires_at']) {
                $transactionEntity->setExpiresAt($transaction['expires_at']);
            }

            if (isset($transaction['course_code'])) {
                $course = $manager->getRepository(Course::class)->findOneBy(['code' => $transaction['course_code']]);
                if ($course) {
                    $transactionEntity->setCourse($course);
                }
            }

            $manager->persist($transactionEntity);
        }

        $manager->flush();
    }
}