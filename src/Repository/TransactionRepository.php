<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Получить отфильтрованные транзакции по параметрам
     *
     * @param string $username Email пользователя
     * @param int|null $type Тип транзакции (число)
     * @param string|null $courseCode Код курса
     * @param bool $skipExpired Пропускать просроченные (expired) транзакции
     * @return Transaction[]
     */
    public function getFilteredTransactions(
        string $username,
        ?int $type = null,
        ?string $courseCode = null,
        bool $skipExpired = false
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.course', 'c')
            ->innerJoin('t.client', 'u', Join::WITH, 'u.email = :username')
            ->setParameter('username', $username);

        if ($type !== null) {
            $qb->andWhere('t.type = :transactionType')
                ->setParameter('transactionType', $type, Types::SMALLINT);
        }

        if ($courseCode !== null) {
            $qb->andWhere('c.code = :code')
                ->setParameter('code', $courseCode);
        }

        if ($skipExpired) {
            $qb->andWhere('t.expiresAt > :now OR t.expiresAt IS NULL')
                ->setParameter('now', new DateTime(), Types::DATETIME_IMMUTABLE);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Найти транзакции с окончанием срока действия в ближайшее время (например, в течение суток)
     *
     * @return Transaction[]
     */
    public function findCoursesEndingSoon(): array
    {
        $start = new DateTime();
        $end = (clone $start)->modify('+1 day');

        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->andWhere('t.expiresAt BETWEEN :start AND :end')
            ->setParameter('type', Transaction::TYPE_NAMES['payment']) // или конкретное число типа платежа
            ->setParameter('start', $start, Types::DATETIME_IMMUTABLE)
            ->setParameter('end', $end, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить отчет по транзакциям за месяц
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return array
     */
    public function getMonthlyReportData(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
{
    return $this->createQueryBuilder('t')
        ->select([
            'c.code as course_code',  // заменить title на code
            't.type',
            'COUNT(t.id) as count',
            'SUM(t.amount) as amount',
        ])
        ->join('t.course', 'c')
        ->where('t.createdAt BETWEEN :start AND :end')
        ->andWhere('t.type = :type')
        ->setParameter('start', $startDate, Types::DATETIME_IMMUTABLE)
        ->setParameter('end', $endDate, Types::DATETIME_IMMUTABLE)
        ->setParameter('type', Transaction::TYPE_NAMES['payment'], Types::SMALLINT)
        ->groupBy('c.id', 't.type')
        ->getQuery()
        ->getArrayResult();
}
}