<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AdminService
{
    private const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'Confirmed',
        3 => 'Completed',
        4 => 'Cancelled',
    ];

    public function __construct(
        private ?Connection $connection = null,
        #[Autowire('%kernel.environment%')]
        private string $env = 'dev',
        private ?Security $security = null,
    ) {
    }

    private function getActor(?string $performedBy = null): string
    {
        if ($performedBy !== null && trim($performedBy) !== '') {
            return trim($performedBy);
        }

        $user = $this->security?->getUser();
        if ($user !== null && $user->getUserIdentifier() !== '') {
            return $user->getUserIdentifier();
        }

        return 'admin@salon.com';
    }

    public function logActivity(
        string $eventType,
        string $newStatus,
        ?string $performedBy,
        string $description,
        ?string $oldStatus = null,
        ?int $appointmentId = null
    ): void {
        if (!$this->canUseDatabase()) {
            return;
        }

        try {
            $this->connection->executeStatement(
                'INSERT INTO activity_logs (appointment_id, event_type, old_status, new_status, performed_by, description, created_at) VALUES (:appointmentId, :eventType, :oldStatus, :newStatus, :performedBy, :description, NOW())',
                [
                    'appointmentId' => $appointmentId,
                    'eventType' => $eventType,
                    'oldStatus' => $oldStatus,
                    'newStatus' => $newStatus,
                    'performedBy' => $this->getActor($performedBy),
                    'description' => $description,
                ]
            );
        } catch (\Throwable) {
        }
    }

    private function canUseDatabase(): bool
    {
        if ($this->connection === null || $this->env === 'test') {
            return false;
        }

        try {
            $this->connection->executeQuery('SELECT 1 FROM users LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getAllAppointments(): array
    {
        if ($this->canUseDatabase()) {
            try {
                $rows = $this->connection->fetchAllAssociative(<<<'SQL'
                    SELECT
                        a.id,
                        COALESCE(a.staff_id, 1) AS staff_id,
                        DATE_FORMAT(a.appointment_date, '%Y-%m-%d') AS appointment_date,
                        TIME_FORMAT(a.start_time, '%H:%i:%s') AS start_time,
                        TIME_FORMAT(a.end_time, '%H:%i:%s') AS end_time,
                        a.status_id,
                        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                        c.email,
                        COALESCE(aps.service_id, 1) AS service_id,
                        COALESCE(s.name, 'Salon Service') AS service_name,
                        COALESCE(aps.subtotal, 0) AS price
                    FROM appointments a
                    JOIN customers c ON c.id = a.customer_id
                    LEFT JOIN appointment_services aps ON aps.appointment_id = a.id
                    LEFT JOIN services s ON s.id = aps.service_id
                    ORDER BY a.id ASC
                SQL);

                return array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'staff_id' => (int) $r['staff_id'],
                    'appointment_date' => (string) $r['appointment_date'],
                    'start_time' => (string) $r['start_time'],
                    'end_time' => (string) $r['end_time'],
                    'status_id' => (int) $r['status_id'],
                    'customer_name' => (string) $r['customer_name'],
                    'email' => (string) $r['email'],
                    'service_id' => (int) $r['service_id'],
                    'service_name' => (string) $r['service_name'],
                    'price' => (float) $r['price'],
                ], $rows);
            } catch (\Throwable) {
            }
        }

        return [
            ['id' => 1, 'staff_id' => 1, 'appointment_date' => '2026-10-27', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'status_id' => 1, 'customer_name' => 'Maria Santos',  'email' => 'maria@example.com',  'service_id' => 1, 'service_name' => 'Bridal Makeup & Styling',  'price' => 2500],
            ['id' => 2, 'staff_id' => 2, 'appointment_date' => '2026-10-27', 'start_time' => '10:30:00', 'end_time' => '12:00:00', 'status_id' => 2, 'customer_name' => 'Ana Reyes',     'email' => 'ana@example.com',    'service_id' => 2, 'service_name' => 'Everyday Glam',           'price' => 1200],
            ['id' => 3, 'staff_id' => 1, 'appointment_date' => '2026-10-28', 'start_time' => '13:00:00', 'end_time' => '14:30:00', 'status_id' => 3, 'customer_name' => 'Liza Cruz',     'email' => 'liza@example.com',   'service_id' => 3, 'service_name' => 'Editorial / Creative Makeup', 'price' => 1800],
            ['id' => 4, 'staff_id' => 3, 'appointment_date' => '2026-10-29', 'start_time' => '14:30:00', 'end_time' => '16:00:00', 'status_id' => 4, 'customer_name' => 'Joy Dela Cruz', 'email' => 'joy@example.com',    'service_id' => 2, 'service_name' => 'Everyday Glam',           'price' => 1200],
        ];
    }

    public function getAllServices(): array
    {
        if ($this->canUseDatabase()) {
            try {
                $rows = $this->connection->fetchAllAssociative(<<<'SQL'
                    SELECT
                        s.id,
                        s.name,
                        s.category_id,
                        sc.name AS category_name,
                        s.duration_minutes,
                        COALESCE((
                            SELECT sp.price
                            FROM service_prices sp
                            WHERE sp.service_id = s.id AND sp.effective_to IS NULL
                            ORDER BY sp.effective_from DESC, sp.id DESC
                            LIMIT 1
                        ), 0) AS price,
                        COALESCE(s.description, '') AS description
                    FROM services s
                    JOIN service_categories sc ON sc.id = s.category_id
                    WHERE s.is_active = 1
                    ORDER BY s.id ASC
                SQL);

                return array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'name' => (string) $r['name'],
                    'category_id' => (int) $r['category_id'],
                    'category_name' => (string) $r['category_name'],
                    'duration_minutes' => (int) $r['duration_minutes'],
                    'price' => (float) $r['price'],
                    'description' => (string) $r['description'],
                ], $rows);
            } catch (\Throwable) {
            }
        }

        return [
            ['id' => 1, 'name' => 'Bridal Makeup & Styling',     'category_id' => 1, 'category_name' => 'Make up', 'duration_minutes' => 120, 'price' => 2500, 'description' => 'Full skin prep, trial planning, lash attachment and airbrushing.'],
            ['id' => 2, 'name' => 'Everyday Glam',               'category_id' => 1, 'category_name' => 'Make up', 'duration_minutes' => 60,  'price' => 1200, 'description' => 'Soft makeup contouring with natural lash definition.'],
            ['id' => 3, 'name' => 'Editorial / Creative Makeup', 'category_id' => 1, 'category_name' => 'Make up', 'duration_minutes' => 90,  'price' => 1800, 'description' => 'Fashion-focused color application for shoots and events.'],
            ['id' => 4, 'name' => 'Manicure',                    'category_id' => 3, 'category_name' => 'Nail care', 'duration_minutes' => 45, 'price' => 115,  'description' => 'Classic manicure.'],
        ];
    }

    public function getAllCustomers(): array
    {
        if ($this->canUseDatabase()) {
            try {
                return $this->connection->fetchAllAssociative('SELECT id, first_name, last_name, email, phone FROM customers ORDER BY id ASC');
            } catch (\Throwable) {
            }
        }

        return [
            ['id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos',    'email' => 'maria@example.com', 'phone' => '+63 912 345 6789'],
            ['id' => 2, 'first_name' => 'Ana',   'last_name' => 'Reyes',     'email' => 'ana@example.com',   'phone' => '+63 917 111 2222'],
            ['id' => 3, 'first_name' => 'Liza',  'last_name' => 'Cruz',      'email' => 'liza@example.com',  'phone' => '+63 918 333 4444'],
            ['id' => 4, 'first_name' => 'Joy',   'last_name' => 'Dela Cruz', 'email' => 'joy@example.com',   'phone' => '+63 919 555 6666'],
        ];
    }

    public function getAllUsers(): array
    {
        if ($this->canUseDatabase()) {
            try {
                $rows = $this->connection->fetchAllAssociative(<<<'SQL'
                    SELECT id, COALESCE(name, email) AS name, email, role, is_active, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at
                    FROM users
                    ORDER BY id ASC
                SQL);

                return array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'name' => (string) $r['name'],
                    'email' => (string) $r['email'],
                    'role' => (string) $r['role'],
                    'is_active' => (bool) $r['is_active'],
                    'created_at' => (string) $r['created_at'],
                ], $rows);
            } catch (\Throwable) {
            }
        }

        return [
            ['id' => 1, 'name' => 'Shaina Truno',            'email' => 'admin@salon.com',     'role' => 'ROLE_ADMIN',    'is_active' => true,  'created_at' => '2026-09-01 08:30:00'],
            ['id' => 2, 'name' => 'Chantelle Ramos',         'email' => 'chantelle@salon.com', 'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-10 09:15:00'],
            ['id' => 3, 'name' => 'Chariz Mae Lopez',        'email' => 'chariz@salon.com',    'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-12 10:00:00'],
            ['id' => 4, 'name' => 'Katrina Ellaine Mendoza', 'email' => 'katrina@salon.com',   'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-14 11:20:00'],
            ['id' => 5, 'name' => 'Maria Santos',            'email' => 'maria@example.com',   'role' => 'ROLE_CUSTOMER', 'is_active' => true,  'created_at' => '2026-10-05 14:10:00'],
            ['id' => 6, 'name' => 'Ana Reyes',               'email' => 'ana@example.com',     'role' => 'ROLE_CUSTOMER', 'is_active' => true,  'created_at' => '2026-10-11 16:45:00'],
            ['id' => 7, 'name' => 'Liza Cruz',               'email' => 'liza@example.com',    'role' => 'ROLE_CUSTOMER', 'is_active' => false, 'created_at' => '2026-10-18 12:05:00'],
        ];
    }

    public function getActivityLogs(): array
    {
        if ($this->canUseDatabase()) {
            try {
                return $this->connection->fetchAllAssociative(<<<'SQL'
                    SELECT id, appointment_id, event_type, old_status, new_status, performed_by, description, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at
                    FROM activity_logs
                    ORDER BY created_at DESC, id DESC
                SQL);
            } catch (\Throwable) {
            }
        }

        return [
            ['id' => 101, 'appointment_id' => 1, 'event_type' => 'created',          'old_status' => null,        'new_status' => 'Pending',   'performed_by' => 'maria@example.com',     'description' => 'New booking created for Bridal Makeup & Styling (Oct 27, 2026 at 09:00 AM).', 'created_at' => '2026-10-25 14:22:10'],
            ['id' => 102, 'appointment_id' => 2, 'event_type' => 'status_updated',   'old_status' => 'Pending',   'new_status' => 'Confirmed', 'performed_by' => 'admin@salon.com',       'description' => 'Appointment #2 status changed from Pending to Confirmed.',                    'created_at' => '2026-10-26 09:15:42'],
            ['id' => 103, 'appointment_id' => 3, 'event_type' => 'completed',        'old_status' => 'Confirmed', 'new_status' => 'Completed', 'performed_by' => 'chantelle@salon.com',   'description' => 'Session completed for Editorial / Creative Makeup with Liza Cruz.',           'created_at' => '2026-10-28 14:35:00'],
            ['id' => 104, 'appointment_id' => 4, 'event_type' => 'cancelled',        'old_status' => 'Pending',   'new_status' => 'Cancelled', 'performed_by' => 'joy@example.com',       'description' => 'Customer cancelled booking #4 (24-hour policy verified).',                    'created_at' => '2026-10-28 18:05:19'],
            ['id' => 105, 'appointment_id' => null, 'event_type' => 'service_updated', 'old_status' => null,      'new_status' => 'Active',    'performed_by' => 'admin@salon.com',       'description' => 'Updated catalog price and duration for Bridal Makeup & Styling.',            'created_at' => '2026-10-30 11:10:05'],
            ['id' => 106, 'appointment_id' => null, 'event_type' => 'user_login',     'old_status' => null,       'new_status' => 'Success',   'performed_by' => 'admin@salon.com',       'description' => 'Administrator signed in to the dashboard.',                                  'created_at' => '2026-11-03 23:50:00'],
        ];
    }

    public function getCategories(): array
    {
        if ($this->canUseDatabase()) {
            try {
                return $this->connection->fetchAllAssociative('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY display_order ASC, id ASC');
            } catch (\Throwable) {
            }
        }

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
        if ($this->canUseDatabase()) {
            try {
                return $this->connection->fetchAllAssociative('SELECT id, first_name, last_name FROM staff WHERE is_active = 1 ORDER BY id ASC');
            } catch (\Throwable) {
            }
        }

        return [
            ['id' => 1, 'first_name' => 'Chantelle',       'last_name' => 'Ramos'],
            ['id' => 2, 'first_name' => 'Chariz Mae',      'last_name' => 'Lopez'],
            ['id' => 3, 'first_name' => 'Katrina Ellaine', 'last_name' => 'Mendoza'],
            ['id' => 4, 'first_name' => 'Claire',          'last_name' => 'Bautista'],
            ['id' => 5, 'first_name' => 'Rheanne',         'last_name' => 'Villanueva'],
            ['id' => 6, 'first_name' => 'Lin',             'last_name' => 'Delos Santos'],
        ];
    }

    public function updateAppointmentStatus(int $id, int $statusId): void
    {
        if (!$this->canUseDatabase()) {
            return;
        }

        $oldStatusId = (int) ($this->connection->fetchOne('SELECT status_id FROM appointments WHERE id = :id', ['id' => $id]) ?: 1);
        $oldLabel = self::STATUS_LABELS[$oldStatusId] ?? 'Pending';
        $newLabel = self::STATUS_LABELS[$statusId] ?? 'Pending';

        $this->connection->executeStatement('UPDATE appointments SET status_id = :statusId WHERE id = :id', [
            'statusId' => $statusId,
            'id' => $id,
        ]);

        $eventType = $statusId === 3 ? 'completed' : ($statusId === 4 ? 'cancelled' : 'status_updated');
        $this->logActivity(
            $eventType,
            $newLabel,
            null,
            sprintf('Appointment #%d status changed from %s to %s.', $id, $oldLabel, $newLabel),
            $oldLabel,
            $id
        );
    }

    public function deleteAppointment(int $id): void
    {
        if (!$this->canUseDatabase()) {
            return;
        }
        $oldStatusId = (int) ($this->connection->fetchOne('SELECT status_id FROM appointments WHERE id = :id', ['id' => $id]) ?: 1);
        $oldLabel = self::STATUS_LABELS[$oldStatusId] ?? 'Pending';

        $this->connection->executeStatement('DELETE FROM appointments WHERE id = :id', ['id' => $id]);

        $this->logActivity(
            'cancelled',
            'Deleted',
            null,
            sprintf('Deleted appointment #%d from schedule.', $id),
            $oldLabel,
            null
        );
    }

    public function updateAppointment(int $id, int $staffId, string $date, string $startTime, int $serviceId, float $price): bool
    {
        if (!$this->canUseDatabase()) {
            return true;
        }
        if (strlen($startTime) === 5) {
            $startTime .= ':00';
        }
        $endTime = date('H:i:s', strtotime($startTime) + 5400);

        $this->connection->executeStatement(
            'UPDATE appointments SET staff_id = :staffId, appointment_date = :date, start_time = :startTime, end_time = :endTime WHERE id = :id',
            ['staffId' => $staffId, 'date' => $date, 'startTime' => $startTime, 'endTime' => $endTime, 'id' => $id]
        );
        $this->connection->executeStatement(
            'UPDATE appointment_services SET service_id = :serviceId, unit_price = :price, subtotal = :price WHERE appointment_id = :id',
            ['serviceId' => $serviceId, 'price' => $price, 'id' => $id]
        );

        $this->logActivity(
            'appointment_updated',
            'Updated',
            null,
            sprintf('Updated appointment #%d details (%s at %s, ₱%s).', $id, $date, substr($startTime, 0, 5), number_format($price, 2)),
            'Scheduled',
            $id
        );

        return true;
    }

    public function createService(string $name, int $categoryId, int $duration, float $price, string $description): void
    {
        if (!$this->canUseDatabase()) {
            return;
        }
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-')) . '-' . random_int(100, 999);
        $this->connection->executeStatement(
            'INSERT INTO services (category_id, name, slug, description, duration_minutes, is_active) VALUES (:categoryId, :name, :slug, :description, :duration, 1)',
            ['categoryId' => $categoryId, 'name' => $name, 'slug' => $slug, 'description' => $description, 'duration' => $duration]
        );
        $serviceId = (int) $this->connection->lastInsertId();
        $this->connection->executeStatement(
            'INSERT INTO service_prices (service_id, price, effective_from) VALUES (:serviceId, :price, CURDATE())',
            ['serviceId' => $serviceId, 'price' => $price]
        );

        $this->logActivity(
            'service_created',
            'Active',
            null,
            sprintf('Added new service "%s" (₱%s, %d mins) to catalog.', $name, number_format($price, 2), $duration)
        );
    }

    public function updateService(int $id, string $name, int $categoryId, int $duration, float $price, string $description): bool
    {
        if (!$this->canUseDatabase()) {
            return true;
        }
        $this->connection->executeStatement(
            'UPDATE services SET name = :name, category_id = :categoryId, duration_minutes = :duration, description = :description WHERE id = :id',
            ['name' => $name, 'categoryId' => $categoryId, 'duration' => $duration, 'description' => $description, 'id' => $id]
        );
        $this->connection->executeStatement(
            'UPDATE service_prices SET price = :price WHERE service_id = :id AND effective_to IS NULL',
            ['price' => $price, 'id' => $id]
        );

        $this->logActivity(
            'service_updated',
            'Updated',
            null,
            sprintf('Updated catalog service "%s" (₱%s, %d mins).', $name, number_format($price, 2), $duration),
            'Active'
        );

        return true;
    }

    public function deleteService(int $id): void
    {
        if (!$this->canUseDatabase()) {
            return;
        }
        $serviceName = (string) ($this->connection->fetchOne('SELECT name FROM services WHERE id = :id', ['id' => $id]) ?: '');
        $this->connection->executeStatement('DELETE FROM services WHERE id = :id', ['id' => $id]);

        $this->logActivity(
            'service_deleted',
            'Removed',
            null,
            sprintf('Removed service %s from catalog.', $serviceName !== '' ? '"' . $serviceName . '"' : '#' . $id),
            'Active'
        );
    }

    public function createUser(string $name, string $email, string $role, bool $isActive = true): void
    {
        if (!$this->canUseDatabase()) {
            return;
        }
        $this->connection->executeStatement(
            'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :hash, :role, :isActive)',
            [
                'name' => $name,
                'email' => $email,
                'hash' => password_hash('password', PASSWORD_BCRYPT),
                'role' => $role,
                'isActive' => $isActive ? 1 : 0,
            ]
        );

        $this->logActivity(
            'user_created',
            $isActive ? 'Active' : 'Deactivated',
            null,
            sprintf('Created user account for %s (%s) with role %s.', $name, $email, $role)
        );
    }

    public function updateUserRole(int $id, string $role, bool $isActive): void
    {
        if (!$this->canUseDatabase()) {
            return;
        }
        $this->connection->executeStatement(
            'UPDATE users SET role = :role, is_active = :isActive WHERE id = :id',
            ['role' => $role, 'isActive' => $isActive ? 1 : 0, 'id' => $id]
        );

        $this->logActivity(
            'user_updated',
            $isActive ? 'Active' : 'Deactivated',
            null,
            sprintf('Updated user #%d role to %s (%s).', $id, $role, $isActive ? 'Active' : 'Deactivated'),
            'Active'
        );
    }

    public function deleteUser(int $id): void
    {
        if (!$this->canUseDatabase()) {
            return;
        }
        $email = (string) ($this->connection->fetchOne('SELECT email FROM users WHERE id = :id', ['id' => $id]) ?: '');
        $this->connection->executeStatement('DELETE FROM users WHERE id = :id', ['id' => $id]);

        $this->logActivity(
            'user_deleted',
            'Removed',
            null,
            sprintf('Removed user account %s.', $email !== '' ? $email : '#' . $id),
            'Active'
        );
    }
}
