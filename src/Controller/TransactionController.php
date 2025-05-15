<?php

namespace App\Controller;

use App\Service\TransactionService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/v1')]
class TransactionController extends AbstractController
{
    #[Route('/transactions', name: 'api_transactions', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/transactions',
        description: "Получение списка транзакций пользователя.
        \nФильтрация транзакций возможна по следующим параметрам:
        \n - type (тип транзакции)
        \n - amount (сумма транзакции)
        \n - expires_at (дата истечения срока транзакции)
        \n - course_code (код курса, если тип транзакции - payment)",
        summary: "Транзакции пользователя"
    )]
    #[OA\Response(
        response: 200,
        description: "Список транзакций пользователя",
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'create_at', type: 'string'),
                    new OA\Property(property: 'type', type: 'string'),
                    new OA\Property(property: 'amount', type: 'number'),
                    new OA\Property(property: 'expires_at', type: 'string', nullable: true),
                    new OA\Property(property: 'course_code', type: 'string', nullable: true)
                ]
            ),
            example: [
                [
                    'id' => 1,
                    'create_at' => '2025-01-07T00:00:00',
                    'type' => 'payment',
                    'amount' => 100,
                    'expires_at' => '2025-05-08T00:00:00',
                    'course_code' => 'ABC123'
                ]
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Ошибка авторизации',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'integer'),
                new OA\Property(
                    property: 'errors',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'description', type: 'string'),
                        ]
                    ),
                ),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Ошибка сервера',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string')
            ],
            type: 'object'
        )
    )]
    #[OA\Tag(
        name: "Transaction"
    )]
    public function index(
        Request $request,
        TransactionService $transactionService,
    ): JsonResponse {
        if ($this->getUser() === null) {
            return new JsonResponse([
                'code' => 401,
                "errors" => [
                    'unauthorized'=>'Пользователь неавторизован.'
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        $filter = $request->get('filter', []);

        $transactions = $transactionService->filter(
            array_merge(
                $filter,
                ['client' => $this->getUser()->getId()]
            )
        );

        $result = [];

        foreach ($transactions as $transaction) {
            $item = [
                'id' => $transaction->getId(),
                'create_at' => date_format($transaction->getCreatedAt(), "Y-m-dTH:i:s"),
                'type' => $transaction->getTypeName(),
                'amount' => $transaction->getAmount(),
                'expires_at' => $transaction->getExpiresAt()
                    ? date_format($transaction->getExpiresAt(), "Y-m-dTH:i:s")
                    : null
            ];

            if ($transaction->getTypeName() == 'payment') {
                $item['course_code'] = $transaction->getCourse()->getCode();
            }

            $result[] = $item;
        }

        return new JsonResponse([
            $result
        ], Response::HTTP_OK);
    }
}