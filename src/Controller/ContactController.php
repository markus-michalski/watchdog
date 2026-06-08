<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
            'contacts' => $repo->findBy([], ['name' => 'ASC']),
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
            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));

            $errors = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

            if ($name !== '' && count($errors) === 0) {
                $contact = new Contact();
                $contact->setName($name);
                $contact->setEmail($email);
                $em->persist($contact);
                $em->flush();
                $this->addFlash('success', sprintf('Contact "%s" added.', $name));

                return $this->redirectToRoute('contact_index');
            }

            $error = 'Please enter a valid name and email address.';
        }

        return $this->render('contact/form.html.twig', [
            'title' => 'New contact',
            'contact' => null,
            'error' => $error,
        ]);
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
            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));

            $errors = $validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

            if ($name !== '' && count($errors) === 0) {
                $contact->setName($name);
                $contact->setEmail($email);
                $em->flush();
                $this->addFlash('success', sprintf('Contact "%s" updated.', $name));

                return $this->redirectToRoute('contact_index');
            }

            $error = 'Please enter a valid name and email address.';
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
        if ($this->isCsrfTokenValid('delete_contact' . $contact->getId(), $request->request->get('_token'))) {
            $em->remove($contact);
            $em->flush();
            $this->addFlash('success', sprintf('Contact "%s" deleted.', $contact->getName()));
        }

        return $this->redirectToRoute('contact_index');
    }
}
