<?php

namespace App\Controller;

use App\Form\ActivityLogFilterType;
use App\Form\AppointmentFilterType;
use App\Form\AppointmentFormType;
use App\Form\CustomerFilterType;
use App\Form\ServiceFilterType;
use App\Form\ServiceFormType;
use App\Form\UserFilterType;
use App\Repository\ActivityLogRepository;
use App\Repository\AppointmentRepository;
use App\Repository\CustomerRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(private AdminService $admin)
    {
    }

    #[Route('', name: 'app_dashboard', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        return $this->render('dashboard/dashboard.html.twig', [
            'counts' => $this->getCounts(),
            'appointments' => $this->admin->getAllAppointments(),
            'services' => $this->admin->getAllServices(),
            'categories' => $this->admin->getCategories(),
            'staff' => $this->admin->getStaff(),
            'customers' => $this->admin->getAllCustomers(),
        ]);
    }

    #[Route('/appointments', name: 'admin_appointments', methods: ['GET'])]
    public function appointments(Request $request, AppointmentRepository $appointmentRepository): Response
    {
        $allAppointments = $this->admin->getAllAppointments();
        $services = $this->admin->getAllServices();
        $staff = $this->admin->getStaff();
        $customers = $this->admin->getAllCustomers();

        $filterForm = $this->createForm(AppointmentFilterType::class, null, [
            'services' => $services,
            'staff' => $staff,
        ]);
        $filterForm->handleRequest($request);

        $appointmentForm = $this->createForm(AppointmentFormType::class, null, [
            'services' => $services,
            'staff' => $staff,
            'customers' => $customers,
            'action' => $this->generateUrl('admin_appointment_create'),
        ]);

        $filters = $filterForm->isSubmitted() ? (array) $filterForm->getData() : $request->query->all();

        return $this->render('dashboard/appointments.html.twig', [
            'counts' => $this->getCounts(),
            'appointments' => $appointmentRepository->findByFilters($filters, $allAppointments),
            'services' => $services,
            'staff' => $staff,
            'customers' => $customers,
            'filterForm' => $filterForm->createView(),
            'appointmentForm' => $appointmentForm->createView(),
        ]);
    }

    #[Route('/appointments/{id}', name: 'admin_appointment_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/appointment/{id}', name: 'admin_appointment_show_alias', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showAppointment(int $id): Response
    {
        $appointment = $this->admin->getAppointmentById($id);
        if (!$appointment) {
            throw $this->createNotFoundException(sprintf('Appointment #%d not found.', $id));
        }

        $services = $this->admin->getAllServices();
        $staff = $this->admin->getStaff();
        $customers = $this->admin->getAllCustomers();

        $appointmentForm = $this->createForm(AppointmentFormType::class, null, [
            'services' => $services,
            'staff' => $staff,
            'customers' => $customers,
            'action' => $this->generateUrl('admin_appointment_create'),
        ]);

        return $this->render('dashboard/appointment_show.html.twig', [
            'counts' => $this->getCounts(),
            'appointment' => $appointment,
            'services' => $services,
            'staff' => $staff,
            'customers' => $customers,
            'appointmentForm' => $appointmentForm->createView(),
        ]);
    }

    #[Route('/services', name: 'admin_services', methods: ['GET'])]
    public function services(Request $request, ServiceRepository $serviceRepository): Response
    {
        $allServices = $this->admin->getAllServices();
        $categories = $this->admin->getCategories();

        $filterForm = $this->createForm(ServiceFilterType::class, null, [
            'categories' => $categories,
        ]);
        $filterForm->handleRequest($request);

        $serviceForm = $this->createForm(ServiceFormType::class, ['duration' => 60], [
            'categories' => $categories,
            'action' => $this->generateUrl('admin_service_create'),
        ]);

        $filters = $filterForm->isSubmitted() ? (array) $filterForm->getData() : $request->query->all();

        return $this->render('dashboard/services.html.twig', [
            'counts' => $this->getCounts(),
            'services' => $serviceRepository->findByFilters($filters, $allServices),
            'categories' => $categories,
            'filterForm' => $filterForm->createView(),
            'serviceForm' => $serviceForm->createView(),
        ]);
    }

    #[Route('/customers', name: 'admin_customers', methods: ['GET'])]
    public function customers(Request $request, CustomerRepository $customerRepository): Response
    {
        $allCustomers = $this->admin->getAllCustomers();

        $filterForm = $this->createForm(CustomerFilterType::class, null, [
            'customers' => $allCustomers,
        ]);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() ? (array) $filterForm->getData() : $request->query->all();

        return $this->render('dashboard/customers.html.twig', [
            'counts' => $this->getCounts(),
            'customers' => $customerRepository->findByFilters($filters, $allCustomers),
            'filterForm' => $filterForm->createView(),
        ]);
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request, UserRepository $userRepository): Response
    {
        $allUsers = $this->admin->getAllUsers();

        $filterForm = $this->createForm(UserFilterType::class);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() ? (array) $filterForm->getData() : $request->query->all();

        return $this->render('dashboard/users.html.twig', [
            'counts' => $this->getCounts(),
            'users' => $userRepository->findByFilters($filters, $allUsers),
            'filterForm' => $filterForm->createView(),
        ]);
    }

    #[Route('/activity-logs', name: 'admin_activity_logs', methods: ['GET'])]
    public function activityLogs(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $allLogs = $this->admin->getActivityLogs();
        $allUsers = $this->admin->getAllUsers();

        $filterForm = $this->createForm(ActivityLogFilterType::class, null, [
            'logs' => $allLogs,
            'users' => $allUsers,
        ]);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() ? (array) $filterForm->getData() : $request->query->all();

        return $this->render('dashboard/activity_logs.html.twig', [
            'counts' => $this->getCounts(),
            'logs' => $activityLogRepository->findByFilters($filters, $allLogs),
            'filterForm' => $filterForm->createView(),
        ]);
    }

    #[Route('/kpi-stats', name: 'admin_kpi_stats', methods: ['GET'])]
    public function kpiStats(): JsonResponse
    {
        return $this->json(array_merge($this->getCounts(), [
            'timestamp' => time(),
        ]));
    }

    #[Route('/appointment/status', name: 'admin_appointment_status', methods: ['POST'])]
    public function updateAppointmentStatus(Request $r): Response
    {
        $this->checkCsrf($r);
        $this->admin->updateAppointmentStatus(
            (int) $r->request->get('appointment_id', 0),
            (int) $r->request->get('status_id', 1)
        );
        $this->addFlash('success', 'Appointment status updated successfully.');

        return $this->back($r);
    }

    #[Route('/appointment/delete', name: 'admin_appointment_delete', methods: ['POST'])]
    public function deleteAppointment(Request $r): Response
    {
        $this->checkCsrf($r);
        $this->admin->deleteAppointment((int) $r->request->get('appointment_id', 0));
        $this->addFlash('success', 'Appointment deleted successfully.');

        return $this->back($r);
    }

    #[Route('/appointment/create', name: 'admin_appointment_create', methods: ['POST'])]
    public function createAppointment(Request $r): Response
    {
        $customers = $this->admin->getAllCustomers();
        $services = $this->admin->getAllServices();
        $staff = $this->admin->getStaff();

        $form = $this->createForm(AppointmentFormType::class, null, [
            'customers' => $customers,
            'services' => $services,
            'staff' => $staff,
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
                (int) ($data['staff_id'] ?? 1),
                (int) ($data['service_id'] ?? 1),
                $date,
                (string) ($data['start_time'] ?? '10:00:00'),
                (float) ($data['price'] ?? 0),
                (int) ($data['status_id'] ?? 1),
                (string) ($data['customer_notes'] ?? '')
            );

            $ok ? $this->addFlash('success', 'New appointment scheduled successfully.')
                : $this->addFlash('error', 'Failed to schedule appointment.');

            return $this->back($r);
        }

        $this->checkCsrf($r);
        $ok = $this->admin->createAppointment(
            (int) $r->request->get('customer_id', 0),
            trim((string) $r->request->get('new_customer_name', '')),
            trim((string) $r->request->get('new_customer_email', '')),
            trim((string) $r->request->get('new_customer_phone', '')),
            (int) $r->request->get('staff_id', 1),
            (int) $r->request->get('service_id', 1),
            (string) $r->request->get('appointment_date', date('Y-m-d')),
            (string) $r->request->get('start_time', '10:00:00'),
            (float) $r->request->get('price', 0),
            (int) $r->request->get('status_id', 1),
            trim((string) $r->request->get('customer_notes', ''))
        );

        $ok ? $this->addFlash('success', 'New appointment scheduled successfully.')
            : $this->addFlash('error', 'Failed to schedule appointment.');

        return $this->back($r);
    }

    #[Route('/appointment/update', name: 'admin_appointment_update', methods: ['POST'])]
    public function updateAppointment(Request $r): Response
    {
        $this->checkCsrf($r);
        $statusId = $r->request->has('status_id') && $r->request->get('status_id') !== '' ? (int) $r->request->get('status_id') : null;
        $customerId = $r->request->has('customer_id') && $r->request->get('customer_id') !== '' ? (int) $r->request->get('customer_id') : null;
        $notes = $r->request->has('customer_notes') ? trim((string) $r->request->get('customer_notes')) : null;

        $ok = $this->admin->updateAppointment(
            (int) $r->request->get('appointment_id', 0),
            (int) $r->request->get('staff_id', 1),
            (string) $r->request->get('appointment_date', date('Y-m-d')),
            (string) $r->request->get('start_time', '10:30:00'),
            (int) $r->request->get('service_id', 1),
            (float) $r->request->get('price', 0),
            $statusId,
            $customerId,
            $notes
        );
        $ok ? $this->addFlash('success', 'Appointment details updated.')
            : $this->addFlash('error', 'Failed to update appointment (slot may already be taken).');

        return $this->back($r);
    }

    #[Route('/customer/create', name: 'admin_customer_create', methods: ['POST'])]
    public function createCustomer(Request $r): Response
    {
        $this->checkCsrf($r);
        $firstName = trim((string) $r->request->get('first_name', ''));
        $lastName = trim((string) $r->request->get('last_name', ''));
        $name = trim((string) $r->request->get('name', ''));
        if ($firstName === '' && $name !== '') {
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? 'Client';
        }

        $email = trim((string) $r->request->get('email', ''));
        $phone = trim((string) $r->request->get('phone', ''));

        $id = $this->admin->createCustomer($firstName, $lastName, $email, $phone);
        $id > 0
            ? $this->addFlash('success', 'Customer profile created and synchronized with users.')
            : $this->addFlash('error', 'Failed to create customer profile.');

        return $this->back($r);
    }

    #[Route('/customer/update', name: 'admin_customer_update', methods: ['POST'])]
    public function updateCustomer(Request $r): Response
    {
        $this->checkCsrf($r);
        $firstName = $r->request->has('first_name') ? trim((string) $r->request->get('first_name')) : null;
        $lastName = $r->request->has('last_name') ? trim((string) $r->request->get('last_name')) : null;
        $name = trim((string) $r->request->get('name', ''));

        $ok = $this->admin->updateCustomer(
            (int) $r->request->get('customer_id', 0),
            $name,
            trim((string) $r->request->get('email', '')),
            trim((string) $r->request->get('phone', '')),
            $firstName,
            $lastName
        );

        $ok ? $this->addFlash('success', 'Client information updated and synced with user account.')
            : $this->addFlash('error', 'Failed to update client information.');

        return $this->back($r);
    }

    #[Route('/customer/delete', name: 'admin_customer_delete', methods: ['POST'])]
    public function deleteCustomer(Request $r): Response
    {
        $this->checkCsrf($r);
        $ok = $this->admin->deleteCustomer((int) $r->request->get('customer_id', 0));
        $ok ? $this->addFlash('success', 'Customer profile removed.')
            : $this->addFlash('error', 'Failed to remove customer.');

        return $this->back($r);
    }

    #[Route('/service/create', name: 'admin_service_create', methods: ['POST'])]
    public function createService(Request $r): Response
    {
        $form = $this->createForm(ServiceFormType::class, null, [
            'categories' => $this->admin->getCategories(),
        ]);
        $form->handleRequest($r);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = (array) $form->getData();
            $this->admin->createService(
                (string) ($data['service_name'] ?? ''),
                (int) ($data['category_id'] ?? 1),
                (int) ($data['duration'] ?? 60),
                (float) ($data['price'] ?? 0),
                (string) ($data['description'] ?? '')
            );
            $this->addFlash('success', 'New service added to catalog.');

            return $this->back($r);
        }

        $this->checkCsrf($r);
        $this->admin->createService(
            trim((string) $r->request->get('service_name', '')),
            (int) $r->request->get('category_id', 1),
            (int) $r->request->get('duration', 60),
            (float) $r->request->get('price', 0),
            trim((string) $r->request->get('description', ''))
        );
        $this->addFlash('success', 'New service added to catalog.');

        return $this->back($r);
    }

    #[Route('/service/update', name: 'admin_service_update', methods: ['POST'])]
    public function updateService(Request $r): Response
    {
        $this->checkCsrf($r);

        $form = $this->createForm(ServiceFormType::class, null, [
            'categories' => $this->admin->getCategories(),
        ]);
        $form->handleRequest($r);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = (array) $form->getData();
            $ok = $this->admin->updateService(
                (int) $r->request->get('service_id', 0),
                (string) ($data['service_name'] ?? ''),
                (int) ($data['category_id'] ?? 1),
                (int) ($data['duration'] ?? 60),
                (float) ($data['price'] ?? 0),
                (string) ($data['description'] ?? '')
            );
        } else {
            $ok = $this->admin->updateService(
                (int) $r->request->get('service_id', 0),
                trim((string) $r->request->get('service_name', '')),
                (int) $r->request->get('category_id', 1),
                (int) $r->request->get('duration', 60),
                (float) $r->request->get('price', 0),
                trim((string) $r->request->get('description', ''))
            );
        }

        $ok ? $this->addFlash('success', 'Service updated successfully.')
            : $this->addFlash('error', 'Failed to update service.');

        return $this->back($r);
    }

    #[Route('/service/delete', name: 'admin_service_delete', methods: ['POST'])]
    public function deleteService(Request $r): Response
    {
        $this->checkCsrf($r);
        $this->admin->deleteService((int) $r->request->get('service_id', 0));
        $this->addFlash('success', 'Service removed from catalog.');

        return $this->back($r);
    }

    #[Route('/user/create', name: 'admin_user_create', methods: ['POST'])]
    public function createUser(Request $r): Response
    {
        $this->checkCsrf($r);
        $this->admin->createUser(
            trim((string) $r->request->get('name', '')),
            trim((string) $r->request->get('email', '')),
            (string) $r->request->get('role', 'ROLE_CUSTOMER'),
            (bool) $r->request->get('is_active', true)
        );
        $this->addFlash('success', 'New user account created.');

        return $this->back($r);
    }

    #[Route('/user/update', name: 'admin_user_update', methods: ['POST'])]
    public function updateUser(Request $r): Response
    {
        $this->checkCsrf($r);
        $this->admin->updateUserRole(
            (int) $r->request->get('user_id', 0),
            (string) $r->request->get('role', 'ROLE_CUSTOMER'),
            (bool) $r->request->get('is_active', true)
        );
        $this->addFlash('success', 'User account updated.');

        return $this->back($r);
    }

    #[Route('/user/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $r): Response
    {
        $this->checkCsrf($r);
        $this->admin->deleteUser((int) $r->request->get('user_id', 0));
        $this->addFlash('success', 'User account removed.');

        return $this->back($r);
    }

    private function getCounts(): array
    {
        return [
            'bookings' => count($this->admin->getAllAppointments()),
            'services' => count($this->admin->getAllServices()),
            'clients' => count($this->admin->getAllCustomers()),
            'users' => count($this->admin->getAllUsers()),
            'logs' => count($this->admin->getActivityLogs()),
        ];
    }

    private function checkCsrf(Request $r): void
    {
        if (!$this->isCsrfTokenValid('admin_action', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function back(Request $r): Response
    {
        return $this->redirect($r->headers->get('referer') ?? $this->generateUrl('app_dashboard'));
    }
}
