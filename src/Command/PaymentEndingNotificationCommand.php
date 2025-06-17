<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Sends notifications about courses ending soon'
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerInterface $mailer,
        private Twig $twig
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $endingCourses = $this->transactionRepository->findCoursesEndingSoon();

        $usersCourses = [];
        foreach ($endingCourses as $transaction) {
            $user = $transaction->getClient();
            $course = $transaction->getCourse();
            $expiresAt = $transaction->getExpiresAt();

            // Пропускаем транзакции с отсутствующими данными
            if (!$user || !$course || !$expiresAt) {
                continue;
            }

            $userId = $user->getId();
            if (!isset($usersCourses[$userId])) {
                $usersCourses[$userId] = [
                    'user' => $user,
                    'courses' => [],
                ];
            }

            $usersCourses[$userId]['courses'][] = [
                'title' => $course->getCode(),
                'expires_at' => $expiresAt,
            ];
        }

        $sentCount = 0;
        foreach ($usersCourses as $userData) {
            try {
                $html = $this->twig->render('email/ending_notification.html.twig', [
                    'user' => $userData['user'],
                    'courses' => $userData['courses'],
                ]);

                $email = (new Email())
                    ->from('no-reply@study-on.local')
                    ->to($userData['user']->getEmail())
                    ->subject('Уведомление о окончании аренды курса')
                    ->html($html);

                $this->mailer->send($email);
                $sentCount++;
            } catch (\Exception $e) {
                $output->writeln('Ошибка отправки: ' . $e->getMessage());
            }
        }

        $output->writeln(sprintf('Отправлено %d уведомлений', $sentCount));
        return Command::SUCCESS;
    }
}