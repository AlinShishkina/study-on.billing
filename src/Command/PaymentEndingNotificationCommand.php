<?php

namespace App\Command;

use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use DateTime;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Sends notifications about courses ending soon (within 7 days)'
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private CourseRepository $courseRepository,
        private MailerInterface $mailer,
        private Twig $twig
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new DateTime();
        $in7days = (clone $now)->modify('+7 days');

        $endingCourses = $this->transactionRepository->findCoursesEndingSoon($now, $in7days);

        if (empty($endingCourses)) {
            $output->writeln('No courses ending within the next 7 days.');
            return Command::SUCCESS;
        }

        $usersCourses = [];
        foreach ($endingCourses as $transaction) {
            $user = $transaction->getClient();
            $course = $transaction->getCourse();

            if (!$course) {
                continue;
            }

            if (!isset($usersCourses[$user->getId()])) {
                $usersCourses[$user->getId()] = [
                    'user' => $user,
                    'courses' => [],
                ];
            }

            $usersCourses[$user->getId()]['courses'][] = [
                'code' => $course->getCode(),
                'expires_at' => $transaction->getExpiresAt(),
            ];
        }

        foreach ($usersCourses as $userData) {
            $user = $userData['user'];
            $coursesInfo = [];
            
            foreach ($userData['courses'] as $course) {
                $formattedDate = $course['expires_at']->format('d.m.Y H:i');
                $coursesInfo[] = sprintf(
                    '%s действует до %s',
                    $course['code'],
                    $formattedDate
                );
            }
            
            $message = sprintf(
                "Уважаемый клиент! У вас есть курсы, срок аренды которых подходит к концу: %s.",
                implode('. ', $coursesInfo)
            );
            
            $output->writeln(sprintf(
                "User: %s (%s)\n%s",
                $user->getEmail(),
                $user->getId(),
                $message
            ));
        }

        foreach ($usersCourses as $userData) {
            $html = $this->twig->render('email/ending_notification.html.twig', [
                'user' => $userData['user'],
                'courses' => $userData['courses'],
            ]);

            $email = (new Email())
                ->from('no-reply@study-on.local')
                ->to($userData['user']->getEmail())
                ->subject('Notification About Courses Ending Soon')
                ->html($html);

            $this->mailer->send($email);
        }

        $output->writeln(sprintf('Sent %d notifications', count($usersCourses)));

        return Command::SUCCESS;
    }
}