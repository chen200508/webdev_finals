<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    private function getAdminUser(): \App\Entity\User
    {
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $userRepo = $em->getRepository(\App\Entity\User::class);
        $admin = $userRepo->findOneBy(['email' => 'admin@salon.com']);
        if (!$admin) {
            $admin = new \App\Entity\User();
            $admin->setEmail('admin@salon.com');
            $admin->setName('Shaina Truno');
            $admin->setRole('ROLE_ADMIN');
            $admin->setPassword('testpass');
            $admin->setIsActive(true);
            $admin->setCreatedAt(new \DateTimeImmutable());
            $em->persist($admin);
            $em->flush();
        }

        return $admin;
    }

    public function testDashboardPagesIncludeDataTableAndNinetyPercentLayout(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $pages = [
            '/dashboard/services' => 'Category',
            '/dashboard/appointments' => 'Status',
            '/dashboard/customers' => 'Search customers...',
            '/dashboard/users' => 'Search users...',
            '/dashboard/activity-logs' => 'Search activity logs...',
        ];

        foreach ($pages as $url => $expectedFilterOrPlaceholder) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful();

            $content = (string) $client->getResponse()->getContent();
            $this->assertStringContainsString('https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.css', $content);
            $this->assertStringContainsString('https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.js', $content);
            $this->assertStringContainsString('data-datatable', $content);
            $this->assertStringContainsString('width: 90%; max-width: none;', $content);
            $this->assertStringContainsString('.desk-logo { width: 150px;', $content);
            $this->assertStringContainsString('secondary', $content);
            $this->assertStringContainsString($expectedFilterOrPlaceholder, $content);
        }

        // Also check Overview page has 90% layout width
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('width: 90%; max-width: none;', (string) $client->getResponse()->getContent());
    }

    public function testAppointmentsPageHasCrudElements(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $crawler = $client->request('GET', '/dashboard/appointments');
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // Create button & modal
        $this->assertStringContainsString('Schedule New Appointment', $html);
        $this->assertStringContainsString('id="addAppointmentModal"', $html);
        $this->assertStringContainsString('id="editAppointmentModal"', $html);

        // Action buttons
        $this->assertStringContainsString('btn-view', $html);
        $this->assertStringContainsString('href="/dashboard/appointments/1"', $html);
        $this->assertStringContainsString('btn-edit', $html);
        $this->assertStringContainsString('btn-delete', $html);

        // Centralized View Details Page
        $client->request('GET', '/dashboard/appointments/1');
        $this->assertResponseIsSuccessful();
        $detailHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Appointment #1', $detailHtml);
        $this->assertStringContainsString('Back to Appointments', $detailHtml);
        $this->assertStringContainsString('Client Information', $detailHtml);
        $this->assertStringContainsString('Treatment &amp; Service', $detailHtml);
        $this->assertStringContainsString('Schedule &amp; Timing', $detailHtml);
        $this->assertStringContainsString('Billing &amp; Fee Summary', $detailHtml);
        $this->assertStringContainsString('width: 90%; max-width: none;', $detailHtml);
    }

    public function testUsersPageHasAddUserModal(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $crawler = $client->request('GET', '/dashboard/users');
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        $this->assertStringContainsString('id="openAddUserModal"', $html);
        $this->assertStringContainsString('id="addUserModal"', $html);
        $this->assertStringContainsString('Add a user account', $html);
        $this->assertStringContainsString('e.g. Jane Doe', $html);
        $this->assertStringContainsString('e.g. jane@salon.com', $html);
        $this->assertStringContainsString('Customer', $html);
        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('Add User', $html);
    }

    public function testAppointmentsCrudOperations(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        $crawler = $client->request('GET', '/dashboard/appointments');
        $this->assertResponseIsSuccessful();

        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        // 1. CREATE APPOINTMENT
        $client->request('POST', '/dashboard/appointment/create', [
            '_token' => $csrfToken,
            'new_customer_name' => 'Test Crud Client',
            'new_customer_email' => 'crud.test@salon.local',
            'new_customer_phone' => '+63 999 123 4567',
            'service_id' => 1,
            'staff_id' => 1,
            'appointment_date' => '2026-12-01',
            'start_time' => '11:00',
            'price' => '2500.00',
            'status_id' => 2,
            'customer_notes' => 'Test special request',
        ]);
        $this->assertResponseRedirects();

        // Follow redirect or reload appointments
        $client->request('GET', '/dashboard/appointments');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test Crud Client', (string) $client->getResponse()->getContent());

        // 2. UPDATE APPOINTMENT DETAILS
        $adminService = static::getContainer()->get(\App\Service\AdminService::class);
        $appointments = $adminService->getAllAppointments();
        $createdApp = null;
        foreach ($appointments as $a) {
            if (($a['customer_name'] ?? '') === 'Test Crud Client') {
                $createdApp = $a;
                break;
            }
        }

        $appId = $createdApp ? (int) $createdApp['id'] : 1;

        $client->request('POST', '/dashboard/appointment/update', [
            '_token' => $csrfToken,
            'appointment_id' => $appId,
            'staff_id' => 2,
            'service_id' => 2,
            'appointment_date' => '2026-12-02',
            'start_time' => '14:00',
            'price' => '1200.00',
            'status_id' => 2,
            'customer_notes' => 'Updated notes',
        ]);
        $this->assertResponseRedirects();

        // 3. UPDATE STATUS
        $client->request('POST', '/dashboard/appointment/status', [
            '_token' => $csrfToken,
            'appointment_id' => $appId,
            'status_id' => 3, // Completed
        ]);
        $this->assertResponseRedirects();

        // 4. DELETE APPOINTMENT
        $client->request('POST', '/dashboard/appointment/delete', [
            '_token' => $csrfToken,
            'appointment_id' => $appId,
        ]);
        $this->assertResponseRedirects();
    }

    public function testAppointmentShowPageCrudSectionsAndCustomerUsersSync(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        // 1. Verify Appointment #2 details page has all CRUD sections, buttons, and modals
        $crawler = $client->request('GET', '/dashboard/appointments/2');
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // Check clean tables and no avatars
        $this->assertStringNotContainsString('class="avatar"', $html);
        $this->assertStringNotContainsString('class="user-avatar"', $html);

        // Section 1: Client Information
        $this->assertStringContainsString('Client Information', $html);
        $this->assertStringContainsString('Customer #2', $html);
        $this->assertStringContainsString('Ana Reyes', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringContainsString('+63 917 111 2222', $html);
        $this->assertStringContainsString('id="openEditClientModal"', $html);
        $this->assertStringContainsString('id="editClientModal"', $html);

        // Section 2: Treatment & Service
        $this->assertStringContainsString('Treatment &amp; Service', $html);
        $this->assertStringContainsString('Service #2', $html);
        $this->assertStringContainsString('Everyday Glam', $html);
        $this->assertStringContainsString('60 minutes', $html);
        $this->assertStringContainsString('Chariz Mae Lopez', $html);
        $this->assertStringContainsString('id="openEditServiceModal"', $html);
        $this->assertStringContainsString('id="editServiceModal"', $html);

        // Section 3: Customer Notes & Special Requests
        $this->assertStringContainsString('Customer Notes &amp; Special Requests', $html);
        $this->assertStringContainsString('id="openEditNotesModal"', $html);
        $this->assertStringContainsString('id="editNotesModal"', $html);

        // Section 4: Schedule & Timing
        $this->assertStringContainsString('Schedule &amp; Timing', $html);
        $this->assertStringContainsString('Oct 27, 2026', $html);
        $this->assertStringContainsString('Tuesday', $html);
        $this->assertStringContainsString('10:30 AM', $html);
        $this->assertStringContainsString('12:00 PM', $html);
        $this->assertStringContainsString('60 mins', $html);
        $this->assertStringContainsString('id="openEditScheduleModal"', $html);
        $this->assertStringContainsString('id="editScheduleModal"', $html);

        // Section 5: Billing & Fee Summary
        $this->assertStringContainsString('Billing &amp; Fee Summary', $html);
        $this->assertStringContainsString('Service Fee', $html);
        $this->assertStringContainsString('Taxes &amp; Salon Surcharge', $html);
        $this->assertStringContainsString('Included', $html);
        $this->assertStringContainsString('Total Due', $html);
        $this->assertStringContainsString('id="openEditFeeModal"', $html);
        $this->assertStringContainsString('id="editFeeModal"', $html);

        // Page-level CRUD elements
        $this->assertStringContainsString('id="openCreateModal"', $html);
        $this->assertStringContainsString('id="scheduleAppointmentModal"', $html);
        $this->assertStringContainsString('id="openEditModal"', $html);
        $this->assertStringContainsString('id="editAppointmentModal"', $html);

        // 2. Test Customer Update endpoint
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/dashboard/customer/update', [
            '_token' => $csrfToken,
            'customer_id' => 2,
            'name' => 'Ana Reyes Updated',
            'email' => 'ana.updated@example.com',
            'phone' => '+63 917 888 9999',
        ]);
        $this->assertResponseRedirects();

        // 3. Verify EVERY customer is present in the Users tab
        $client->request('GET', '/dashboard/users');
        $this->assertResponseIsSuccessful();
        $usersHtml = (string) $client->getResponse()->getContent();

        $adminService = static::getContainer()->get(\App\Service\AdminService::class);
        $allCustomers = $adminService->getAllCustomers();
        foreach ($allCustomers as $customer) {
            $this->assertStringContainsString($customer['email'], $usersHtml, sprintf('Customer email %s must be present in the Users tab.', $customer['email']));
        }
    }

    public function testCustomersPageHasFullCrudOperations(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        // 1. GET /dashboard/customers
        $crawler = $client->request('GET', '/dashboard/customers');
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // Check Create button and modal
        $this->assertStringContainsString('id="openAddCustomerModal"', $html);
        $this->assertStringContainsString('id="addCustomerModal"', $html);
        $this->assertStringContainsString('Add Customer', $html);

        // Check Action columns & buttons
        $this->assertStringContainsString('Actions', $html);
        $this->assertStringContainsString('btn-view-customer', $html);
        $this->assertStringContainsString('btn-edit-customer', $html);
        $this->assertStringContainsString('btn-delete', $html);

        // Check View and Edit modals exist
        $this->assertStringContainsString('id="viewCustomerModal"', $html);
        $this->assertStringContainsString('id="editCustomerModal"', $html);
        $this->assertStringContainsString('Customer Details', $html);

        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        // 2. CREATE CUSTOMER
        $client->request('POST', '/dashboard/customer/create', [
            '_token' => $csrfToken,
            'first_name' => 'Carla',
            'last_name' => 'Mendoza',
            'email' => 'carla@salon.test',
            'phone' => '+63 922 777 8888',
        ]);
        $this->assertResponseRedirects();

        // Check Carla is on customers page
        $client->request('GET', '/dashboard/customers');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Carla Mendoza', (string) $client->getResponse()->getContent());
        $this->assertStringContainsString('carla@salon.test', (string) $client->getResponse()->getContent());

        // Check Carla was automatically synchronized to users tab
        $client->request('GET', '/dashboard/users');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('carla@salon.test', (string) $client->getResponse()->getContent());

        // 3. EDIT CUSTOMER
        $adminService = static::getContainer()->get(\App\Service\AdminService::class);
        $customers = $adminService->getAllCustomers();
        $carlaId = null;
        foreach ($customers as $c) {
            if ($c['email'] === 'carla@salon.test') {
                $carlaId = $c['id'];
                break;
            }
        }
        $this->assertNotNull($carlaId);

        $client->request('POST', '/dashboard/customer/update', [
            '_token' => $csrfToken,
            'customer_id' => $carlaId,
            'first_name' => 'Carla Jane',
            'last_name' => 'Mendoza',
            'email' => 'carla.jane@salon.test',
            'phone' => '+63 922 999 0000',
        ]);
        $this->assertResponseRedirects();

        // 4. DELETE CUSTOMER
        $client->request('POST', '/dashboard/customer/delete', [
            '_token' => $csrfToken,
            'customer_id' => $carlaId,
        ]);
        $this->assertResponseRedirects();
    }

    public function testActivityLogsPerformedByIsDynamic(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getAdminUser());

        // 1. Visit activity logs page
        $crawler = $client->request('GET', '/dashboard/activity-logs');
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // 2. Performed By filter choices must be dynamically populated from users & logs
        $this->assertStringContainsString('All Users', $html);
        $this->assertStringContainsString('admin@salon.com', $html);
        $this->assertStringContainsString('Shaina Truno', $html);
        $this->assertStringContainsString('Admin', $html);

        // Check table displays dynamic actor name, role badge, and email
        $this->assertStringContainsString('badge-admin', $html);
        $this->assertStringContainsString('Shaina Truno', $html);

        // 3. Filter by specific performer
        $client->request('GET', '/dashboard/activity-logs', [
            'performed_by' => 'admin@salon.com',
        ]);
        $this->assertResponseIsSuccessful();
        $filteredHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('admin@salon.com', $filteredHtml);

        // 4. Trigger a new action and verify dynamic logging
        $adminService = static::getContainer()->get(\App\Service\AdminService::class);
        $adminService->logActivity(
            'status_updated',
            'Confirmed',
            'chantelle@salon.com',
            'Chantelle confirmed customer booking test.',
            'Pending',
            2
        );

        $client->request('GET', '/dashboard/activity-logs');
        $this->assertResponseIsSuccessful();
        $updatedHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Chantelle Ramos', $updatedHtml);
        $this->assertStringContainsString('chantelle@salon.com', $updatedHtml);
        $this->assertStringContainsString('badge-staff', $updatedHtml);
        $this->assertStringContainsString('Staff', $updatedHtml);

        // 5. Test logout logs activity without any TypeError
        $client->request('GET', '/logout');
        $this->assertResponseRedirects();
    }
}

