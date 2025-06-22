<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use App\Repository\TransactionRepository;
use App\Service\Twig;
use App\Command\PaymentReportCommand;

class PaymentReportCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
    }

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

        // Мок репозитория транзакций
        $transactionRepo = $this->createMock(TransactionRepository::class);
        $transactionRepo->method('getMonthlyReportData')
            ->willReturn([
                'totalAmount' => 1000.0,
                'purchasedCourses' => 5,
                'rentedCourses' => 3
            ]);

        // Мок Twig
        $twig = $this->createMock(Twig::class);
        $twig->method('render')
            ->willReturn('<html>Monthly Report</html>');

       
        $command = new PaymentReportCommand(
            $transactionRepo,
            $mailer,
            $twig,
            'reports@study-on.local'
        );

      
        $application = new Application(self::$kernel);
        $application->add($command); 
        
        $command = $application->find('payment:report');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Проверяем, что письмо было отправлено
        $this->assertCount(1, $sentEmails);

      
        $this->assertInstanceOf(\Symfony\Component\Mime\Email::class, $sentEmails[0]);
        
     
        $this->assertStringContainsString(
            'Отчет для',
            $sentEmails[0]->getSubject()
        );
        
       
        $this->assertEquals(
            'reports@study-on.local',
            $sentEmails[0]->getTo()[0]->getAddress()
        );

       
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Отчет отправлен на reports@study-on.local', $output);
    }
}