<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Check\CheckRegistry;
use App\Entity\Client;
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
        private readonly CheckRegistry $checkRegistry,
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

        $checkTarget = $this->resolveCheckTarget($check->getType(), $check->getConfig());

        $targetSuffix = $checkTarget ? ' ('.$checkTarget['value'].')' : '';
        $subject = match ($message->action) {
            'failure' => sprintf('[WATCHDOG] %s: %s - %s%s', $result->getStatus()->label(), $client->getName(), $check->getLabel(), $targetSuffix),
            'recovery' => sprintf('[WATCHDOG] RECOVERED: %s - %s%s', $client->getName(), $check->getLabel(), $targetSuffix),
            default => sprintf('[WATCHDOG] Alert: %s', $client->getName()),
        };

        $htmlBody = $this->twig->render('email/notification.html.twig', [
            'client' => $client,
            'check' => $check,
            'result' => $result,
            'action' => $message->action,
            'checkTarget' => $checkTarget,
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

    /**
     * @param array<string, mixed> $config
     * @return array{label: string, value: string}|null
     */
    private function resolveCheckTarget(string $type, array $config): ?array
    {
        if (!$this->checkRegistry->has($type)) {
            return null;
        }

        $impl = $this->checkRegistry->get($type);
        $label = $impl->getEmailTargetLabel();
        $value = $impl->resolveEmailTarget($config);

        if (null === $label || null === $value || '' === $value) {
            return null;
        }

        return ['label' => $label, 'value' => $value];
    }
}
