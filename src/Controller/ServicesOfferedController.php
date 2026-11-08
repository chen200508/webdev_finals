<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServicesOfferedController extends AbstractController
{
    #[Route('/services/offered', name: 'app_services_offered')]
    public function index(): Response
    {
        return $this->render('services_offered/index.html.twig', [
            'controller_name' => 'ServicesOfferedController',
        ]);
    }
}
