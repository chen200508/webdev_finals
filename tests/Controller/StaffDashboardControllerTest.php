<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StaffDashboardControllerTest extends WebTestCase
{
    private function getStaffUser(): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepo = $em->getRepository(User::class);
        $user = $userRepo->findOneBy(['email' => 'chantelle@salon.com']);
        if (!$user) {
            $user = new User();
            $user->setEmail('chantelle@salon.com');
            $user->setName('Chantelle Ramos');
            $user->setRole('ROLE_STAFF');
            $user->setPassword('dummy-hashed-pass');
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());
            $em->persist($user);
            $em->flush();
        }

        return $user;
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/staff');

        $this->assertResponseRedirects('/login');
    }

    public function testStaffDashboardAccessibleForRoleStaff(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $client->request('GET', '/staff');
        $this->assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Staff Dashboard', $content);
        $this->assertStringContainsString('Staff Portal', $content);
        $this->assertStringContainsString('My Appointments', $content);
        $this->assertStringContainsString('Chantelle', $content);
    }

    public function testStaffCannotAccessAdminDashboardReturns403(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $client->request('GET', '/dashboard');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Access Denied', $content);
    }

    public function testStaffAppointmentsPageLoadsWithIdenticalUi(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $client->request('GET', '/staff/appointments');
        $this->assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Appointments', $content);
        $this->assertStringContainsString('New Appointment', $content);
        $this->assertStringContainsString('All Salon Bookings', $content);
        $this->assertStringContainsString('Assigned to Me', $content);

        // Centralized DataTable & Filters
        $this->assertStringContainsString('data-datatable', $content);
        $this->assertStringContainsString('dt-salon-toolbar', $content);
        $this->assertStringContainsString('datatables.min.js', $content);
        $this->assertStringContainsString('datatables.min.css', $content);

        // Centralized Loading Spinner & Toast Notifications
        $this->assertStringContainsString('id="salon-top-loader"', $content);
        $this->assertStringContainsString('id="salon-toast-container"', $content);
        $this->assertStringContainsString('window.showToast', $content);
        $this->assertStringContainsString('window.setButtonLoading', $content);
        $this->assertStringContainsString('onchange="this.form.submit()"', $content);
    }

    public function testStaffProfilePageLoadsWithChangePassword(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $client->request('GET', '/staff/profile');
        $this->assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('My Profile & Security', $content);
        $this->assertStringContainsString('Change Password', $content);
        $this->assertStringContainsString('New Password', $content);
    }

    public function testStaffAppointmentShowPage(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $client->request('GET', '/staff/appointments/1');
        $this->assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Appointment #1', $content);
        $this->assertStringContainsString('Client & Booking Details', $content);
    }

    public function testStaffCannotEditAppointmentBelongingToOtherStaff(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/staff/appointments');
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Appointment #2 is assigned to Chariz Mae Lopez (staff_id 2), while Chantelle is staff_id 1
        $client->request('POST', '/staff/appointment/update', [
            '_token' => $csrfToken,
            'appointment_id' => 2,
            'service_id' => 1,
            'appointment_date' => '2026-11-20',
            'start_time' => '11:00:00',
            'price' => 1200,
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Access denied: You can only edit your own appointments', $content);
    }

    public function testStaffCannotDeleteAppointmentBelongingToOtherStaff(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/staff/appointments');
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Appointment #2 belongs to staff_id 2
        $client->request('POST', '/staff/appointment/delete', [
            '_token' => $csrfToken,
            'appointment_id' => 2,
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Access denied: You cannot delete appointments belonging to other staff or admin', $content);
    }

    public function testStaffPasswordChangeRequiresMinimumSixCharacters(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/staff/profile');
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/staff/profile', [
            '_token' => $csrfToken,
            'new_password' => '123',
            'confirm_password' => '123',
        ]);

        $this->assertResponseRedirects('/staff/profile');
        $client->followRedirect();
        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('New password must be at least 6 characters long', $content);
    }

    public function testStaffUpdateAppointmentStatusToCompletedUpdatesTableAndOverview(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/staff/appointments');
        $this->assertResponseIsSuccessful();
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Chantelle (staff_id 1) owns appointment #1
        $client->request('POST', '/staff/appointment/status', [
            '_token' => $csrfToken,
            'appointment_id' => 1,
            'status_id' => 3, // Completed
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $tableHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('status-completed', $tableHtml);
        $this->assertStringContainsString('Completed', $tableHtml);

        // Verify Overview shows completed sessions
        $client->request('GET', '/staff');
        $this->assertResponseIsSuccessful();
        $overviewHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Completed Sessions', $overviewHtml);
        $this->assertStringContainsString('badge-3', $overviewHtml);
    }

    public function testStaffUpdateAppointmentDetailsToCompletedUpdatesTableAndOverview(): void
    {
        $client = static::createClient();
        $staff = $this->getStaffUser();
        $client->loginUser($staff);

        $crawler = $client->request('GET', '/staff/appointments');
        $csrfToken = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');

        // Edit appointment #1 via modal update to status 3
        $client->request('POST', '/staff/appointment/update', [
            '_token' => $csrfToken,
            'appointment_id' => 1,
            'service_id' => 1,
            'appointment_date' => '2026-10-27',
            'start_time' => '09:00:00',
            'price' => 2500,
            'status_id' => 3, // Completed
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $tableHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('status-completed', $tableHtml);

        // Verify Overview
        $client->request('GET', '/staff');
        $this->assertResponseIsSuccessful();
        $overviewHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Completed Sessions', $overviewHtml);
    }
}
