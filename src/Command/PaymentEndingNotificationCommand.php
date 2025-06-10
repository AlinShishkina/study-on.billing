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
            $user = $transaction->getBillingUser();
            $course = $transaction->getCourse();

            if (!isset($usersCourses[$user->getId()])) {
                $usersCourses[$user->getId()] = [
                    'user' => $user,
                    'courses' => [],
                ];
            }

            
            $expiresAt = $transaction->getExpiresAt();
            if ($expiresAt instanceof \DateTime) {
                $expiresAt = \DateTimeImmutable::createFromMutable($expiresAt);
            }

            $usersCourses[$user->getId()]['courses'][] = [
                'title' => $course->getCode(),
                'expires_at' => $expiresAt,
            ];
        }

        foreach ($usersCourses as $userData) {
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
        }

        $output->writeln(sprintf('Отправлено %d уведомлений', count($usersCourses)));

        return Command::SUCCESS;
    }
}
