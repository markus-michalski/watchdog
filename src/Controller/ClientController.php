<?php

declare(strict_types=1);

namespace App\Controller;

use App\Check\CheckRegistry;
use App\Entity\Client;
use App\Entity\ClientUrl;
use App\Form\ClientType;
use App\Form\ClientUrlType;
use App\Repository\CheckResultRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients', name: 'client_')]
class ClientController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(ClientRepository $clientRepository): Response
    {
        return $this->render('client/index.html.twig', [
            'clients' => $clientRepository->findAllWithChecks(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($client);
            $em->flush();
            $this->addFlash('success', sprintf('Client "%s" created.', $client->getName()));

            return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/form.html.twig', [
            'form' => $form,
            'client' => $client,
            'title' => 'Add Client',
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Client $client, CheckResultRepository $checkResultRepository, CheckRegistry $checkRegistry): Response
    {
        $latestResults = [];
        $typeLabels = [];  // type => label, only types present in this client's checks
        $checkTargets = []; // checkId => ?string

        foreach ($client->getChecks() as $check) {
            $result = $checkResultRepository->findLatestForCheck($check);
            if (null !== $result) {
                $latestResults[$check->getId()] = $result;
            }

            $type = $check->getType();
            if ($checkRegistry->has($type)) {
                $impl = $checkRegistry->get($type);
                $typeLabels[$type] = $impl->getLabel();
                $checkTargets[$check->getId()] = $impl->resolveEmailTarget($check->getConfig());
            }
        }

        asort($typeLabels);

        return $this->render('client/show.html.twig', [
            'client' => $client,
            'latestResults' => $latestResults,
            'typeLabels' => $typeLabels,
            'checkTargets' => $checkTargets,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', sprintf('Client "%s" updated.', $client->getName()));

            return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/form.html.twig', [
            'form' => $form,
            'client' => $client,
            'title' => 'Edit Client',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$client->getId(), (string) $request->request->get('_token', ''))) {
            $em->remove($client);
            $em->flush();
            $this->addFlash('success', sprintf('Client "%s" deleted.', $client->getName()));
        }

        return $this->redirectToRoute('client_index');
    }

    #[Route('/{id}/urls/new', name: 'url_new')]
    public function urlNew(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $clientUrl = new ClientUrl();
        $clientUrl->setClient($client);

        $form = $this->createForm(ClientUrlType::class, $clientUrl);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($clientUrl);
            $em->flush();
            $this->addFlash('success', 'URL added.');

            return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/url_form.html.twig', [
            'form' => $form,
            'client' => $client,
            'title' => 'Add URL',
        ]);
    }

    #[Route('/{clientId}/urls/{urlId}/edit', name: 'url_edit')]
    public function urlEdit(
        Request $request,
        #[MapEntity(id: 'clientId')]
        Client $client,
        #[MapEntity(id: 'urlId')]
        ClientUrl $clientUrl,
        EntityManagerInterface $em,
    ): Response {
        if ($clientUrl->getClient() !== $client) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ClientUrlType::class, $clientUrl);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'URL updated.');

            return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/url_form.html.twig', [
            'form' => $form,
            'client' => $client,
            'title' => 'Edit URL',
        ]);
    }

    #[Route('/{clientId}/urls/{urlId}/delete', name: 'url_delete', methods: ['POST'])]
    public function urlDelete(
        Request $request,
        #[MapEntity(id: 'clientId')]
        Client $client,
        #[MapEntity(id: 'urlId')]
        ClientUrl $clientUrl,
        EntityManagerInterface $em,
    ): Response {
        if ($clientUrl->getClient() !== $client) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_url'.$clientUrl->getId(), (string) $request->request->get('_token', ''))) {
            $em->remove($clientUrl);
            $em->flush();
            $this->addFlash('success', 'URL deleted.');
        }

        return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
    }
}
