<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use App\Repository\TransactionRepository;
use App\Repository\CourseRepository;
use App\Service\Twig;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Course;
use DateTime;

class PaymentEndingNotificationCommandTest extends KernelTestCase
{
    public function testCommandCorrect(): void
    {
       
        $sentEmails = [];

        // Мок мейлера
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->method('send')
            ->willReturnCallback(function ($email) use (&$sentEmails) {
                $sentEmails[] = $email;
            });

     
        $user = new User();
        $user->setEmail('user@email.example');
        
        $course = new Course();
        $course->setCode('test-course');
        
        $transaction = new Transaction();
        $transaction->setClient($user);
        $transaction->setCourse($course);
        $transaction->setExpiresAt(new DateTime('+3 days'));
        
        // Мок репозитория транзакций
        $transactionRepo = $this->createMock(TransactionRepository::class);
        $transactionRepo->method('findCoursesEndingSoon')
            ->willReturn([$transaction]);

        // Мок репозитория курсов
        $courseRepo = $this->createMock(CourseRepository::class);
        
        // Мок Twig
        $twig = $this->createMock(Twig::class);
        $twig->method('render')
            ->willReturn('<html>Notification</html>');

        // Мокаем в контейнере
        self::bootKernel();
        $container = static::getContainer();
        $container->set(MailerInterface::class, $mailer);
        $container->set(TransactionRepository::class, $transactionRepo);
        $container->set(CourseRepository::class, $courseRepo);
        $container->set(Twig::class, $twig);

     
        $application = new Application(self::$kernel);
        $command = $application->find('payment:ending:notification');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        
        $this->assertEquals(1, count($sentEmails));

        
        $this->assertInstanceOf(\Symfony\Component\Mime\Email::class, $sentEmails[0]);
        
      
        $this->assertEquals(
            'Notification About Courses Ending Soon',
            $sentEmails[0]->getSubject()
        );
        
      
        $this->assertEquals(
            'user@email.example',
            $sentEmails[0]->getTo()[0]->getAddress()
        );

        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Sent 1 notifications', $output);
        $this->assertStringContainsString('test-course', $output);
    }
    
    public function testCommandNoCourses(): void
    {
        // Мок репозитория транзакций (пустой результат)
        $transactionRepo = $this->createMock(TransactionRepository::class);
        $transactionRepo->method('findCoursesEndingSoon')
            ->willReturn([]);

        
        self::bootKernel();
        $container = static::getContainer();
        $container->set(TransactionRepository::class, $transactionRepo);

       
        $application = new Application(self::$kernel);
        $command = $application->find('payment:ending:notification');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No courses ending within the next 7 days.', $output);
    }
}