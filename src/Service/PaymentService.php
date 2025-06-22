<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\PaymentException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function payment(User $user, Course $course): Transaction
    {
        $this->em->beginTransaction();
        try {
            if ($user->getBalance() < $course->getPrice()) {
                throw new PaymentException('На счету недостаточно средств');
            }

            $user->setBalance($user->getBalance() - $course->getPrice());
            $this->em->persist($user);

            $transaction = new Transaction();
            $currentDate = new \DateTime();
            $courseType = $course->getTypeName();

            $transaction->setCourse($course);
            $transaction->setAmount($course->getPrice() ?? 0.0);
            $transaction->setClient($user);
            $transaction->setType(Transaction::TYPE_NAMES['payment']);
            $transaction->setCreatedAt($currentDate);

            if ($courseType === "rent") {
                $transaction->setExpiresAt($currentDate->add(new \DateInterval('P1W')));
            }

            $this->em->persist($transaction);
            $this->em->flush();

            $this->em->commit();

            return $transaction;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function deposit(User $user, float $amount): Transaction
    {
        $this->em->beginTransaction();
        try {
            $transaction = new Transaction();
            $currentDate = new \DateTime();

            $transaction->setClient($user);
            $transaction->setType(Transaction::TYPE_NAMES['deposit']);
            $transaction->setAmount($amount);
            $transaction->setCreatedAt($currentDate);

            $user->setBalance($user->getBalance() + $amount);

            $this->em->persist($user);
            $this->em->persist($transaction);
            $this->em->flush();

            $this->em->commit();

            return $transaction;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}
