<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AppointmentFilterType;
use App\Form\AppointmentFormType;
use App\Repository\AppointmentRepository;
use App\Service\AdminService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffDashboardController extends AbstractController
{
    public function __construct(
        private AdminService $admin,
        private UserPasswordHasherInterface $passwordHasher,
        private ?EntityManagerInterface $entityManager = null,
    ) {
    }

    #[Route('', name: 'staff_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];
        $allAppointments = $this->admin->getAllAppointments();
        $myAppointments = array_values(array_filter($allAppointments, static fn (array $a): bool => (int) ($a['staff_id'] ?? 0) === $staffId));

        $counts = $this->getStaffCounts($staffId, $myAppointments);

        return $this->render('staff/dashboard.html.twig', [
            'staffMember' => $staffMember,
            'counts' => $counts,
            'appointments' => $myAppointments,
            'recentAppointments' => array_slice($myAppointments, 0, 5),
            'services' => $this->admin->getAllServices(),
            'categories' => $this->admin->getCategories(),
        ]);
    }

    #[Route('/appointments', name: 'staff_appointments', methods: ['GET'])]
    public function appointments(Request $request, AppointmentRepository $appointmentRepository): Response
    {
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];

        $allAppointments = $this->admin->getAllAppointments();
        $services = $this->admin->getAllServices();
        $staffList = $this->admin->getStaff();
        $customers = $this->admin->getAllCustomers();

        $viewMode = $request->query->get('view', 'all'); // 'mine' or 'all'
        $filteredPool = $viewMode === 'mine'
            ? array_values(array_filter($allAppointments, static fn (array $a): bool => (int) ($a['staff_id'] ?? 0) === $staffId))
            : $allAppointments;

        $filterForm = $this->createForm(AppointmentFilterType::class, null, [
            'services' => $services,
            'staff' => $staffList,
        ]);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() ? (array) $filterForm->getData() : $request->query->all();
        $displayedAppointments = $appointmentRepository->findByFilters($filters, $filteredPool);

        $appointmentForm = $this->createForm(AppointmentFormType::class, ['staff_id' => $staffId], [
            'services' => $services,
            'staff' => [$staffMember], // Lock or prioritize current staff
            'customers' => $customers,
            'action' => $this->generateUrl('staff_appointment_create'),
        ]);

        $counts = $this->getStaffCounts($staffId);

        return $this->render('staff/appointments.html.twig', [
            'staffMember' => $staffMember,
            'currentStaffId' => $staffId,
            'counts' => $counts,
            'appointments' => $displayedAppointments,
            'services' => $services,
            'staff' => $staffList,
            'customers' => $customers,
            'viewMode' => $viewMode,
            'filterForm' => $filterForm->createView(),
            'appointmentForm' => $appointmentForm->createView(),
        ]);
    }

    #[Route('/appointments/{id}', name: 'staff_appointment_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showAppointment(int $id): Response
    {
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];

        $appointment = $this->admin->getAppointmentById($id);
        if (!$appointment) {
            throw $this->createNotFoundException(sprintf('Appointment #%d not found.', $id));
        }

        $services = $this->admin->getAllServices();
        $customers = $this->admin->getAllCustomers();
        $isOwner = ((int) ($appointment['staff_id'] ?? 0) === $staffId);

        return $this->render('staff/appointment_show.html.twig', [
            'staffMember' => $staffMember,
            'currentStaffId' => $staffId,
            'appointment' => $appointment,
            'isOwner' => $isOwner,
            'services' => $services,
            'customers' => $customers,
            'counts' => $this->getStaffCounts($staffId),
        ]);
    }

    #[Route('/appointment/create', name: 'staff_appointment_create', methods: ['POST'])]
    public function createAppointment(Request $r): Response
    {
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];

        $customers = $this->admin->getAllCustomers();
        $services = $this->admin->getAllServices();

        $form = $this->createForm(AppointmentFormType::class, null, [
            'customers' => $customers,
            'services' => $services,
            'staff' => [$staffMember],
        ]);
        $form->handleRequest($r);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = (array) $form->getData();
            $date = $data['appointment_date'] instanceof \DateTimeInterface
                ? $data['appointment_date']->format('Y-m-d')
                : (string) ($data['appointment_date'] ?? date('Y-m-d'));

            $ok = $this->admin->createAppointment(
                (int) ($data['customer_id'] ?? 0),
                (string) ($data['new_customer_name'] ?? ''),
                (string) ($data['new_customer_email'] ?? ''),
                (string) ($data['new_customer_phone'] ?? ''),
                $staffId, // Enforce current staff ID
                (int) ($data['service_id'] ?? 1),
                $date,
                (string) ($data['start_time'] ?? '10:00:00'),
                (float) ($data['price'] ?? 0),
                (int) ($data['status_id'] ?? 1),
                (string) ($data['customer_notes'] ?? '')
            );

            $ok ? $this->addFlash('success', 'Appointment scheduled successfully.')
                : $this->addFlash('error', 'Failed to schedule appointment.');

            return $this->back($r);
        }

        $this->checkCsrf($r);
        $ok = $this->admin->createAppointment(
            (int) $r->request->get('customer_id', 0),
            trim((string) $r->request->get('new_customer_name', '')),
            trim((string) $r->request->get('new_customer_email', '')),
            trim((string) $r->request->get('new_customer_phone', '')),
            $staffId, // Enforce current staff ID
            (int) $r->request->get('service_id', 1),
            (string) $r->request->get('appointment_date', date('Y-m-d')),
            (string) $r->request->get('start_time', '10:00:00'),
            (float) $r->request->get('price', 0),
            (int) $r->request->get('status_id', 1),
            trim((string) $r->request->get('customer_notes', ''))
        );

        $ok ? $this->addFlash('success', 'Appointment scheduled successfully.')
            : $this->addFlash('error', 'Failed to schedule appointment.');

        return $this->back($r);
    }

    #[Route('/appointment/update', name: 'staff_appointment_update', methods: ['POST'])]
    public function updateAppointment(Request $r): Response
    {
        $this->checkCsrf($r);
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];
        $appointmentId = (int) $r->request->get('appointment_id', 0);

        // Security check: staff can only update their own appointments
        if (!$this->admin->isAppointmentOwnedByStaff($appointmentId, $staffId)) {
            $this->addFlash('error', 'Access denied: You can only edit your own appointments.');
            return $this->back($r);
        }

        $statusId = $r->request->has('status_id') && $r->request->get('status_id') !== '' ? (int) $r->request->get('status_id') : null;
        $customerId = $r->request->has('customer_id') && $r->request->get('customer_id') !== '' ? (int) $r->request->get('customer_id') : null;
        $notes = $r->request->has('customer_notes') ? trim((string) $r->request->get('customer_notes')) : null;

        $ok = $this->admin->updateAppointment(
            $appointmentId,
            $staffId, // Keep owned by this staff
            (string) $r->request->get('appointment_date', date('Y-m-d')),
            (string) $r->request->get('start_time', '10:30:00'),
            (int) $r->request->get('service_id', 1),
            (float) $r->request->get('price', 0),
            $statusId,
            $customerId,
            $notes
        );

        $ok ? $this->addFlash('success', 'Appointment updated successfully.')
            : $this->addFlash('error', 'Failed to update appointment (time slot may already be reserved).');

        return $this->back($r);
    }

    #[Route('/appointment/status', name: 'staff_appointment_status', methods: ['POST'])]
    public function updateAppointmentStatus(Request $r): Response
    {
        $this->checkCsrf($r);
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];
        $appointmentId = (int) $r->request->get('appointment_id', 0);

        // Security check: staff can only update their own appointments
        if (!$this->admin->isAppointmentOwnedByStaff($appointmentId, $staffId)) {
            $this->addFlash('error', 'Access denied: You can only update status for your own appointments.');
            return $this->back($r);
        }

        $statusId = $r->request->has('status_id') && $r->request->get('status_id') !== '' ? (int) $r->request->get('status_id') : 0;
        if ($appointmentId > 0 && $statusId > 0) {
            $this->admin->updateAppointmentStatus($appointmentId, $statusId);
            $this->addFlash('success', 'Appointment status updated.');
        } else {
            $this->addFlash('error', 'Failed to update appointment status.');
        }

        return $this->back($r);
    }

    #[Route('/appointment/delete', name: 'staff_appointment_delete', methods: ['POST'])]
    public function deleteAppointment(Request $r): Response
    {
        $this->checkCsrf($r);
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];
        $appointmentId = (int) $r->request->get('appointment_id', 0);

        // Security check: staff can only delete their own appointments
        if (!$this->admin->isAppointmentOwnedByStaff($appointmentId, $staffId)) {
            $this->addFlash('error', 'Access denied: You cannot delete appointments belonging to other staff or admin.');
            return $this->back($r);
        }

        $this->admin->deleteAppointment($appointmentId);
        $this->addFlash('success', 'Appointment removed successfully.');

        return $this->back($r);
    }

    #[Route('/profile', name: 'staff_profile', methods: ['GET', 'POST'])]
    public function profile(Request $r): Response
    {
        $staffMember = $this->getCurrentStaffMember();
        $staffId = (int) $staffMember['id'];
        $user = $this->getUser();

        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('staff_profile_action', (string) $r->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $currentPassword = (string) $r->request->get('current_password', '');
            $newPassword = (string) $r->request->get('new_password', '');
            $confirmPassword = (string) $r->request->get('confirm_password', '');

            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'New password must be at least 6 characters long.');
                return $this->redirectToRoute('staff_profile');
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'New password and confirmation do not match.');
                return $this->redirectToRoute('staff_profile');
            }

            if ($user instanceof User) {
                $hashed = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashed);

                if ($this->entityManager) {
                    try {
                        $this->entityManager->persist($user);
                        $this->entityManager->flush();
                    } catch (\Throwable) {
                    }
                }

                $this->admin->updateUserPassword($user->getUserIdentifier(), $hashed);

                $this->admin->logActivity(
                    'password_changed',
                    'Updated',
                    $user->getUserIdentifier(),
                    sprintf('Staff member %s changed their account password.', $user->getUserIdentifier())
                );

                $this->addFlash('success', 'Your password has been changed successfully.');
            } else {
                $this->addFlash('success', 'Password updated successfully.');
            }

            return $this->redirectToRoute('staff_profile');
        }

        return $this->render('staff/profile.html.twig', [
            'staffMember' => $staffMember,
            'user' => $user,
            'counts' => $this->getStaffCounts($staffId),
        ]);
    }

    private function getCurrentStaffMember(): array
    {
        return $this->admin->getStaffMemberForUser($this->getUser());
    }

    private function getStaffCounts(int $staffId, ?array $myAppointments = null): array
    {
        if ($myAppointments === null) {
            $all = $this->admin->getAllAppointments();
            $myAppointments = array_values(array_filter($all, static fn (array $a): bool => (int) ($a['staff_id'] ?? 0) === $staffId));
        }

        $pending = 0;
        $confirmed = 0;
        $completed = 0;
        $cancelled = 0;

        foreach ($myAppointments as $app) {
            $s = (int) ($app['status_id'] ?? 1);
            if ($s === 1) $pending++;
            elseif ($s === 2) $confirmed++;
            elseif ($s === 3) $completed++;
            elseif ($s === 4) $cancelled++;
        }

        return [
            'my_total' => count($myAppointments),
            'bookings' => count($myAppointments), // for sidebar badge
            'pending' => $pending,
            'confirmed' => $confirmed,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'services' => count($this->admin->getAllServices()),
        ];
    }

    private function checkCsrf(Request $r, string $token = 'staff_action'): void
    {
        if (!$this->isCsrfTokenValid($token, (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF security token.');
        }
    }

    private function back(Request $r): Response
    {
        return $this->redirect($r->headers->get('referer') ?? $this->generateUrl('staff_dashboard'));
    }
}

