<?php

namespace App\Command;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:report',
    description: 'Generates monthly payment report'
)]
class PaymentReportCommand extends Command
{
    
    private const RENT_TYPE = 2;
    private const BUY_TYPE = 3;

    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerInterface $mailer,
        private Twig $twig,
        #[Autowire('%app.report_email%')]
        private string $reportEmail
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Начало текущего месяца
        $startDate = new \DateTimeImmutable('first day of this month midnight');
        // Конец текущего месяца
        $endDate = new \DateTimeImmutable('last day of this month 23:59:59');

        // Получаем все транзакции оплат за период
        $transactions = $this->transactionRepository->findByPeriodAndType(
            $startDate,
            $endDate,
            Transaction::TYPE_NAMES['payment']
        );

        

        $userReports = [];
        $generalReport = [];
        $totalAmount = 0;

        foreach ($transactions as $transaction) {
            $user = $transaction->getClient();
            $course = $transaction->getCourse();
            
       
            if (!$course) {
                continue;
            }
            
            $userId = $user->getId();
            $courseId = $course->getId();
            
        
            if (!isset($userReports[$userId])) {
                $userReports[$userId] = [
                    'user' => $user,
                    'courses' => [],
                ];
            }
            
           
            if (!isset($userReports[$userId]['courses'][$courseId])) {
                $userReports[$userId]['courses'][$courseId] = [
                    'course' => $course,
                    'count' => 0,
                    'sum' => 0.0,
                ];
            }
            
            
            $userReports[$userId]['courses'][$courseId]['count']++;
            $userReports[$userId]['courses'][$courseId]['sum'] += $transaction->getAmount();
            
            
            $courseKey = $courseId . '_' . $course->getType();
            if (!isset($generalReport[$courseKey])) {
                $generalReport[$courseKey] = [
                    'course_code' => $course->getCode(), 
                    'course_type' => $course->getType(),
                    'count' => 0,
                    'sum' => 0.0,
                ];
            }
            $generalReport[$courseKey]['count']++;
            $generalReport[$courseKey]['sum'] += $transaction->getAmount();
        }
        
        // Вывод в консоль
        $output->writeln('');
        $output->writeln('<comment>Отчет об оплаченных курсах за период</comment>');
        $output->writeln(sprintf(
            '<info>%s - %s</info>', 
            $startDate->format('d.m.Y'),
            $endDate->format('d.m.Y')
        ));
        $output->writeln('');

        foreach ($userReports as $userReport) {
            $user = $userReport['user'];
            $output->writeln(sprintf(
                '<comment>Пользователь: %s (ID: %d)</comment>',
                $user->getEmail(),
                $user->getId()
            ));
            
            // Таблица для пользователя
            $table = new Table($output);
            $table->setHeaders([
                '<question>Код курса</question>', 
                '<question>Тип курса</question>', 
                '<question>Количество</question>', 
                '<question>Сумма</question>'
            ]);

            $userTotal = 0;
            foreach ($userReport['courses'] as $courseData) {
                $course = $courseData['course'];
                
                
                $typeName = match ($course->getType()) {
                    self::RENT_TYPE => 'Аренда',
                    self::BUY_TYPE => 'Покупка',
                    default => 'Неизвестный'
                };
                
                $table->addRow([
                    $course->getCode(), 
                    $typeName,
                    $courseData['count'],
                    number_format($courseData['sum'], 2) . ' руб.'
                ]);
                
                $userTotal += $courseData['sum'];
                $totalAmount += $courseData['sum'];
            }

            $table->render();
            $output->writeln(sprintf(
                '<comment>Итого по пользователю: %s руб.</comment>', 
                number_format($userTotal, 2)
            ));
            $output->writeln('');
        }

        $output->writeln(sprintf(
            '<comment>Общая сумма: %s руб.</comment>', 
            number_format($totalAmount, 2)
        ));
        $output->writeln('');
        
        
        $html = $this->twig->render('email/monthly_report.html.twig', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reportData' => array_values($generalReport),
        ]);
        
       
        $email = (new Email())
            ->from('no-reply@study-on.local')
            ->to($this->reportEmail)
            ->subject('Отчет для ' . $startDate->format('m.Y'))
            ->html($html);
        
        $this->mailer->send($email);
        
        $output->writeln('Отчет отправлен на ' . $this->reportEmail);
        
        return Command::SUCCESS;
    }
}