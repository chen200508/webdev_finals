<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function createFilterQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC');

        if (!empty($filters['keyword'])) {
            $kw = '%' . strtolower((string) $filters['keyword']) . '%';
            $qb->andWhere('LOWER(c.firstName) LIKE :kw OR LOWER(c.lastName) LIKE :kw OR LOWER(c.email) LIKE :kw OR LOWER(c.phone) LIKE :kw')
               ->setParameter('kw', $kw);
        }

        if (!empty($filters['email_domain'])) {
            $qb->andWhere('c.email LIKE :domain')
               ->setParameter('domain', '%' . (string) $filters['email_domain']);
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
            $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $email = (string) ($row['email'] ?? '');
            $phone = (string) ($row['phone'] ?? '');

            if (!empty($filters['keyword'])) {
                $needle = strtolower((string) $filters['keyword']);
                $haystack = strtolower($fullName . ' ' . $email . ' ' . $phone);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            if (!empty($filters['email_domain'])) {
                $domain = strtolower((string) $filters['email_domain']);
                if (!str_ends_with(strtolower($email), $domain)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
