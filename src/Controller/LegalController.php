<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/impressum', name: 'legal_impressum')]
    public function impressum(): Response
    {
        return $this->render('legal/impressum.html.twig');
    }

    #[Route('/datenschutz', name: 'legal_datenschutz')]
    public function datenschutz(): Response
    {
        return $this->render('legal/datenschutz.html.twig');
    }
}
