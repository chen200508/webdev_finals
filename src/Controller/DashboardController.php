<?php

namespace App\Controller;

use App\Form\ActivityLogFilterType;
use App\Form\AppointmentFilterType;
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

        $filterForm = $this->createForm(AppointmentFilterType::class, null, [
            'services' => $services,
            'staff' => $staff,
        ]);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() ? (array) $filterForm->getData() : $request->query->all();

        return $this->render('dashboard/appointments.html.twig', [
            'counts' => $this->getCounts(),
            'appointments' => $appointmentRepository->findByFilters($filters, $allAppointments),
            'services' => $services,
            'staff' => $staff,
            'filterForm' => $filterForm->createView(),
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

        $filterForm = $this->createForm(ActivityLogFilterType::class, null, [
            'logs' => $allLogs,
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

    #[Route('/appointment/update', name: 'admin_appointment_update', methods: ['POST'])]
    public function updateAppointment(Request $r): Response
    {
        $this->checkCsrf($r);
        $ok = $this->admin->updateAppointment(
            (int) $r->request->get('appointment_id', 0),
            (int) $r->request->get('staff_id', 1),
            (string) $r->request->get('appointment_date', date('Y-m-d')),
            (string) $r->request->get('start_time', '10:30:00'),
            (int) $r->request->get('service_id', 1),
            (float) $r->request->get('price', 0)
        );
        $ok ? $this->addFlash('success', 'Appointment details updated.')
            : $this->addFlash('error', 'Failed to update appointment (slot may already be taken).');

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
