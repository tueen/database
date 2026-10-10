<?php

declare(strict_types=1);

namespace App\Database\OrmExamples\Repository;

use App\Database\OrmExamples\Entity\Customer;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends EntityRepository<Customer>
 */
class CustomerRepository extends EntityRepository
{
    /**
     * Finds customers along with their orders in a single SQL query,
     * completely eliminating the N+1 query problem.
     *
     * @return list<Customer>
     */
    public function findCustomersWithRecentOrders(int $days = 30): array
    {
        $since = new DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('c')
            ->addSelect('o') // Eagerly loads orders into the same object graph
            ->leftJoin('c.orders', 'o')
            ->where('o.createdAt >= :since OR o.id IS NULL')
            ->setParameter('since', $since)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Safely paginates customers using Doctrine's Paginator.
     *
     * @return Paginator<Customer>
     */
    public function paginateCustomers(int $page = 1, int $perPage = 25): Paginator
    {
        $firstResult = ($page - 1) * $perPage;

        $query = $this->createQueryBuilder('c')
            ->addSelect('o')
            ->leftJoin('c.orders', 'o')
            ->orderBy('c.id', 'ASC')
            ->setFirstResult($firstResult)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query, fetchJoinCollection: true);
    }

    /**
     * Retrieves lightweight DTO summaries without hydrating heavy entities.
     *
     * @return list<array{id: int, name: string, email: string, order_count: int}>
     */
    public function getCustomerOrderCounts(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id', 'c.name', 'c.email', 'COUNT(o.id) AS order_count')
            ->leftJoin('c.orders', 'o')
            ->groupBy('c.id', 'c.name', 'c.email')
            ->orderBy('order_count', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
