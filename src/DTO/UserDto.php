<?php

namespace App\DTO;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserDto
{
    // TODO объъект не инициализируется
    /*
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }
    */
    
    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Email обязателен к заполнению.')]
    #[Assert\Email(message: 'Некорректный email.')]
    public ?string $username = null;

    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Пароль обязателен к заполнению.')]
    #[Assert\Length(min: 6, minMessage: 'Минимальная длинна пароля: 6')]
    public ?string $password = null;

    /*
    #[Assert\IsTrue(message: "Email должен быть уникальным.")]
    public function isUsernameUnique(): bool
    {
        // $em = $this->doctrine->getManager();
        $userRepository = $em->getRepository(User::class);
        $existingUser = $userRepository->findOneBy(['username' => $this->username]);
        return $existingUser === null;
    }
    */
}