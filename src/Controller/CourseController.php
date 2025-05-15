<?php

namespace App\Controller;

use App\Exception\PaymentException;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use App\Service\TransactionService;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/v1')]
class CourseController extends AbstractController
{
    private $removeFieldsToArray = ['id', 'transactions'];
    
    public function __construct(
        private CourseRepository $courseRepository,
    ) {
    }

    #[Route('/courses', name: 'api_courses', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses',
        description: "Получение списка всех курсов.",
        summary: "Список курсов",
        responses: [
            new OA\Response(
                response: 200,
                description: "Список курсов",
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', type: 'string', description: "Код курса"),
                            new OA\Property(property: 'price', type: 'integer', description: "Цена курса"),
                            new OA\Property(property: 'type', type: 'string', description: "Тип доступа"),
                        ]
                    ),
                    example: [
                        [
                            'code' => 'php',
                            'price' => 0.0,
                            'type' => 'free',
                        ],
                        [
                            'code' => 'js',
                            'price' => 1000.0,
                            'type' => 'rent',
                        ]
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Пользователь не авторизован"
            ),
            new OA\Response(
                response: 403,
                description: "Доступ запрещен"
            )
        ]
    )]
    #[OA\Tag(
        name: "Course"
    )]
    public function index(): JsonResponse
    {
        $courses = $this->courseRepository->findAll();
        $result = [];

        foreach ($courses as $course) {
            $item = $course->toArray();
            $item['type'] = $course->getTypeName();

            foreach ($this->removeFieldsToArray as $code) {
                unset($item[$code]);
            }

            $result[] = $item;
        }
        
        return new JsonResponse(
            $result,
            Response::HTTP_OK
        );
    }

    #[Route('/courses/{code}', name: 'api_course', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses/{code}',
        description: "Получение информации о курсе по его коду.",
        summary: "Информация о курсе",
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                description: 'Код курса',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Информация о курсе",
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'string', description: "Код курса"),
                        new OA\Property(property: 'price', type: 'integer', description: "Цена курса"),
                        new OA\Property(property: 'type', type: 'string', description: "Тип доступа"),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Курс не найден",
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', description: "Код ошибки"),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'course', type: 'string', description: "Описание ошибки")
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Tag(
        name: "Course"
    )]
    public function show(Request $request): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['code' => $request->get('code')]);

        if (empty($course)) {
            return new JsonResponse([
                'code' => 401,
                'errors' => [
                    'course' => 'Курс не найден'
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $course->toArray();
        $result['type'] = $course->getTypeName();

        foreach ($this->removeFieldsToArray as $code) {
            unset($result[$code]);
        }
        
        return new JsonResponse($result, Response::HTTP_OK);
    }

    #[Route('/courses/{code}/pay', name: 'api_course_pay', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/courses/{code}/pay',
        description: "Оплата курса",
        summary: "Оплата курса",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: "Код курса")
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Оплата прошла успешно",
        content: new OA\JsonContent(
            type: 'object',
            properties: []
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Ошибки в запросе",
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'integer', description: "Код ошибки"),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'course', type: 'string', description: "Описание ошибки")
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Пользователь не авторизован",
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'integer', description: "Код ошибки"),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'unauthorized',
                            type: 'string',
                            description: "Описание ошибки"
                        )
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 406,
        description: "Недостаточно средств на счету",
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'integer', description: "Код ошибки"),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'payment', type: 'string', description: "Описание ошибки")
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: "Доступ запрещен"
    )]

    #[OA\Tag(
        name: "Course"
    )]
    #[Security(name: "Bearer")]
    public function pay(
        Request $request,
        PaymentService $paymentService,
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
        
        $course = $this->courseRepository->findOneBy(['code' => $request->get('code')]);
        $user = $this->getUser();

        if (empty($course)) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => [
                    'course' => 'Курс не найден'
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($course->getTypeName() == 'free') {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => [
                    'course' => 'Курс бесплатный. Оплата не требуется.'
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // проверяю что пользователь не совершал актуальных действия покупки с этим курсом
        $transactions = $transactionService->filter([
            'client' => $user->getId(),
            'course_code' => $request->get('code'),
            'type' => 'payment',
            'skip_expired' => true
        ]);

        if (count($transactions) !== 0) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => [
                    'course' => 'Доступ к курсу актуален. Оплата не требуется.'
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $paymentService->payment($user, $course);
        } catch (PaymentException $e) { // ошибка оплаты
            $error = [
                'mes' => $e->getMessage(),
                'code' => Response::HTTP_NOT_ACCEPTABLE
            ];
        } catch (\Exception $e) {
            $error = [
                'mes' => 'Произошла непредвиденная ошибка. Повторите запрос позже.',
                'code' => Response::HTTP_BAD_REQUEST
            ];
        } finally {
            if (isset($error)) {
                return new JsonResponse([
                    'code' => $error['code'],
                    'errors' => [
                        'payment' => $error['mes']
                    ]
                ], $error['code']);
            }
        }

        return new JsonResponse($result, Response::HTTP_OK);
    }
}