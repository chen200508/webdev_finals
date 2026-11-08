<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function createFilterQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.service', 's')
            ->leftJoin('a.staff', 'st')
            ->orderBy('a.id', 'ASC');

        if (!empty($filters['service'])) {
            $qb->andWhere('s.name = :service')
               ->setParameter('service', (string) $filters['service']);
        }

        if (!empty($filters['staff_id'])) {
            $qb->andWhere('st.id = :staffId')
               ->setParameter('staffId', (int) $filters['staff_id']);
        }

        if (!empty($filters['status_id'])) {
            $qb->andWhere('a.statusId = :statusId')
               ->setParameter('statusId', (int) $filters['status_id']);
        }

        if (!empty($filters['appointment_date'])) {
            $qb->andWhere('a.appointmentDate = :appointmentDate')
               ->setParameter('appointmentDate', (string) $filters['appointment_date']);
        }

        return $qb;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    public function findByFilters(array $filters, array $records = []): array
    {
        return array_values(array_filter($records, static function (array $row) use ($filters): bool {
            if (!empty($filters['service']) && ($row['service_name'] ?? '') !== (string) $filters['service']) {
                return false;
            }

            if (!empty($filters['staff_id']) && (int) ($row['staff_id'] ?? 0) !== (int) $filters['staff_id']) {
                return false;
            }

            if (!empty($filters['status_id']) && (int) ($row['status_id'] ?? 0) !== (int) $filters['status_id']) {
                return false;
            }

            if (!empty($filters['appointment_date']) && ($row['appointment_date'] ?? '') !== (string) $filters['appointment_date']) {
                return false;
            }

            return true;
        }));
    }
}
