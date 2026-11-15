<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/403', name: 'app_access_denied', methods: ['GET'])]
    public function accessDenied(): Response
    {
        $response = $this->render('security/403.html.twig');
        $response->setStatusCode(Response::HTTP_FORBIDDEN);

        return $response;
    }

    #[Route(path: '/404', name: 'app_not_found', methods: ['GET'])]
    public function notFound(): Response
    {
        $response = $this->render('security/404.html.twig');
        $response->setStatusCode(Response::HTTP_NOT_FOUND);

        return $response;
    }

    #[Route(path: '/{path}', name: 'app_catchall', requirements: ['path' => '^(?!_profiler|_wdt|assets|build).*'], priority: -1000)]
    public function catchAll(): Response
    {
        if ($this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            $response = $this->render('security/403.html.twig');
            $response->setStatusCode(Response::HTTP_FORBIDDEN);

            return $response;
        }

        $response = $this->render('security/404.html.twig');
        $response->setStatusCode(Response::HTTP_NOT_FOUND);

        return $response;
    }
}

