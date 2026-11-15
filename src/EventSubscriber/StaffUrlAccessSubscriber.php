<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class StaffUrlAccessSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_STAFF_PATHS = [
        '/staff',
        '/staff/appointments',
        '/staff/appointment/create',
        '/staff/appointment/update',
        '/staff/appointment/status',
        '/staff/appointment/delete',
        '/staff/services',
        '/staff/service/create',
        '/staff/customers',
        '/staff/customer/create',
        '/staff/customer/update',
        '/staff/customer/delete',
        '/staff/profile',
        '/logout',
        '/403',
        '/api/slots',
    ];

    public function __construct(
        private Security $security,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->isStaffOnlyUser()) {
            return;
        }

        $path = rtrim($event->getRequest()->getPathInfo(), '/') ?: '/';

        if ($this->isPathAllowedForStaff($path)) {
            return;
        }

        $content = $this->twig->render('security/403.html.twig');
        $event->setResponse(new Response($content, Response::HTTP_FORBIDDEN));
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->isStaffOnlyUser()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (
            $throwable instanceof NotFoundHttpException
            || $throwable instanceof AccessDeniedHttpException
            || $throwable instanceof AccessDeniedException
        ) {
            $content = $this->twig->render('security/403.html.twig');
            $event->setResponse(new Response($content, Response::HTTP_FORBIDDEN));
        }
    }

    private function isStaffOnlyUser(): bool
    {
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }

        $roles = $user->getRoles();

        return \in_array('ROLE_STAFF', $roles, true) && !\in_array('ROLE_ADMIN', $roles, true);
    }

    private function isPathAllowedForStaff(string $path): bool
    {
        if (\in_array($path, self::ALLOWED_STAFF_PATHS, true)) {
            return true;
        }

        if (preg_match('#^/staff/appointments/\d+$#', $path) === 1) {
            return true;
        }

        if (preg_match('#^/(_profiler|_wdt|assets|build)(/|$)#', $path) === 1 || $path === '/favicon.ico') {
            return true;
        }

        return false;
    }
}
