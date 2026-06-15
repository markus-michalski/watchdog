<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Agent;
use App\Enum\CheckRunner;
use App\Form\AgentType;
use App\Repository\AgentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/agents', name: 'agent_')]
class AgentController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(AgentRepository $agentRepository): Response
    {
        return $this->render('agent/index.html.twig', [
            'agents' => $agentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $agent = new Agent();
        $form = $this->createForm(AgentType::class, $agent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rawToken = bin2hex(random_bytes(32));
            $agent->setTokenHash(hash('sha256', $rawToken));

            $em->persist($agent);
            $em->flush();

            $this->addFlash('agent_token', $rawToken);

            return $this->redirectToRoute('agent_show', ['id' => $agent->getId()]);
        }

        return $this->render('agent/form.html.twig', [
            'form' => $form,
            'title' => 'Register Agent',
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Agent $agent): Response
    {
        return $this->render('agent/show.html.twig', [
            'agent' => $agent,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Agent $agent, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AgentType::class, $agent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Agent updated.');
            return $this->redirectToRoute('agent_show', ['id' => $agent->getId()]);
        }

        return $this->render('agent/form.html.twig', [
            'form' => $form,
            'title' => 'Edit Agent',
            'agent' => $agent,
        ]);
    }

    #[Route('/{id}/regenerate-token', name: 'regenerate_token', methods: ['POST'])]
    public function regenerateToken(Request $request, Agent $agent, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('regenerate-token-' . $agent->getId(), (string) $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $rawToken = bin2hex(random_bytes(32));
        $agent->setTokenHash(hash('sha256', $rawToken));
        $em->flush();

        $this->addFlash('agent_token', $rawToken);

        return $this->redirectToRoute('agent_show', ['id' => $agent->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Agent $agent, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-agent-' . $agent->getId(), (string) $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        foreach ($agent->getChecks() as $check) {
            $check->setAgent(null);
            $check->setRunner(CheckRunner::Dashboard);
        }

        $em->remove($agent);
        $em->flush();

        $this->addFlash('success', 'Agent deleted.');

        return $this->redirectToRoute('agent_index');
    }
}
