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

    private static ?array $mockAppointments = null;
    private static ?array $mockUsers = null;
    private static ?array $mockCustomers = null;
    private static ?array $mockLogs = null;

    public function __construct(
        private ?Connection $connection = null,
        #[Autowire('%kernel.environment%')]
        private string $env = 'dev',
        private ?Security $security = null,
    ) {
    }

    public function getActor(?string $performedBy = null): string
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
        $actor = $this->getActor($performedBy);

        // Resolve performer name and role dynamically
        $performerName = $actor;
        $performerRole = 'ROLE_CUSTOMER';

        foreach ($this->getAllUsers() as $u) {
            if (strtolower($u['email']) === strtolower($actor)) {
                $performerName = $u['name'];
                $performerRole = $u['role'];
                break;
            }
        }

        // If mock logs array is initialized, keep it in sync
        if (is_array(self::$mockLogs)) {
            $nextId = empty(self::$mockLogs) ? 101 : (max(array_column(self::$mockLogs, 'id')) + 1);
            array_unshift(self::$mockLogs, [
                'id' => $nextId,
                'appointment_id' => $appointmentId,
                'event_type' => $eventType,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'performed_by' => $actor,
                'performer_name' => $performerName,
                'performer_role' => $performerRole,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!$this->canUseDatabase()) {
            if (!is_array(self::$mockLogs)) {
                $this->getActivityLogs();
                $nextId = empty(self::$mockLogs) ? 101 : (max(array_column(self::$mockLogs, 'id')) + 1);
                array_unshift(self::$mockLogs, [
                    'id' => $nextId,
                    'appointment_id' => $appointmentId,
                    'event_type' => $eventType,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'performed_by' => $actor,
                    'performer_name' => $performerName,
                    'performer_role' => $performerRole,
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
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
                    'performedBy' => $actor,
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
                        a.customer_id,
                        COALESCE(a.staff_id, 1) AS staff_id,
                        DATE_FORMAT(a.appointment_date, '%Y-%m-%d') AS appointment_date,
                        TIME_FORMAT(a.start_time, '%H:%i:%s') AS start_time,
                        TIME_FORMAT(a.end_time, '%H:%i:%s') AS end_time,
                        a.status_id,
                        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                        c.email,
                        COALESCE(c.phone, '') AS phone,
                        COALESCE(a.customer_notes, '') AS customer_notes,
                        COALESCE(aps.service_id, 1) AS service_id,
                        COALESCE(s.name, 'Salon Service') AS service_name,
                        COALESCE(aps.duration_minutes, s.duration_minutes, 60) AS duration_minutes,
                        COALESCE(aps.subtotal, 0) AS price
                    FROM appointments a
                    JOIN customers c ON c.id = a.customer_id
                    LEFT JOIN appointment_services aps ON aps.appointment_id = a.id
                    LEFT JOIN services s ON s.id = aps.service_id
                    ORDER BY a.id ASC
                SQL);

                return array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'customer_id' => (int) $r['customer_id'],
                    'staff_id' => (int) $r['staff_id'],
                    'appointment_date' => (string) $r['appointment_date'],
                    'start_time' => (string) $r['start_time'],
                    'end_time' => (string) $r['end_time'],
                    'status_id' => (int) $r['status_id'],
                    'customer_name' => (string) $r['customer_name'],
                    'email' => (string) $r['email'],
                    'phone' => (string) $r['phone'],
                    'customer_notes' => (string) $r['customer_notes'],
                    'service_id' => (int) $r['service_id'],
                    'service_name' => (string) $r['service_name'],
                    'duration_minutes' => (int) $r['duration_minutes'],
                    'price' => (float) $r['price'],
                ], $rows);
            } catch (\Throwable) {
            }
        }

        if (self::$mockAppointments === null) {
            self::$mockAppointments = [
                ['id' => 1, 'customer_id' => 1, 'staff_id' => 1, 'appointment_date' => '2026-10-27', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'status_id' => 1, 'customer_name' => 'Maria Santos',  'email' => 'maria@example.com',  'phone' => '+63 912 345 6789', 'customer_notes' => 'Bridal party preparation', 'service_id' => 1, 'service_name' => 'Bridal Makeup & Styling',  'duration_minutes' => 120, 'price' => 2500],
                ['id' => 2, 'customer_id' => 2, 'staff_id' => 2, 'appointment_date' => '2026-10-27', 'start_time' => '10:30:00', 'end_time' => '12:00:00', 'status_id' => 2, 'customer_name' => 'Ana Reyes',     'email' => 'ana@example.com',    'phone' => '+63 917 111 2222', 'customer_notes' => 'Afternoon event', 'service_id' => 2, 'service_name' => 'Everyday Glam',           'duration_minutes' => 60, 'price' => 1200],
                ['id' => 3, 'customer_id' => 3, 'staff_id' => 1, 'appointment_date' => '2026-10-28', 'start_time' => '13:00:00', 'end_time' => '14:30:00', 'status_id' => 3, 'customer_name' => 'Liza Cruz',     'email' => 'liza@example.com',   'phone' => '+63 918 333 4444', 'customer_notes' => 'Editorial shoot', 'service_id' => 3, 'service_name' => 'Editorial / Creative Makeup', 'duration_minutes' => 90, 'price' => 1800],
                ['id' => 4, 'customer_id' => 4, 'staff_id' => 3, 'appointment_date' => '2026-10-29', 'start_time' => '14:30:00', 'end_time' => '16:00:00', 'status_id' => 4, 'customer_name' => 'Joy Dela Cruz', 'email' => 'joy@example.com',    'phone' => '+63 919 555 6666', 'customer_notes' => '', 'service_id' => 2, 'service_name' => 'Everyday Glam',           'duration_minutes' => 60, 'price' => 1200],
            ];
        }

        return self::$mockAppointments;
    }

    public function getAppointmentById(int $id): ?array
    {
        $all = $this->getAllAppointments();
        $staffMap = [];
        foreach ($this->getStaff() as $member) {
            $staffMap[(int) $member['id']] = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
        }

        foreach ($all as $app) {
            if ((int) $app['id'] === $id) {
                $app['staff_name'] = $staffMap[(int) $app['staff_id']] ?? ('Staff #' . $app['staff_id']);
                return $app;
            }
        }

        if ($this->canUseDatabase()) {
            try {
                $row = $this->connection->fetchAssociative(<<<'SQL'
                    SELECT
                        a.id,
                        a.customer_id,
                        COALESCE(a.staff_id, 1) AS staff_id,
                        DATE_FORMAT(a.appointment_date, '%Y-%m-%d') AS appointment_date,
                        TIME_FORMAT(a.start_time, '%H:%i:%s') AS start_time,
                        TIME_FORMAT(a.end_time, '%H:%i:%s') AS end_time,
                        a.status_id,
                        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                        c.email,
                        COALESCE(c.phone, '') AS phone,
                        COALESCE(a.customer_notes, '') AS customer_notes,
                        COALESCE(aps.service_id, 1) AS service_id,
                        COALESCE(s.name, 'Salon Service') AS service_name,
                        COALESCE(aps.duration_minutes, s.duration_minutes, 60) AS duration_minutes,
                        COALESCE(aps.subtotal, 0) AS price
                    FROM appointments a
                    JOIN customers c ON c.id = a.customer_id
                    LEFT JOIN appointment_services aps ON aps.appointment_id = a.id
                    LEFT JOIN services s ON s.id = aps.service_id
                    WHERE a.id = :id
                    LIMIT 1
                SQL, ['id' => $id]);

                if ($row) {
                    $staffId = (int) $row['staff_id'];
                    return [
                        'id' => (int) $row['id'],
                        'customer_id' => (int) $row['customer_id'],
                        'staff_id' => $staffId,
                        'staff_name' => $staffMap[$staffId] ?? ('Staff #' . $staffId),
                        'appointment_date' => (string) $row['appointment_date'],
                        'start_time' => (string) $row['start_time'],
                        'end_time' => (string) $row['end_time'],
                        'status_id' => (int) $row['status_id'],
                        'customer_name' => (string) $row['customer_name'],
                        'email' => (string) $row['email'],
                        'phone' => (string) $row['phone'],
                        'customer_notes' => (string) $row['customer_notes'],
                        'service_id' => (int) $row['service_id'],
                        'service_name' => (string) $row['service_name'],
                        'duration_minutes' => (int) $row['duration_minutes'],
                        'price' => (float) $row['price'],
                    ];
                }
            } catch (\Throwable) {
            }
        }

        return null;
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
                $rows = $this->connection->fetchAllAssociative(<<<'SQL'
                    SELECT 
                        c.id, 
                        c.first_name, 
                        c.last_name, 
                        c.email, 
                        COALESCE(c.phone, '') AS phone,
                        c.user_id,
                        COALESCE((SELECT COUNT(*) FROM appointments a WHERE a.customer_id = c.id), 0) AS total_bookings
                    FROM customers c 
                    ORDER BY c.id ASC
                SQL);

                return array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'first_name' => (string) $r['first_name'],
                    'last_name' => (string) $r['last_name'],
                    'email' => (string) $r['email'],
                    'phone' => (string) $r['phone'],
                    'user_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
                    'total_bookings' => (int) $r['total_bookings'],
                ], $rows);
            } catch (\Throwable) {
            }
        }

        if (self::$mockCustomers === null) {
            self::$mockCustomers = [
                ['id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos',    'email' => 'maria@example.com', 'phone' => '+63 912 345 6789', 'total_bookings' => 1],
                ['id' => 2, 'first_name' => 'Ana',   'last_name' => 'Reyes',     'email' => 'ana@example.com',   'phone' => '+63 917 111 2222', 'total_bookings' => 1],
                ['id' => 3, 'first_name' => 'Liza',  'last_name' => 'Cruz',      'email' => 'liza@example.com',  'phone' => '+63 918 333 4444', 'total_bookings' => 1],
                ['id' => 4, 'first_name' => 'Joy',   'last_name' => 'Dela Cruz', 'email' => 'joy@example.com',   'phone' => '+63 919 555 6666', 'total_bookings' => 1],
            ];
        }

        return self::$mockCustomers;
    }

    public function getAllUsers(): array
    {
        if ($this->canUseDatabase()) {
            try {
                // Ensure every customer in customers table is automatically present in users table
                $this->connection->executeStatement(<<<'SQL'
                    INSERT INTO users (name, email, password_hash, role, is_active, created_at, updated_at)
                    SELECT 
                        TRIM(CONCAT(c.first_name, ' ', c.last_name)),
                        c.email,
                        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                        'ROLE_CUSTOMER',
                        1,
                        NOW(),
                        NOW()
                    FROM customers c
                    WHERE c.email IS NOT NULL AND c.email != ''
                      AND c.email NOT IN (SELECT u.email FROM users u WHERE u.email IS NOT NULL);
                SQL);

                $this->connection->executeStatement(<<<'SQL'
                    UPDATE customers c
                    JOIN users u ON c.email = u.email
                    SET c.user_id = u.id
                    WHERE c.user_id IS NULL OR c.user_id != u.id;
                SQL);

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

        if (self::$mockUsers === null) {
            self::$mockUsers = [
                ['id' => 1, 'name' => 'Shaina Truno',            'email' => 'admin@salon.com',     'role' => 'ROLE_ADMIN',    'is_active' => true,  'created_at' => '2026-09-01 08:30:00'],
                ['id' => 2, 'name' => 'Chantelle Ramos',         'email' => 'chantelle@salon.com', 'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-10 09:15:00'],
                ['id' => 3, 'name' => 'Chariz Mae Lopez',        'email' => 'chariz@salon.com',    'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-12 10:00:00'],
                ['id' => 4, 'name' => 'Katrina Ellaine Mendoza', 'email' => 'katrina@salon.com',   'role' => 'ROLE_STAFF',    'is_active' => true,  'created_at' => '2026-09-14 11:20:00'],
                ['id' => 5, 'name' => 'Maria Santos',            'email' => 'maria@example.com',   'role' => 'ROLE_CUSTOMER', 'is_active' => true,  'created_at' => '2026-10-05 14:10:00'],
                ['id' => 6, 'name' => 'Ana Reyes',               'email' => 'ana@example.com',     'role' => 'ROLE_CUSTOMER', 'is_active' => true,  'created_at' => '2026-10-11 16:45:00'],
                ['id' => 7, 'name' => 'Liza Cruz',               'email' => 'liza@example.com',    'role' => 'ROLE_CUSTOMER', 'is_active' => false, 'created_at' => '2026-10-18 12:05:00'],
                ['id' => 8, 'name' => 'Joy Dela Cruz',           'email' => 'joy@example.com',     'role' => 'ROLE_CUSTOMER', 'is_active' => true,  'created_at' => '2026-10-20 09:30:00'],
            ];
        }

        // Guarantee every customer is also present in users mock
        $existingEmails = array_map(static fn (array $u): string => strtolower($u['email']), self::$mockUsers);
        foreach ($this->getAllCustomers() as $c) {
            $cEmail = strtolower($c['email']);
            if (!in_array($cEmail, $existingEmails, true)) {
                $maxId = empty(self::$mockUsers) ? 1 : (max(array_column(self::$mockUsers, 'id')) + 1);
                self::$mockUsers[] = [
                    'id' => $maxId,
                    'name' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')),
                    'email' => $c['email'],
                    'role' => 'ROLE_CUSTOMER',
                    'is_active' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $existingEmails[] = $cEmail;
            }
        }

        return self::$mockUsers;
    }

    public function getActivityLogs(): array
    {
        if ($this->canUseDatabase()) {
            try {
                $rows = $this->connection->fetchAllAssociative(<<<'SQL'
                    SELECT 
                        l.id, 
                        l.appointment_id, 
                        l.event_type, 
                        l.old_status, 
                        l.new_status, 
                        l.performed_by,
                        COALESCE(u.name, c.full_name, l.performed_by) AS performer_name,
                        COALESCE(u.role, 'ROLE_CUSTOMER') AS performer_role,
                        l.description, 
                        DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i:%s') AS created_at
                    FROM activity_logs l
                    LEFT JOIN users u ON u.email = l.performed_by
                    LEFT JOIN (SELECT email, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name FROM customers) c ON c.email = l.performed_by
                    ORDER BY l.created_at DESC, l.id DESC
                SQL);

                return array_map(static fn (array $r): array => [
                    'id' => (int) $r['id'],
                    'appointment_id' => $r['appointment_id'] !== null ? (int) $r['appointment_id'] : null,
                    'event_type' => (string) $r['event_type'],
                    'old_status' => $r['old_status'] !== null ? (string) $r['old_status'] : null,
                    'new_status' => (string) $r['new_status'],
                    'performed_by' => (string) $r['performed_by'],
                    'performer_name' => (string) $r['performer_name'],
                    'performer_role' => (string) $r['performer_role'],
                    'description' => (string) $r['description'],
                    'created_at' => (string) $r['created_at'],
                ], $rows);
            } catch (\Throwable) {
            }
        }

        if (self::$mockLogs === null) {
            self::$mockLogs = [
                ['id' => 101, 'appointment_id' => 1, 'event_type' => 'created',          'old_status' => null,        'new_status' => 'Pending',   'performed_by' => 'maria@example.com',     'performer_name' => 'Maria Santos',   'performer_role' => 'ROLE_CUSTOMER', 'description' => 'New booking created for Bridal Makeup & Styling (Oct 27, 2026 at 09:00 AM).', 'created_at' => '2026-10-25 14:22:10'],
                ['id' => 102, 'appointment_id' => 2, 'event_type' => 'status_updated',   'old_status' => 'Pending',   'new_status' => 'Confirmed', 'performed_by' => 'admin@salon.com',       'performer_name' => 'Shaina Truno',   'performer_role' => 'ROLE_ADMIN',    'description' => 'Appointment #2 status changed from Pending to Confirmed.',                    'created_at' => '2026-10-26 09:15:42'],
                ['id' => 103, 'appointment_id' => 3, 'event_type' => 'completed',        'old_status' => 'Confirmed', 'new_status' => 'Completed', 'performed_by' => 'chantelle@salon.com',   'performer_name' => 'Chantelle Ramos', 'performer_role' => 'ROLE_STAFF',    'description' => 'Session completed for Editorial / Creative Makeup with Liza Cruz.',           'created_at' => '2026-10-28 14:35:00'],
                ['id' => 104, 'appointment_id' => 4, 'event_type' => 'cancelled',        'old_status' => 'Pending',   'new_status' => 'Cancelled', 'performed_by' => 'joy@example.com',       'performer_name' => 'Joy Dela Cruz',  'performer_role' => 'ROLE_CUSTOMER', 'description' => 'Customer cancelled booking #4 (24-hour policy verified).',                    'created_at' => '2026-10-28 18:05:19'],
                ['id' => 105, 'appointment_id' => null, 'event_type' => 'service_updated', 'old_status' => null,      'new_status' => 'Active',    'performed_by' => 'admin@salon.com',       'performer_name' => 'Shaina Truno',   'performer_role' => 'ROLE_ADMIN',    'description' => 'Updated catalog price and duration for Bridal Makeup & Styling.',            'created_at' => '2026-10-30 11:10:05'],
                ['id' => 106, 'appointment_id' => null, 'event_type' => 'user_login',     'old_status' => null,       'new_status' => 'Success',   'performed_by' => 'admin@salon.com',       'performer_name' => 'Shaina Truno',   'performer_role' => 'ROLE_ADMIN',    'description' => 'Administrator signed in to the dashboard.',                                  'created_at' => '2026-11-03 23:50:00'],
            ];
        }

        return self::$mockLogs;
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

    public function getStaffMemberForUser(?\Symfony\Component\Security\Core\User\UserInterface $user): array
    {
        $allStaff = $this->getStaff();
        if ($user === null) {
            return $allStaff[0] ?? ['id' => 1, 'first_name' => 'Staff', 'last_name' => 'Member'];
        }

        $email = strtolower($user->getUserIdentifier());
        $userName = method_exists($user, 'getName') ? (string) $user->getName() : '';

        if ($this->canUseDatabase()) {
            try {
                $userDbId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE email = :email', ['email' => $email]);
                if ($userDbId > 0) {
                    $staffRow = $this->connection->fetchAssociative('SELECT id, first_name, last_name FROM staff WHERE user_id = :uid LIMIT 1', ['uid' => $userDbId]);
                    if ($staffRow) {
                        return [
                            'id' => (int) $staffRow['id'],
                            'first_name' => (string) $staffRow['first_name'],
                            'last_name' => (string) $staffRow['last_name'],
                        ];
                    }
                }
            } catch (\Throwable) {
            }
        }

        foreach ($allStaff as $member) {
            $firstName = strtolower($member['first_name'] ?? '');
            $fullName = strtolower(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
            if ($firstName !== '' && str_contains($email, $firstName)) {
                return $member;
            }
            if ($userName !== '' && (str_contains(strtolower($userName), $firstName) || strtolower($userName) === $fullName)) {
                return $member;
            }
        }

        return $allStaff[0] ?? ['id' => 1, 'first_name' => 'Staff', 'last_name' => 'Member'];
    }

    public function isAppointmentOwnedByStaff(int $appointmentId, int $staffId): bool
    {
        $appointment = $this->getAppointmentById($appointmentId);
        if (!$appointment) {
            return false;
        }

        return (int) ($appointment['staff_id'] ?? 0) === $staffId;
    }

    public function getStaffAppointments(int $staffId): array
    {
        $all = $this->getAllAppointments();
        return array_values(array_filter($all, static fn (array $a): bool => (int) ($a['staff_id'] ?? 0) === $staffId));
    }

    public function updateUserPassword(string $email, string $hashedPassword): bool
    {
        if ($this->canUseDatabase()) {
            try {
                $this->connection->executeStatement('UPDATE users SET password_hash = :pwd WHERE email = :email', [
                    'pwd' => $hashedPassword,
                    'email' => $email,
                ]);
                return true;
            } catch (\Throwable) {
                return false;
            }
        }
        return true;
    }

    public function updateAppointmentStatus(int $id, int $statusId): void
    {
        if (!$this->canUseDatabase()) {
            if (self::$mockAppointments === null) {
                $this->getAllAppointments();
            }
            foreach (self::$mockAppointments as &$app) {
                if ($app['id'] === $id) {
                    $app['status_id'] = $statusId;
                    break;
                }
            }
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
            if (self::$mockAppointments === null) {
                $this->getAllAppointments();
            }
            self::$mockAppointments = array_values(array_filter(
                self::$mockAppointments,
                static fn (array $a): bool => $a['id'] !== $id
            ));
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

    public function createAppointment(
        int $customerId = 0,
        string $customerName = '',
        string $customerEmail = '',
        string $customerPhone = '',
        int $staffId = 1,
        int $serviceId = 1,
        string $date = '',
        string $startTime = '10:00:00',
        float $price = 0.0,
        int $statusId = 1,
        string $notes = ''
    ): bool {
        if (!$this->canUseDatabase()) {
            if (self::$mockAppointments === null) {
                $this->getAllAppointments();
            }
            $nextId = empty(self::$mockAppointments) ? 1 : (max(array_column(self::$mockAppointments, 'id')) + 1);
            $cName = trim($customerName) !== '' ? trim($customerName) : 'Walk-in Client';
            $cEmail = $customerEmail !== '' ? $customerEmail : 'client@salon.local';
            $cPhone = $customerPhone !== '' ? $customerPhone : 'N/A';

            $services = $this->getAllServices();
            $sName = 'Salon Service';
            foreach ($services as $s) {
                if ($s['id'] === $serviceId) {
                    $sName = $s['name'];
                    break;
                }
            }

            self::$mockAppointments[] = [
                'id' => $nextId,
                'customer_id' => $customerId > 0 ? $customerId : $nextId + 10,
                'staff_id' => $staffId,
                'appointment_date' => $date ?: date('Y-m-d'),
                'start_time' => strlen($startTime) === 5 ? $startTime . ':00' : $startTime,
                'end_time' => date('H:i:s', strtotime($startTime) + 3600),
                'status_id' => $statusId,
                'customer_name' => $cName,
                'email' => $cEmail,
                'phone' => $cPhone,
                'customer_notes' => $notes,
                'service_id' => $serviceId,
                'service_name' => $sName,
                'duration_minutes' => 60,
                'price' => $price,
            ];
            return true;
        }

        try {
            if (strlen($startTime) === 5) {
                $startTime .= ':00';
            }
            if ($date === '') {
                $date = date('Y-m-d');
            }

            // Resolve or insert Customer
            if ($customerId <= 0) {
                if ($customerEmail !== '') {
                    $existingId = $this->connection->fetchOne('SELECT id FROM customers WHERE email = :email LIMIT 1', ['email' => $customerEmail]);
                    if ($existingId !== false) {
                        $customerId = (int) $existingId;
                    }
                }

                if ($customerId <= 0) {
                    $cleanName = trim($customerName);
                    if ($cleanName !== '') {
                        $nameParts = explode(' ', $cleanName, 2);
                        $firstName = $nameParts[0];
                        $lastName = $nameParts[1] ?? 'Client';
                    } else {
                        $firstName = 'Walk-in';
                        $lastName = 'Client';
                    }

                    $email = $customerEmail !== '' ? $customerEmail : 'client.' . time() . '.' . random_int(10, 99) . '@salon.local';
                    $phone = $customerPhone !== '' ? $customerPhone : 'N/A';

                    $this->connection->executeStatement(
                        'INSERT INTO customers (first_name, last_name, email, phone) VALUES (:first, :last, :email, :phone)',
                        [
                            'first' => $firstName,
                            'last' => $lastName,
                            'email' => $email,
                            'phone' => $phone,
                        ]
                    );
                    $customerId = (int) $this->connection->lastInsertId();

                    // Ensure customer is also present in users table
                    $existingUserId = $this->connection->fetchOne('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
                    if ($existingUserId === false) {
                        $this->connection->executeStatement(
                            'INSERT INTO users (name, email, password_hash, role, is_active, created_at, updated_at) VALUES (:name, :email, :hash, :role, 1, NOW(), NOW())',
                            [
                                'name' => trim($firstName . ' ' . $lastName),
                                'email' => $email,
                                'hash' => password_hash('password', PASSWORD_BCRYPT),
                                'role' => 'ROLE_CUSTOMER',
                            ]
                        );
                        $newUserId = (int) $this->connection->lastInsertId();
                        $this->connection->executeStatement('UPDATE customers SET user_id = :userId WHERE id = :id', ['userId' => $newUserId, 'id' => $customerId]);
                    } else {
                        $this->connection->executeStatement('UPDATE customers SET user_id = :userId WHERE id = :id', ['userId' => (int) $existingUserId, 'id' => $customerId]);
                    }
                }
            }

            // Lookup service duration and default price
            $serviceRow = $this->connection->fetchAssociative(
                'SELECT s.name, s.duration_minutes, COALESCE((SELECT sp.price FROM service_prices sp WHERE sp.service_id = s.id AND sp.effective_to IS NULL ORDER BY sp.effective_from DESC, sp.id DESC LIMIT 1), 1200) AS price FROM services s WHERE s.id = :id LIMIT 1',
                ['id' => $serviceId]
            );
            $duration = (int) ($serviceRow['duration_minutes'] ?? 60);
            if ($price <= 0) {
                $price = (float) ($serviceRow['price'] ?? 1200.00);
            }
            $serviceName = (string) ($serviceRow['name'] ?? 'Salon Service');
            $endTime = date('H:i:s', strtotime($startTime) + ($duration * 60));

            // Insert into appointments
            $this->connection->executeStatement(
                'INSERT INTO appointments (customer_id, staff_id, status_id, appointment_date, start_time, end_time, customer_notes) VALUES (:custId, :staffId, :statusId, :date, :start, :end, :notes)',
                [
                    'custId' => $customerId,
                    'staffId' => $staffId,
                    'statusId' => $statusId,
                    'date' => $date,
                    'start' => $startTime,
                    'end' => $endTime,
                    'notes' => $notes !== '' ? $notes : null,
                ]
            );
            $appointmentId = (int) $this->connection->lastInsertId();

            // Insert into appointment_services
            $this->connection->executeStatement(
                'INSERT INTO appointment_services (appointment_id, service_id, quantity, unit_price, duration_minutes, subtotal) VALUES (:appId, :srvId, 1, :price, :duration, :subtotal)',
                [
                    'appId' => $appointmentId,
                    'srvId' => $serviceId,
                    'price' => $price,
                    'duration' => $duration,
                    'subtotal' => $price,
                ]
            );

            // Log activity
            $statusLabel = self::STATUS_LABELS[$statusId] ?? 'Pending';
            $this->logActivity(
                'created',
                $statusLabel,
                null,
                sprintf('Admin scheduled appointment #%d for %s (%s at %s, ₱%s).', $appointmentId, $serviceName, $date, substr($startTime, 0, 5), number_format($price, 2)),
                null,
                $appointmentId
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateAppointment(
        int $id,
        ?int $staffId = null,
        ?string $date = null,
        ?string $startTime = null,
        ?int $serviceId = null,
        ?float $price = null,
        ?int $statusId = null,
        ?int $customerId = null,
        ?string $notes = null
    ): bool {
        $existing = $this->getAppointmentById($id);
        if ($staffId === null || $staffId <= 0) {
            $staffId = (int) ($existing['staff_id'] ?? 1);
        }
        if ($date === null || $date === '') {
            $date = (string) ($existing['appointment_date'] ?? date('Y-m-d'));
        }
        if ($startTime === null || $startTime === '') {
            $startTime = (string) ($existing['start_time'] ?? '10:00:00');
        }
        if ($serviceId === null || $serviceId <= 0) {
            $serviceId = (int) ($existing['service_id'] ?? 1);
        }
        if ($price === null || $price < 0) {
            $price = (float) ($existing['price'] ?? 0);
        }

        if (!$this->canUseDatabase()) {
            if (self::$mockAppointments === null) {
                $this->getAllAppointments();
            }
            $services = $this->getAllServices();
            $serviceName = 'Salon Service';
            $duration = 60;
            foreach ($services as $s) {
                if ($s['id'] === $serviceId) {
                    $serviceName = $s['name'];
                    $duration = (int) ($s['duration_minutes'] ?? 60);
                    break;
                }
            }

            foreach (self::$mockAppointments as &$app) {
                if ($app['id'] === $id) {
                    $app['staff_id'] = $staffId;
                    $app['appointment_date'] = $date;
                    $app['start_time'] = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
                    $app['end_time'] = date('H:i:s', strtotime($startTime) + ($duration * 60));
                    $app['service_id'] = $serviceId;
                    $app['service_name'] = $serviceName;
                    $app['duration_minutes'] = $duration;
                    $app['price'] = $price;
                    if ($statusId !== null) $app['status_id'] = $statusId;
                    if ($customerId !== null) $app['customer_id'] = $customerId;
                    if ($notes !== null) $app['customer_notes'] = $notes;
                    break;
                }
            }
            return true;
        }
        try {
            if (strlen($startTime) === 5) {
                $startTime .= ':00';
            }

            $duration = (int) ($this->connection->fetchOne('SELECT duration_minutes FROM services WHERE id = :id', ['id' => $serviceId]) ?: 60);
            $endTime = date('H:i:s', strtotime($startTime) + ($duration * 60));

            $params = [
                'staffId' => $staffId,
                'date' => $date,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'id' => $id,
            ];

            $updateFields = [
                'staff_id = :staffId',
                'appointment_date = :date',
                'start_time = :startTime',
                'end_time = :endTime',
            ];

            if ($statusId !== null && $statusId > 0) {
                $updateFields[] = 'status_id = :statusId';
                $params['statusId'] = $statusId;
            }

            if ($customerId !== null && $customerId > 0) {
                $updateFields[] = 'customer_id = :customerId';
                $params['customerId'] = $customerId;
            }

            if ($notes !== null) {
                $updateFields[] = 'customer_notes = :notes';
                $params['notes'] = $notes !== '' ? $notes : null;
            }

            $sql = 'UPDATE appointments SET ' . implode(', ', $updateFields) . ' WHERE id = :id';
            $this->connection->executeStatement($sql, $params);

            // Update or insert appointment_services
            $hasService = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM appointment_services WHERE appointment_id = :id', ['id' => $id]);
            if ($hasService > 0) {
                $this->connection->executeStatement(
                    'UPDATE appointment_services SET service_id = :serviceId, unit_price = :price, duration_minutes = :duration, subtotal = :price WHERE appointment_id = :id',
                    ['serviceId' => $serviceId, 'price' => $price, 'duration' => $duration, 'id' => $id]
                );
            } else {
                $this->connection->executeStatement(
                    'INSERT INTO appointment_services (appointment_id, service_id, quantity, unit_price, duration_minutes, subtotal) VALUES (:id, :serviceId, 1, :price, :duration, :price)',
                    ['id' => $id, 'serviceId' => $serviceId, 'price' => $price, 'duration' => $duration]
                );
            }

            $this->logActivity(
                'appointment_updated',
                'Updated',
                null,
                sprintf('Updated appointment #%d details (%s at %s, ₱%s).', $id, $date, substr($startTime, 0, 5), number_format($price, 2)),
                'Scheduled',
                $id
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
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

    public function createCustomer(string $firstName, string $lastName, string $email, string $phone): int
    {
        $firstName = trim($firstName) !== '' ? trim($firstName) : 'Walk-in';
        $lastName = trim($lastName) !== '' ? trim($lastName) : 'Client';
        $fullName = trim($firstName . ' ' . $lastName);
        $email = trim($email) !== '' ? trim($email) : ('client.' . time() . '.' . random_int(10, 99) . '@salon.local');
        $phone = trim($phone) !== '' ? trim($phone) : 'N/A';

        if (!$this->canUseDatabase()) {
            if (self::$mockCustomers === null) {
                $this->getAllCustomers();
            }
            $newId = empty(self::$mockCustomers) ? 1 : (max(array_column(self::$mockCustomers, 'id')) + 1);
            self::$mockCustomers[] = [
                'id' => $newId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'total_bookings' => 0,
            ];
            if (self::$mockUsers !== null) {
                $this->getAllUsers();
            }
            return $newId;
        }

        try {
            $this->connection->executeStatement(
                'INSERT INTO customers (first_name, last_name, email, phone) VALUES (:first, :last, :email, :phone)',
                [
                    'first' => $firstName,
                    'last' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                ]
            );
            $customerId = (int) $this->connection->lastInsertId();

            $existingUserId = $this->connection->fetchOne('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
            if ($existingUserId === false) {
                $this->connection->executeStatement(
                    'INSERT INTO users (name, email, password_hash, role, is_active, created_at, updated_at) VALUES (:name, :email, :hash, :role, 1, NOW(), NOW())',
                    [
                        'name' => $fullName,
                        'email' => $email,
                        'hash' => password_hash('password', PASSWORD_BCRYPT),
                        'role' => 'ROLE_CUSTOMER',
                    ]
                );
                $newUserId = (int) $this->connection->lastInsertId();
                $this->connection->executeStatement('UPDATE customers SET user_id = :userId WHERE id = :id', ['userId' => $newUserId, 'id' => $customerId]);
            } else {
                $this->connection->executeStatement('UPDATE customers SET user_id = :userId WHERE id = :id', ['userId' => (int) $existingUserId, 'id' => $customerId]);
            }

            $this->logActivity(
                'customer_created',
                'Active',
                null,
                sprintf('Created customer profile for %s (%s).', $fullName, $email)
            );

            return $customerId;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function updateCustomer(int $customerId, string $name, string $email, string $phone, ?string $firstName = null, ?string $lastName = null): bool
    {
        $name = trim($name);
        $email = trim($email);
        $phone = trim($phone);

        if ($firstName === null || $lastName === null) {
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0] ?? 'Client';
            $lastName = $nameParts[1] ?? '';
        }
        if ($name === '') {
            $name = trim($firstName . ' ' . $lastName);
        }

        if (!$this->canUseDatabase()) {
            if (self::$mockCustomers === null) {
                $this->getAllCustomers();
            }
            foreach (self::$mockCustomers as &$c) {
                if ($c['id'] === $customerId) {
                    $c['first_name'] = $firstName;
                    $c['last_name'] = $lastName;
                    $c['email'] = $email;
                    $c['phone'] = $phone;
                    break;
                }
            }

            if (self::$mockAppointments !== null) {
                foreach (self::$mockAppointments as &$app) {
                    if ($app['customer_id'] === $customerId) {
                        $app['customer_name'] = $name;
                        $app['email'] = $email;
                        $app['phone'] = $phone;
                    }
                }
            }

            if (self::$mockUsers !== null) {
                foreach (self::$mockUsers as &$u) {
                    if (strtolower($u['email']) === strtolower($email)) {
                        $u['name'] = $name;
                        $u['email'] = $email;
                    }
                }
            }

            return true;
        }

        try {
            $oldCustomer = $this->connection->fetchAssociative(
                'SELECT email, user_id FROM customers WHERE id = :id LIMIT 1',
                ['id' => $customerId]
            );

            $this->connection->executeStatement(
                'UPDATE customers SET first_name = :first, last_name = :last, email = :email, phone = :phone WHERE id = :id',
                [
                    'first' => $firstName,
                    'last' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'id' => $customerId,
                ]
            );

            $userId = $oldCustomer['user_id'] ?? null;
            $oldEmail = (string) ($oldCustomer['email'] ?? $email);

            if ($userId) {
                $this->connection->executeStatement(
                    'UPDATE users SET name = :name, email = :email WHERE id = :userId',
                    ['name' => $name, 'email' => $email, 'userId' => $userId]
                );
            } else {
                $existingUserId = $this->connection->fetchOne(
                    'SELECT id FROM users WHERE email = :oldEmail OR email = :email LIMIT 1',
                    ['oldEmail' => $oldEmail, 'email' => $email]
                );
                if ($existingUserId) {
                    $this->connection->executeStatement(
                        'UPDATE users SET name = :name, email = :email WHERE id = :userId',
                        ['name' => $name, 'email' => $email, 'userId' => (int) $existingUserId]
                    );
                    $this->connection->executeStatement(
                        'UPDATE customers SET user_id = :userId WHERE id = :id',
                        ['userId' => (int) $existingUserId, 'id' => $customerId]
                    );
                } else {
                    $this->connection->executeStatement(
                        'INSERT INTO users (name, email, password_hash, role, is_active, created_at, updated_at) VALUES (:name, :email, :hash, :role, 1, NOW(), NOW())',
                        [
                            'name' => $name,
                            'email' => $email,
                            'hash' => password_hash('password', PASSWORD_BCRYPT),
                            'role' => 'ROLE_CUSTOMER',
                        ]
                    );
                    $newUserId = (int) $this->connection->lastInsertId();
                    $this->connection->executeStatement(
                        'UPDATE customers SET user_id = :userId WHERE id = :id',
                        ['userId' => $newUserId, 'id' => $customerId]
                    );
                }
            }

            $this->logActivity(
                'customer_updated',
                'Updated',
                null,
                sprintf('Updated client #%d (%s, %s).', $customerId, $name, $email)
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteCustomer(int $id): bool
    {
        if (!$this->canUseDatabase()) {
            if (self::$mockCustomers === null) {
                $this->getAllCustomers();
            }
            $targetEmail = '';
            foreach (self::$mockCustomers as $c) {
                if ($c['id'] === $id) {
                    $targetEmail = $c['email'];
                    break;
                }
            }
            self::$mockCustomers = array_values(array_filter(
                self::$mockCustomers,
                static fn (array $c): bool => $c['id'] !== $id
            ));
            if (self::$mockAppointments !== null) {
                self::$mockAppointments = array_values(array_filter(
                    self::$mockAppointments,
                    static fn (array $a): bool => $a['customer_id'] !== $id
                ));
            }
            if ($targetEmail !== '' && self::$mockUsers !== null) {
                self::$mockUsers = array_values(array_filter(
                    self::$mockUsers,
                    static fn (array $u): bool => strtolower($u['email']) !== strtolower($targetEmail) || $u['role'] === 'ROLE_ADMIN'
                ));
            }
            return true;
        }

        try {
            $customer = $this->connection->fetchAssociative('SELECT email, user_id FROM customers WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$customer) {
                return false;
            }

            $this->connection->executeStatement('DELETE FROM appointments WHERE customer_id = :id', ['id' => $id]);
            $this->connection->executeStatement('DELETE FROM customers WHERE id = :id', ['id' => $id]);

            if (!empty($customer['user_id'])) {
                $this->connection->executeStatement('DELETE FROM users WHERE id = :userId AND role = "ROLE_CUSTOMER"', ['userId' => $customer['user_id']]);
            }

            $this->logActivity(
                'customer_deleted',
                'Removed',
                null,
                sprintf('Customer #%d (%s) removed.', $id, $customer['email'] ?? '')
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function createUser(string $name, string $email, string $role, bool $isActive = true): void
    {
        if (!$this->canUseDatabase()) {
            if (self::$mockUsers === null) {
                $this->getAllUsers();
            }
            $maxId = empty(self::$mockUsers) ? 1 : (max(array_column(self::$mockUsers, 'id')) + 1);
            self::$mockUsers[] = [
                'id' => $maxId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'is_active' => $isActive,
                'created_at' => date('Y-m-d H:i:s'),
            ];
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
            if (self::$mockUsers === null) {
                $this->getAllUsers();
            }
            foreach (self::$mockUsers as &$u) {
                if ($u['id'] === $id) {
                    $u['role'] = $role;
                    $u['is_active'] = $isActive;
                    break;
                }
            }
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
            if (self::$mockUsers === null) {
                $this->getAllUsers();
            }
            self::$mockUsers = array_values(array_filter(
                self::$mockUsers,
                static fn (array $u): bool => $u['id'] !== $id
            ));
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
