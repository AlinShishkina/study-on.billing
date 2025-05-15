<?php

namespace App\Controller;

use App\DTO\UserDto;
use App\Entity\User;
use App\Exception\PaymentException;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/v1')]
class AuthController extends AbstractController
{
    private ValidatorInterface $validator;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/auth', name: 'api_auth', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth',
        description: "Входные данные: email и пароль. Выходные данные: JWT-токен и refresh_token в случае успеха, либо ошибки.",
        summary: "Аутентификация пользователя"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'password', type: 'string')
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Успешная аутентификация',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'refresh_token', type: 'string'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Невалидные данные',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials.')
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
    #[OA\Tag(name: "User")]
    public function auth(
        Request $request,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager
    ): JsonResponse {
        $serializer = SerializerBuilder::create()->build();
        try {
            $dto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');
        } catch (\Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Некорректный формат данных'
            ], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $jsonErrors = [];
            foreach ($errors as $error) {
                $jsonErrors[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => $jsonErrors,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => $dto->username]);

        // Используем passwordHasher для проверки пароля!
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $dto->password)) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Invalid credentials.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $jwtManager->create($user);

        // создаём refresh token
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $refreshTokenManager->save($refreshToken);

        return new JsonResponse([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/register',
        description: "Входные данные: email и пароль. Выходные данные: JWT-токен и refresh_token в случае успеха, либо ошибки.",
        summary: "Регистрация пользователя"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'password', type: 'string')
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Успешная регистрация',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: "string")),
                new OA\Property(property: 'refresh_token', type: 'string'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Невалидные данные',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 400),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: "string"))
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
    #[OA\Tag(name: "User")]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
        PaymentService $paymentService,
    ): JsonResponse {
        $serializer = SerializerBuilder::create()->build();
        try {
            $dto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');
        } catch (\Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => ['invalid_format' => 'Некорректный формат данных']
            ], Response::HTTP_BAD_REQUEST);
        }
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            $jsonErrors = [];
            foreach ($errors as $error) {
                $jsonErrors[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => $jsonErrors
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => $dto->username]);

        if ($user !== null) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'errors' => [
                    "username" => 'Email должен быть уникальным.'
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Создаём пользователя и хешируем пароль через passwordHasher
        $user = new User();
        $user->setEmail($dto->username);
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(0);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        // добавляем refresh token
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $refreshTokenManager->save($refreshToken);

        // добавляем баланс
        try {
            $paymentService->deposit($user, $_ENV['INITIAL_DEPOSIT']);
        } catch (PaymentException $e) {
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

        return new JsonResponse([
            'token' => $jwtManager->create($user),
            'roles' => $user->getRoles(),
            'code' => Response::HTTP_CREATED,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/users/current', name: 'api_current', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/users/current',
        description: "Входные данные - JWT-токен. Выходные данные: объект пользователя или ошибка.",
        summary: "Получение текущего пользователя"
    )]
    #[OA\Response(
        response: 200,
        description: 'Успешное получение пользователя',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: "string")),
                new OA\Property(property: 'balance', type: 'integer', example: 0)
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Невалидный JWT-токен',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 401),
                new OA\Property(property: 'errors', type: 'string', example: "Invalid JWT Token")
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
    #[OA\Tag(name: "User")]
    #[Security(name: "Bearer")]
    public function getCurrentUser(): JsonResponse
    {
        if ($this->getUser() === null) {
            return new JsonResponse([
                'code' => 401,
                "errors" => [
                    'unauthorized'=>'Пользователь неавторизован.'
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'username' => $this->getUser()->getEmail(),
            'roles' => $this->getUser()->getRoles(),
            'balance' => $this->getUser()->getBalance(),
        ], Response::HTTP_OK);
    }

    #[Route('/token/refresh', name: 'api_refresh', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/token/refresh',
        summary: "Обновление JWT-токена",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'refresh_token', type: 'string')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Успешное получение токена',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'refresh_token', type: 'string'),
            ],
            type: 'object'
        )
    )]
    #[OA\Tag(name: "User")]
    public function refresh(
        Request $request,
        RefreshTokenManagerInterface $refreshTokenManager,
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['refresh_token'])) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'error' => 'Refresh token is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = $refreshTokenManager->get($data['refresh_token']);
        if (!$refreshToken || !$refreshToken->getUsername()) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'error' => 'Invalid refresh token'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $refreshToken->getUsername()]);
        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'error' => 'User not found'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'refresh_token' => $refreshToken->getRefreshToken(),
        ], Response::HTTP_CREATED);
    }
}
