<?php

namespace App\EventSubscriber;

use App\Service\AdminService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private AdminService $admin)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $identifier = $token?->getUserIdentifier() ?: 'admin@salon.com';

        $this->admin->logActivity(
            'user_logout',
            'Signed Out',
            $identifier,
            sprintf('User (%s) signed out of the session.', $identifier),
            'Signed In'
        );
    }
}

