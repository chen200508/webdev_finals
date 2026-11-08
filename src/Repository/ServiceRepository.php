<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function createFilterQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->orderBy('s.id', 'ASC');

        if (!empty($filters['category'])) {
            $qb->andWhere('c.name = :category')
               ->setParameter('category', (string) $filters['category']);
        }

        if (!empty($filters['duration'])) {
            $duration = (int) $filters['duration'];
            if ($duration <= 45) {
                $qb->andWhere('s.durationMinutes <= 45');
            } elseif ($duration >= 120) {
                $qb->andWhere('s.durationMinutes >= 120');
            } else {
                $qb->andWhere('s.durationMinutes = :duration')
                   ->setParameter('duration', $duration);
            }
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
            if (!empty($filters['category']) && ($row['category_name'] ?? '') !== (string) $filters['category']) {
                return false;
            }

            if (!empty($filters['duration'])) {
                $target = (int) $filters['duration'];
                $minutes = (int) ($row['duration_minutes'] ?? 0);
                if ($target <= 45 && $minutes > 45) {
                    return false;
                }
                if ($target >= 120 && $minutes < 120) {
                    return false;
                }
                if ($target > 45 && $target < 120 && $minutes !== $target) {
                    return false;
                }
            }

            return true;
        }));
    }
}
