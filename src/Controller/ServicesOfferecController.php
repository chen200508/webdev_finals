<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServicesOfferecController extends AbstractController
{
    #[Route('/services/offerec', name: 'app_services_offerec')]
    public function index(): Response
    {
        return $this->render('services_offerec/index.html.twig', [
            'controller_name' => 'ServicesOfferecController',
        ]);
    }
}
