<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;

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

    public function __construct(private Connection $connection)
    {
        $this->transactions = [
            [
                "created_at" => date('Y-m-dTH:i:s', time() - 2 * 24 * 60 * 60),
                "type" => "payment",
                "course_code" => "php",
                "amount" => 2500,
                'expires_at' => null,
            ],
            [
                "created_at" => date('Y-m-dTH:i:s', time()),
                "type" => "payment",
                "course_code" => "js",
                "expires_at" => date('Y-m-dTH:i:s', time() + 7 * 24 * 60 * 60),
                "amount" => 1000,
            ],
            [
                "created_at" => date('Y-m-dTH:i:s', time() - 5 * 24 * 60 * 60),
                "type" => "deposit",
                "amount" => 100000,
                'expires_at' => null,
            ],
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // обнуление сиквансов
        $sequences = ['course_id_seq', 'transaction_id_seq'];

        foreach ($sequences as $sequence) {
            $sql = sprintf("SELECT setval('%s', 1, false);", $sequence);
            $this->connection->executeQuery($sql);
        }
        
        foreach ($this::$courses as $course) {
            $courseEntity = new Course();
            $courseEntity->setCode($course['code']);
            $courseEntity->setPrice($course['price']);
            $courseEntity->setTypeName($course['type']);

            $manager->persist($courseEntity);
        }

        $manager->flush();

        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'user@email.example']);

        foreach ($this->transactions as $transaction) {
            $transactionEntity = new Transaction();
            $transactionEntity->setAmount($transaction['amount']);
            $transactionEntity->setTypeName($transaction['type']);
            $transactionEntity->setClient($user);
            $transactionEntity->setCreatedAt(new \DateTimeImmutable($transaction['created_at']));
            if ($transaction['expires_at']) {
                $transactionEntity->setExpiresAt(new \DateTimeImmutable($transaction['expires_at']));
            }

            if (isset($transaction['course_code'])) {
                $course = $manager->getRepository(Course::class)->findOneBy(['code' => $transaction['course_code']]);
                $transactionEntity->setCourse($course);
            }

            $manager->persist($transactionEntity);
        }

        $manager->flush();
    }
}