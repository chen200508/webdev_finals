<?php

namespace App\Controller;

use App\Service\SalonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(private SalonService $salon)
    {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'treatmentPrices' => $this->salon->getHomeTreatmentPrices(),
            'testimonials' => $this->salon->getTestimonials(),
        ]);
    }

    #[Route('/home', name: 'app_home_redirect', methods: ['GET'])]
    public function homeRedirect(): Response
    {
        return $this->redirectToRoute('app_home', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/services', name: 'app_services', methods: ['GET', 'POST'])]
    public function services(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleAppointmentSubmission($request, 'app_services');
        }

        return $this->render('home/services.html.twig', [
            'categories' => $this->salon->getServiceCategories(),
            'allServices' => $this->salon->getServicesWithPrices(),
        ]);
    }

    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('home/about.html.twig', [
            'teamMembers' => $this->salon->getTeamMembers(),
        ]);
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $message = trim((string) $request->request->get('message', ''));

            if ($name !== '' && $email !== '' && $message !== '') {
                $this->addFlash('success', 'Thank you for reaching out! We have received your message and will get back to you shortly.');
            } else {
                $this->addFlash('error', 'Could not send message. Please try again.');
            }

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('home/contact.html.twig');
    }

    #[Route('/booking', name: 'app_booking', methods: ['GET', 'POST'])]
    public function booking(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleAppointmentSubmission($request, 'app_booking');
        }

        $bookedSlots = $request->hasSession() ? (array) $request->getSession()->get('booked_slots', []) : [];

        return $this->render('home/booking.html.twig', [
            'featuredServices' => $this->salon->getFeaturedBookingServices(),
            'specialists' => $this->salon->getSpecialists(),
            'initialSlots' => $this->salon->getAvailableSlots(1, '2026-10-14', 1, $bookedSlots),
        ]);
    }

    #[Route('/api/slots', name: 'app_api_slots', methods: ['GET'])]
    public function getSlots(Request $request): JsonResponse
    {
        $staffId = max(1, (int) $request->query->get('staff_id', 1));
        $date = (string) $request->query->get('date', '2026-10-14');
        $serviceId = max(1, (int) $request->query->get('service_id', 1));

        $bookedSlots = $request->hasSession() ? (array) $request->getSession()->get('booked_slots', []) : [];
        $slots = $this->salon->getAvailableSlots($staffId, $date, $serviceId, $bookedSlots);

        return $this->json([
            'success' => true,
            'slots' => $slots,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(Request $request): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return $this->redirectToRoute('app_home');
    }

    private function handleAppointmentSubmission(Request $request, string $redirectRoute): Response
    {
        if (!$this->isCsrfTokenValid('book_appointment', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute($redirectRoute);
        }

        $session = $request->getSession();
        $staffId = max(1, (int) $request->request->get('staff_id', 1));
        $date = (string) $request->request->get('appointment_date', date('Y-m-d'));
        $startTime = (string) $request->request->get('start_time', '10:30:00');
        if (strlen($startTime) === 5) {
            $startTime .= ':00';
        }

        $bookedSlots = (array) $session->get('booked_slots', []);
        $slotKey = sprintf('%d_%s_%s', $staffId, $date, $startTime);

        if (in_array($slotKey, $bookedSlots, true)) {
            $this->addFlash('error', sprintf(
                'Slot Collision: The time slot %s on %s is no longer available.',
                date('h:i A', strtotime($date . ' ' . $startTime)),
                date('M d, Y', strtotime($date))
            ));

            return $this->redirectToRoute($redirectRoute);
        }

        $bookedSlots[] = $slotKey;
        $session->set('booked_slots', $bookedSlots);

        $this->addFlash('success', 'Thank you! Your appointment request has been successfully reserved.');

        return $this->redirectToRoute($redirectRoute);
    }
}
