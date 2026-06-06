<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Site;
use App\Form\SiteType;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sites', name: 'site_')]
class SiteController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(SiteRepository $siteRepository): Response
    {
        return $this->render('site/index.html.twig', [
            'sites' => $siteRepository->findAllWithChecks(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $site = new Site();
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($site);
            $em->flush();
            $this->addFlash('success', sprintf('Site "%s" created.', $site->getName()));

            return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
        }

        return $this->render('site/form.html.twig', [
            'form' => $form,
            'site' => $site,
            'title' => 'Add Site',
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Site $site): Response
    {
        return $this->render('site/show.html.twig', ['site' => $site]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Site $site, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', sprintf('Site "%s" updated.', $site->getName()));

            return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
        }

        return $this->render('site/form.html.twig', [
            'form' => $form,
            'site' => $site,
            'title' => 'Edit Site',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Site $site, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $site->getId(), $request->request->get('_token'))) {
            $em->remove($site);
            $em->flush();
            $this->addFlash('success', sprintf('Site "%s" deleted.', $site->getName()));
        }

        return $this->redirectToRoute('site_index');
    }
}
