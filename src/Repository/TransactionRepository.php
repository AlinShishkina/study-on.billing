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
                ->setParameter('now', new DateTime(), 'datetime');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Найти транзакции с окончанием срока действия в указанном диапазоне дат
     *
     * @param DateTime $start
     * @param DateTime $end
     * @return Transaction[]
     */
    public function findCoursesEndingSoon(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->andWhere('t.expiresAt BETWEEN :start AND :end')
            ->setParameter('type', Transaction::TYPE_NAMES['payment'])
            ->setParameter('start', $start, 'datetime')
            ->setParameter('end', $end, 'datetime')
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
                'c.code as course_code',
                't.type',
                'COUNT(t.id) as count',
                'SUM(t.amount) as amount',
            ])
            ->join('t.course', 'c')
            ->where('t.createdAt BETWEEN :start AND :end')
            ->andWhere('t.type = :type')
            ->setParameter('start', $startDate, 'datetime')
            ->setParameter('end', $endDate, 'datetime')
            ->setParameter('type', Transaction::TYPE_NAMES['payment'], Types::SMALLINT)
            ->groupBy('c.id', 't.type')
            ->getQuery()
            ->getArrayResult();
    }
}
