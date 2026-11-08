<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function createFilterQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC');

        if (!empty($filters['role'])) {
            $qb->andWhere('u.role = :role')
               ->setParameter('role', (string) $filters['role']);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $qb->andWhere('u.isActive = :isActive')
               ->setParameter('isActive', (string) $filters['status'] === '1');
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
            if (!empty($filters['role']) && ($row['role'] ?? '') !== (string) $filters['role']) {
                return false;
            }

            if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
                $expectedActive = (string) $filters['status'] === '1';
                if ((bool) ($row['is_active'] ?? false) !== $expectedActive) {
                    return false;
                }
            }

            return true;
        }));
    }
}
