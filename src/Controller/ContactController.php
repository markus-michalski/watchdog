<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/sites/{siteId}/contacts', name: 'contact_')]
class ContactController extends AbstractController
{
    #[Route('/new', name: 'new')]
    public function new(
        Request $request,
        #[MapEntity(id: 'siteId')] Site $site,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): Response {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));

            $emailConstraint = new Assert\Email();
            $errors = $validator->validate($email, $emailConstraint);

            if ($name !== '' && count($errors) === 0) {
                $contact = new Contact();
                $contact->setName($name);
                $contact->setEmail($email);
                $contact->setSite($site);

                $em->persist($contact);
                $em->flush();
                $this->addFlash('success', sprintf('Contact "%s" added.', $name));

                return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
            }

            $this->addFlash('error', 'Invalid name or email.');
        }

        return $this->render('contact/form.html.twig', ['site' => $site]);
    }

    #[Route('/{contactId}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        #[MapEntity(id: 'siteId')] Site $site,
        #[MapEntity(id: 'contactId')] Contact $contact,
        EntityManagerInterface $em,
    ): Response {
        if ($this->isCsrfTokenValid('delete_contact' . $contact->getId(), $request->request->get('_token'))) {
            $em->remove($contact);
            $em->flush();
            $this->addFlash('success', 'Contact removed.');
        }

        return $this->redirectToRoute('site_show', ['id' => $site->getId()]);
    }
}
