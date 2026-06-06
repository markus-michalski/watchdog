<?php

declare(strict_types=1);

namespace App\Controller;

use App\Check\CheckRegistry;
use App\Entity\Site;
use App\Entity\SiteCheck;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Form\SiteCheckType;
use App\Repository\CheckResultRepository;
use App\Service\CheckRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sites/{siteId}/checks', name: 'check_')]
class SiteCheckController extends AbstractController
{
    #[Route('/new', name: 'new')]
    public function new(
        Request $request,
        #[MapEntity(id: 'siteId')] Site $site,
        EntityManagerInterface $em,
        CheckRegistry $registry,
    ): Response {
        $check = new SiteCheck();
        $check->setSite($site);

        $form = $this->createForm(SiteCheckType::class, $check);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $check->setConfig($this->buildConfig($request, $check->getType(), $registry));
            $em->persist($check);
            $em->flush();
            $this->addFlash('success', sprintf('Check "%s" added.', $check->getLabel()));

            return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
        }

        return $this->render('check/form.html.twig', [
            'form' => $form,
            'site' => $site,
            'check' => $check,
            'title' => 'Add Check',
            'schemas' => $registry->getAllSchemas(),
        ]);
    }

    #[Route('/{checkId}/edit', name: 'edit')]
    public function edit(
        Request $request,
        #[MapEntity(id: 'siteId')] Site $site,
        #[MapEntity(id: 'checkId')] SiteCheck $check,
        EntityManagerInterface $em,
        CheckRegistry $registry,
    ): Response {
        $form = $this->createForm(SiteCheckType::class, $check);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $check->setConfig($this->buildConfig($request, $check->getType(), $registry));
            $em->flush();
            $this->addFlash('success', 'Check updated.');

            return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
        }

        return $this->render('check/form.html.twig', [
            'form' => $form,
            'site' => $site,
            'check' => $check,
            'title' => 'Edit Check',
            'schemas' => $registry->getAllSchemas(),
        ]);
    }

    private function buildConfig(Request $request, string $type, CheckRegistry $registry): array
    {
        if (!$registry->has($type)) {
            return [];
        }

        $schema = $registry->get($type)->getConfigSchema();
        $config = [];

        foreach ($schema as $field) {
            $raw = $request->request->get('check_config_' . $field['name']);

            if ($raw === null || $raw === '') {
                continue;
            }

            if ($field['name'] === 'expected_status_codes') {
                $config[$field['name']] = array_map(
                    'intval',
                    array_filter(array_map('trim', explode(',', $raw)))
                );
            } elseif ($field['type'] === 'number') {
                $config[$field['name']] = (int) $raw;
            } else {
                $config[$field['name']] = $raw;
            }
        }

        return $config ?: $registry->get($type)->getDefaultConfig();
    }

    #[Route('/{checkId}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'siteId')] Site $site, #[MapEntity(id: 'checkId')] SiteCheck $check, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_check' . $check->getId(), $request->request->get('_token'))) {
            $em->remove($check);
            $em->flush();
            $this->addFlash('success', 'Check deleted.');
        }

        return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
    }

    #[Route('/{checkId}/run', name: 'run', methods: ['POST'])]
    public function run(
        Request $request,
        #[MapEntity(id: 'siteId')] Site $site,
        #[MapEntity(id: 'checkId')] SiteCheck $check,
        CheckRunner $checkRunner,
        CheckResultRepository $checkResultRepository,
    ): Response {
        if ($this->isCsrfTokenValid('run_check' . $check->getId(), $request->request->get('_token'))) {
            $checkRunner->run($check);
            $this->addFlash('success', sprintf('Check "%s" executed.', $check->getLabel()));
        }

        return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
    }

    #[Route('/{checkId}/history', name: 'history')]
    public function history(#[MapEntity(id: 'siteId')] Site $site, #[MapEntity(id: 'checkId')] SiteCheck $check, CheckResultRepository $checkResultRepository): Response
    {
        return $this->render('check/history.html.twig', [
            'site' => $site,
            'check' => $check,
            'results' => $checkResultRepository->findRecentForCheck($check, 50),
        ]);
    }
}
