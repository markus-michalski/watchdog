<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/contacts', name: 'contact_')]
class ContactController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ContactRepository $repo): Response
    {
        return $this->render('contact/index.html.twig', [
            'contacts' => $repo->findAllWithClients(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): Response {
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));

            $nameErrors = $validator->validate($name, [new Assert\NotBlank(), new Assert\Length(max: 255)]);
            $emailErrors = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

            if (0 === count($nameErrors) && 0 === count($emailErrors)) {
                $contact = new Contact();
                $contact->setName($name);
                $contact->setEmail($email);
                $em->persist($contact);
                $em->flush();
                $this->addFlash('success', sprintf('Contact "%s" added.', $name));

                return $this->redirectToRoute('contact_index');
            }

            $error = count($nameErrors) > 0
                ? 'Please enter a valid name (max. 255 characters).'
                : 'Please enter a valid email address.';
        }

        return $this->render('contact/form.html.twig', [
            'title' => 'New contact',
            'contact' => null,
            'error' => $error,
        ]);
    }

    #[Route('/new-ajax', name: 'new_ajax', methods: ['POST'])]
    public function newAjax(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token', ''))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
        }

        $name  = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));

        $nameErrors  = $validator->validate($name, [new Assert\NotBlank(), new Assert\Length(max: 255)]);
        $emailErrors = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

        if (count($nameErrors) > 0) {
            return new JsonResponse(['success' => false, 'error' => 'Please enter a valid name (max. 255 characters).']);
        }
        if (count($emailErrors) > 0) {
            return new JsonResponse(['success' => false, 'error' => 'Please enter a valid email address.']);
        }

        $contact = new Contact();
        $contact->setName($name);
        $contact->setEmail($email);
        $em->persist($contact);
        $em->flush();

        return new JsonResponse(['success' => true, 'id' => $contact->getId(), 'name' => $contact->getName(), 'email' => $contact->getEmail()]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Contact $contact,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): Response {
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));

            $nameErrors = $validator->validate($name, [new Assert\NotBlank(), new Assert\Length(max: 255)]);
            $emailErrors = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

            if (0 === count($nameErrors) && 0 === count($emailErrors)) {
                $contact->setName($name);
                $contact->setEmail($email);
                $em->flush();
                $this->addFlash('success', sprintf('Contact "%s" updated.', $name));

                return $this->redirectToRoute('contact_index');
            }

            $error = count($nameErrors) > 0
                ? 'Please enter a valid name (max. 255 characters).'
                : 'Please enter a valid email address.';
        }

        return $this->render('contact/form.html.twig', [
            'title' => 'Edit contact',
            'contact' => $contact,
            'error' => $error,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Contact $contact,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_contact'.$contact->getId(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('contact_index');
        }

        $em->remove($contact);
        $em->flush();
        $this->addFlash('success', sprintf('Contact "%s" deleted.', $contact->getName()));

        return $this->redirectToRoute('contact_index');
    }
}
