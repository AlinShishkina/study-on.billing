<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\VarDumper\VarDumper;

class TransactionService
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function filter(array $filters = []): array
    {
        $queryBuilder = $this->transactionRepository->createQueryBuilder('t');

        // 🔎 Показываем, что пришло
        VarDumper::dump(['filters' => $filters]);

        // Фильтр по типу: payment|deposit
        if (!empty($filters['type']) && isset(Transaction::TYPE_NAMES[$filters['type']])) {
            $type = Transaction::TYPE_NAMES[$filters['type']];
            $queryBuilder->andWhere('t.type = :type')
                ->setParameter('type', $type);

            VarDumper::dump(['filter_type_applied' => $type]);
        } elseif (!empty($filters['type'])) {
            VarDumper::dump(['unknown_type' => $filters['type']]);
        }

        // Фильтр по коду курса
        if (!empty($filters['course_code'])) {
            $queryBuilder->innerJoin('t.course', 'c')
                ->andWhere('c.code = :code')
                ->setParameter('code', $filters['course_code']);

            VarDumper::dump(['filter_course_code_applied' => $filters['course_code']]);
        }

        // Фильтр: пропустить просроченные транзакции
        if (!empty($filters['skip_expired'])) {
            $currentDate = new \DateTimeImmutable('now');
            $queryBuilder->andWhere('t.expiresAt > :currentDate OR t.expiresAt IS NULL')
                ->setParameter('currentDate', $currentDate);

            VarDumper::dump(['filter_skip_expired_applied' => $currentDate]);
        }

        // Фильтр по клиенту
        if (!empty($filters['client'])) {
            $queryBuilder->andWhere('t.client = :client')
                ->setParameter('client', $filters['client']);

            VarDumper::dump(['filter_client_applied' => $filters['client']]);
        }

        // Сортировка по дате создания
        $queryBuilder->orderBy('t.createdAt', 'DESC');

        // Вывод финального DQL-запроса
        VarDumper::dump(['dql' => $queryBuilder->getDQL(), 'params' => $queryBuilder->getParameters()]);

        $result = $queryBuilder->getQuery()->getResult();

        // Вывод результатов
        VarDumper::dump(['result_count' => count($result)]);

        return $result;
    }

    // Отладочный метод — посмотреть вообще все транзакции
    public function debugAllTransactions(): void
    {
        $all = $this->transactionRepository->findAll();
        VarDumper::dump(['all_transactions' => $all]);
    }
}
