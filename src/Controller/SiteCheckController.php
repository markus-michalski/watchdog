<?php

declare(strict_types=1);

namespace App\Controller;

use App\Check\CheckRegistry;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Form\SiteCheckType;
use App\Message\RunSiteChecksMessage;
use App\Repository\CheckResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/clients/{clientId}/checks', name: 'check_')]
class SiteCheckController extends AbstractController
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    #[Route('/new', name: 'new')]
    public function new(
        Request $request,
        #[MapEntity(id: 'clientId')]
        Client $client,
        EntityManagerInterface $em,
        CheckRegistry $registry,
    ): Response {
        $check = new SiteCheck();
        $check->setClient($client);

        $form = $this->createForm(SiteCheckType::class, $check);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $check->setConfig($this->buildConfig($request, $check->getType(), $registry));
            $em->persist($check);
            $em->flush();
            $this->cache->delete('watchdog_schedule');
            $this->addFlash('success', sprintf('Check "%s" added.', $registry->get($check->getType())->getLabel()));

            return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
        }

        return $this->render('check/form.html.twig', [
            'form' => $form,
            'client' => $client,
            'check' => $check,
            'title' => 'Add Check',
            'schemas' => $registry->getAllSchemas(),
            'clientUrls' => $client->getUrls(),
            'runnerModes' => $registry->getRunnerModes(),
        ]);
    }

    #[Route('/{checkId}/edit', name: 'edit')]
    public function edit(
        Request $request,
        #[MapEntity(id: 'clientId')]
        Client $client,
        #[MapEntity(id: 'checkId')]
        SiteCheck $check,
        EntityManagerInterface $em,
        CheckRegistry $registry,
    ): Response {
        $form = $this->createForm(SiteCheckType::class, $check);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $check->setConfig($this->buildConfig($request, $check->getType(), $registry));
            $em->flush();
            $this->cache->delete('watchdog_schedule');
            $this->addFlash('success', 'Check updated.');

            return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
        }

        return $this->render('check/form.html.twig', [
            'form' => $form,
            'client' => $client,
            'check' => $check,
            'title' => 'Edit Check',
            'schemas' => $registry->getAllSchemas(),
            'clientUrls' => $client->getUrls(),
            'runnerModes' => $registry->getRunnerModes(),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildConfig(Request $request, string $type, CheckRegistry $registry): array
    {
        if (!$registry->has($type)) {
            return [];
        }

        $schema = $registry->get($type)->getConfigSchema();
        $config = [];

        foreach ($schema as $field) {
            if ('client_url_multiselect' === $field['type']) {
                $values = $request->request->all('check_config_'.$field['name']);
                $config[$field['name']] = array_values(array_filter(
                    array_map(static fn (mixed $v): string => is_string($v) ? trim($v) : '', $values),
                    static fn (string $v): bool => '' !== $v,
                ));

                continue;
            }

            $raw = $request->request->get('check_config_'.$field['name']);

            if (null === $raw || '' === $raw) {
                continue;
            }

            if ('expected_status_codes' === $field['name']) {
                $config[$field['name']] = array_map(
                    'intval',
                    array_filter(array_map('trim', explode(',', (string) $raw)))
                );
            } elseif ('float' === $field['type']) {
                $config[$field['name']] = (float) $raw;
            } elseif (in_array($field['type'], ['number', 'duration', 'client_url_select'], true)) {
                $config[$field['name']] = (int) $raw;
            } else {
                $config[$field['name']] = $raw;
            }
        }

        return $config ?: $registry->get($type)->getDefaultConfig();
    }

    #[Route('/{checkId}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(id: 'clientId')]
        Client $client,
        #[MapEntity(id: 'checkId')]
        SiteCheck $check,
        EntityManagerInterface $em,
    ): Response {
        if ($this->isCsrfTokenValid('delete_check'.$check->getId(), (string) $request->request->get('_token', ''))) {
            $em->remove($check);
            $em->flush();
            $this->cache->delete('watchdog_schedule');
            $this->addFlash('success', 'Check deleted.');
        }

        return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
    }

    #[Route('/{checkId}/run', name: 'run', methods: ['POST'])]
    public function run(
        Request $request,
        #[MapEntity(id: 'clientId')]
        Client $client,
        #[MapEntity(id: 'checkId')]
        SiteCheck $check,
        MessageBusInterface $bus,
        EntityManagerInterface $em,
        CheckRegistry $registry,
    ): Response {
        if ($this->isCsrfTokenValid('run_check'.$check->getId(), (string) $request->request->get('_token', ''))) {
            $label = $registry->has($check->getType()) ? $registry->get($check->getType())->getLabel() : $check->getType();
            if (\App\Enum\CheckRunner::Agent === $check->getRunner()) {
                $check->setRunNow(true);
                $em->flush();
                $this->addFlash('success', sprintf('"%s" queued for the agent — result appears within ~30 seconds.', $label));
            } else {
                $bus->dispatch(new RunSiteChecksMessage((int) $check->getId()));
                $this->addFlash('success', sprintf('Check "%s" queued — result appears in a few seconds.', $label));
            }
        }

        return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
    }

    #[Route('/{checkId}/history', name: 'history')]
    public function history(
        Request $request,
        #[MapEntity(id: 'clientId')]
        Client $client,
        #[MapEntity(id: 'checkId')]
        SiteCheck $check,
        CheckResultRepository $checkResultRepository,
    ): Response {
        $limit = in_array($request->query->getInt('limit', 50), [10, 25, 50, 100], true)
            ? $request->query->getInt('limit', 50)
            : 50;
        $page = max(1, $request->query->getInt('page', 1));

        $rawStatus = $request->query->get('status', '');
        $rawFrom = $request->query->get('from', '');
        $rawTo = $request->query->get('to', '');
        $rawHttpCode = $request->query->get('http_code', '');

        $filters = [
            'status' => '' !== $rawStatus ? $rawStatus : null,
            'from' => null,
            'to' => null,
            'http_code' => '' !== $rawHttpCode ? (int) $rawHttpCode : null,
        ];

        if ('' !== $rawFrom) {
            try {
                $filters['from'] = new \DateTimeImmutable($rawFrom.' 00:00:00');
            } catch (\Exception) {
            }
        }

        if ('' !== $rawTo) {
            try {
                $filters['to'] = new \DateTimeImmutable($rawTo.' 00:00:00');
            } catch (\Exception) {
            }
        }

        $total = $checkResultRepository->countFilteredForCheck($check, $filters);
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);

        return $this->render('check/history.html.twig', [
            'client' => $client,
            'check' => $check,
            'results' => $checkResultRepository->findFilteredForCheck($check, $filters, $page, $limit),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages,
            'filters' => [
                'status' => $rawStatus,
                'from' => $rawFrom,
                'to' => $rawTo,
                'http_code' => $rawHttpCode,
            ],
        ]);
    }
}
