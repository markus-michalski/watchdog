<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Site;
use App\Message\MailNotificationMessage;
use App\Repository\AlertStateRepository;
use App\Repository\CheckResultRepository;
use App\Repository\SiteCheckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class MailNotificationHandler
{
    public function __construct(
        private readonly SiteCheckRepository $siteCheckRepository,
        private readonly CheckResultRepository $checkResultRepository,
        private readonly AlertStateRepository $alertStateRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly EntityManagerInterface $em,
        private readonly string $mailerFrom,
    ) {}

    public function __invoke(MailNotificationMessage $message): void
    {
        $check = $this->siteCheckRepository->find($message->siteCheckId);
        $result = $this->checkResultRepository->find($message->checkResultId);

        if ($check === null || $result === null) {
            return;
        }

        // Eager-load contacts to avoid lazy-loading issues in Messenger worker context
        /** @var Site $site */
        $site = $this->em->createQueryBuilder()
            ->select('s', 'c')
            ->from(Site::class, 's')
            ->leftJoin('s.contacts', 'c')
            ->where('s.id = :id')
            ->setParameter('id', $check->getSite()->getId())
            ->getQuery()
            ->getSingleResult();

        $contacts = $site->getContacts();

        if ($contacts->isEmpty()) {
            return;
        }

        $subject = match ($message->action) {
            'failure' => sprintf('[WATCHDOG] %s: %s - %s', $result->getStatus()->label(), $site->getName(), $check->getLabel()),
            'recovery' => sprintf('[WATCHDOG] RECOVERED: %s - %s', $site->getName(), $check->getLabel()),
            default => sprintf('[WATCHDOG] Alert: %s', $site->getName()),
        };

        $htmlBody = $this->twig->render('email/notification.html.twig', [
            'site' => $site,
            'check' => $check,
            'result' => $result,
            'action' => $message->action,
        ]);

        foreach ($contacts as $contact) {
            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($contact->getEmail())
                ->subject($subject)
                ->html($htmlBody);

            $this->mailer->send($email);
        }

        // Update last alert sent timestamp
        $state = $this->alertStateRepository->findOrCreateForCheck($check);
        $state->setLastAlertSentAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
