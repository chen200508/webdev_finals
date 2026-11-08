<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function createFilterQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC');

        if (!empty($filters['event_type'])) {
            $qb->andWhere('l.eventType = :eventType')
               ->setParameter('eventType', (string) $filters['event_type']);
        }

        if (!empty($filters['performed_by'])) {
            $qb->andWhere('l.performedBy = :performedBy')
               ->setParameter('performedBy', (string) $filters['performed_by']);
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
            if (!empty($filters['event_type']) && ($row['event_type'] ?? '') !== (string) $filters['event_type']) {
                return false;
            }

            if (!empty($filters['performed_by']) && ($row['performed_by'] ?? '') !== (string) $filters['performed_by']) {
                return false;
            }

            return true;
        }));
    }
}
