<?php

namespace App\Service;

/**
 * TEMPORARY: sample data for building the layout (no database).
 * Same method names as the real service, so nothing else changes.
 */
class AdminService
{
    public function getAllAppointments(): array
    {
        return [
            ['id' => 1, 'staff_id' => 1, 'appointment_date' => '2026-10-27', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'status_id' => 1, 'customer_name' => 'Maria Santos',  'email' => 'maria@example.com',  'service_id' => 1, 'service_name' => 'Bridal Makeup & Styling',  'price' => 2500],
            ['id' => 2, 'staff_id' => 2, 'appointment_date' => '2026-10-27', 'start_time' => '10:30:00', 'end_time' => '12:00:00', 'status_id' => 2, 'customer_name' => 'Ana Reyes',     'email' => 'ana@example.com',    'service_id' => 2, 'service_name' => 'Everyday Glam',           'price' => 1200],
            ['id' => 3, 'staff_id' => 1, 'appointment_date' => '2026-10-28', 'start_time' => '13:00:00', 'end_time' => '14:30:00', 'status_id' => 3, 'customer_name' => 'Liza Cruz',     'email' => 'liza@example.com',   'service_id' => 3, 'service_name' => 'Editorial / Creative Makeup', 'price' => 1800],
            ['id' => 4, 'staff_id' => 3, 'appointment_date' => '2026-10-29', 'start_time' => '14:30:00', 'end_time' => '16:00:00', 'status_id' => 4, 'customer_name' => 'Joy Dela Cruz', 'email' => 'joy@example.com',    'service_id' => 2, 'service_name' => 'Everyday Glam',           'price' => 1200],
        ];
    }

    public function getAllServices(): array
    {
        return [
            ['id' => 1, 'name' => 'Bridal Makeup & Styling',     'category_id' => 1, 'category_name' => 'Make up', 'duration_minutes' => 120, 'price' => 2500, 'description' => 'Full skin prep, trial planning, lash attachment and airbrushing.'],
            ['id' => 2, 'name' => 'Everyday Glam',               'category_id' => 1, 'category_name' => 'Make up', 'duration_minutes' => 60,  'price' => 1200, 'description' => 'Soft makeup contouring with natural lash definition.'],
            ['id' => 3, 'name' => 'Editorial / Creative Makeup', 'category_id' => 1, 'category_name' => 'Make up', 'duration_minutes' => 90,  'price' => 1800, 'description' => 'Fashion-focused color application for shoots and events.'],
            ['id' => 4, 'name' => 'Manicure',                    'category_id' => 3, 'category_name' => 'Nail care', 'duration_minutes' => 45, 'price' => 115,  'description' => 'Classic manicure.'],
        ];
    }

    public function getAllCustomers(): array
    {
        return [
            ['id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos',    'email' => 'maria@example.com', 'phone' => '+63 912 345 6789'],
            ['id' => 2, 'first_name' => 'Ana',   'last_name' => 'Reyes',     'email' => 'ana@example.com',   'phone' => '+63 917 111 2222'],
            ['id' => 3, 'first_name' => 'Liza',  'last_name' => 'Cruz',      'email' => 'liza@example.com',  'phone' => '+63 918 333 4444'],
            ['id' => 4, 'first_name' => 'Joy',   'last_name' => 'Dela Cruz', 'email' => 'joy@example.com',   'phone' => '+63 919 555 6666'],
        ];
    }

    public function getAllUsers(): array
    {
        return [
            ['id' => 1, 'name' => 'Shaina Truno',   'email' => 'admin@salon.com',    'role' => 'ROLE_ADMIN',    'is_active' => true,  'created_at' => '2026-09-01 08:30:00'],
            ['id' => 2, 'name' => 'Elena Rostova',  'email' => 'elena@salon.com',    'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-10 09:15:00'],
            ['id' => 3, 'name' => 'Sophia Miller',  'email' => 'sophia@salon.com',   'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-12 10:00:00'],
            ['id' => 4, 'name' => 'Amara Diallo',   'email' => 'amara@salon.com',    'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-14 11:20:00'],
            ['id' => 5, 'name' => 'Maria Santos',   'email' => 'maria@example.com',  'role' => 'ROLE_CUSTOMER', 'is_active' => true,  'created_at' => '2026-10-05 14:10:00'],
            ['id' => 6, 'name' => 'Ana Reyes',      'email' => 'ana@example.com',    'role' => 'ROLE_CUSTOMER', 'is_active' => true,  'created_at' => '2026-10-11 16:45:00'],
            ['id' => 7, 'name' => 'Liza Cruz',      'email' => 'liza@example.com',   'role' => 'ROLE_CUSTOMER', 'is_active' => false, 'created_at' => '2026-10-18 12:05:00'],
        ];
    }

    public function getActivityLogs(): array
    {
        return [
            ['id' => 101, 'appointment_id' => 1, 'event_type' => 'created',          'old_status' => null,        'new_status' => 'Pending',   'performed_by' => 'maria@example.com', 'description' => 'New booking created for Bridal Makeup & Styling (Oct 27, 2026 at 09:00 AM).', 'created_at' => '2026-10-25 14:22:10'],
            ['id' => 102, 'appointment_id' => 2, 'event_type' => 'status_updated',   'old_status' => 'Pending',   'new_status' => 'Confirmed', 'performed_by' => 'admin@salon.com',   'description' => 'Appointment #2 status changed from Pending to Confirmed.',                    'created_at' => '2026-10-26 09:15:42'],
            ['id' => 103, 'appointment_id' => 3, 'event_type' => 'completed',        'old_status' => 'Confirmed', 'new_status' => 'Completed', 'performed_by' => 'elena@salon.com',   'description' => 'Session completed for Editorial / Creative Makeup with Liza Cruz.',           'created_at' => '2026-10-28 14:35:00'],
            ['id' => 104, 'appointment_id' => 4, 'event_type' => 'cancelled',        'old_status' => 'Pending',   'new_status' => 'Cancelled', 'performed_by' => 'joy@example.com',   'description' => 'Customer cancelled booking #4 (24-hour policy verified).',                    'created_at' => '2026-10-28 18:05:19'],
            ['id' => 105, 'appointment_id' => null, 'event_type' => 'service_updated', 'old_status' => null,      'new_status' => 'Active',    'performed_by' => 'admin@salon.com',   'description' => 'Updated catalog price and duration for Bridal Makeup & Styling.',            'created_at' => '2026-10-30 11:10:05'],
            ['id' => 106, 'appointment_id' => null, 'event_type' => 'user_login',     'old_status' => null,       'new_status' => 'Success',   'performed_by' => 'admin@salon.com',   'description' => 'Administrator signed in to the dashboard.',                                  'created_at' => '2026-11-03 23:50:00'],
        ];
    }

    public function getCategories(): array
    {
        return [
            ['id' => 1, 'name' => 'Make up'],
            ['id' => 2, 'name' => 'Hair styling'],
            ['id' => 3, 'name' => 'Nail care'],
            ['id' => 4, 'name' => 'Cosmetology'],
            ['id' => 5, 'name' => 'SPA procedures'],
        ];
    }

    public function getStaff(): array
    {
        return [
            ['id' => 1, 'first_name' => 'Elena',  'last_name' => 'Rostova'],
            ['id' => 2, 'first_name' => 'Sophia', 'last_name' => 'Miller'],
            ['id' => 3, 'first_name' => 'Amara',  'last_name' => 'Diallo'],
        ];
    }

    // ---- Actions do nothing for now (flash messages still show) ----

    public function updateAppointmentStatus(int $id, int $statusId): void {}
    public function deleteAppointment(int $id): void {}
    public function updateAppointment(int $id, int $staffId, string $date, string $startTime, int $serviceId, float $price): bool { return true; }
    public function createService(string $name, int $categoryId, int $duration, float $price, string $description): void {}
    public function updateService(int $id, string $name, int $categoryId, int $duration, float $price, string $description): bool { return true; }
    public function deleteService(int $id): void {}
    public function createUser(string $name, string $email, string $role, bool $isActive = true): void {}
    public function updateUserRole(int $id, string $role, bool $isActive): void {}
    public function deleteUser(int $id): void {}
}
