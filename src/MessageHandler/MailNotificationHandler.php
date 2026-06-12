<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Client;
use App\Message\MailNotificationMessage;
use App\Repository\AlertStateRepository;
use App\Repository\CheckResultRepository;
use App\Repository\ClientUrlRepository;
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
        private readonly ClientUrlRepository $clientUrlRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly EntityManagerInterface $em,
        private readonly string $mailerFrom,
    ) {
    }

    public function __invoke(MailNotificationMessage $message): void
    {
        $check = $this->siteCheckRepository->find($message->siteCheckId);
        $result = $this->checkResultRepository->find($message->checkResultId);

        if (null === $check || null === $result) {
            return;
        }

        // Eager-load contacts to avoid lazy-loading issues in Messenger worker context
        /** @var Client $client */
        $client = $this->em->createQueryBuilder()
            ->select('c', 'co')
            ->from(Client::class, 'c')
            ->leftJoin('c.contacts', 'co')
            ->where('c.id = :id')
            ->setParameter('id', $check->getClient()->getId())
            ->getQuery()
            ->getSingleResult();

        $contacts = $client->getContacts();

        if ($contacts->isEmpty()) {
            return;
        }

        $subject = match ($message->action) {
            'failure' => sprintf('[WATCHDOG] %s: %s - %s', $result->getStatus()->label(), $client->getName(), $check->getLabel()),
            'recovery' => sprintf('[WATCHDOG] RECOVERED: %s - %s', $client->getName(), $check->getLabel()),
            default => sprintf('[WATCHDOG] Alert: %s', $client->getName()),
        };

        $checkUrl = null;
        if ('http' === $check->getType()) {
            $clientUrlId = isset($check->getConfig()['client_url_id'])
                ? (int) $check->getConfig()['client_url_id']
                : null;
            if (null !== $clientUrlId) {
                $checkUrl = $this->clientUrlRepository->find($clientUrlId)?->getUrl();
            }
        }

        $htmlBody = $this->twig->render('email/notification.html.twig', [
            'client' => $client,
            'check' => $check,
            'result' => $result,
            'action' => $message->action,
            'checkUrl' => $checkUrl,
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
