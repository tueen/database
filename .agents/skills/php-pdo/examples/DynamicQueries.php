<?php

declare(strict_types=1);

namespace App\Database\Examples;

use InvalidArgumentException;
use PDO;

final class DynamicQueryBuilder
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * 1. Safe Dynamic "WHERE IN (...)" query.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Generate dynamic placeholders: "?, ?, ?"
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $sql = "SELECT id, name, email FROM users WHERE id IN ({$placeholders}) ORDER BY id ASC";
        $stmt = $this->pdo->prepare($sql);

        // Execute passing array directly
        $stmt->execute(array_values($ids));

        return $stmt->fetchAll();
    }

    /**
     * 2. Safe Dynamic Sorting with Whitelist Validation.
     *
     * @return list<array<string, mixed>>
     */
    public function listSortedUsers(string $sortBy, string $direction = 'ASC'): array
    {
        $allowedColumns = [
            'id'         => 'id',
            'name'       => 'name',
            'email'      => 'email',
            'created_at' => 'created_at',
        ];

        // Fail-safe default or throw exception on invalid column
        if (!isset($allowedColumns[$sortBy])) {
            throw new InvalidArgumentException(sprintf('Invalid sort column "%s"', $sortBy));
        }

        $column = $allowedColumns[$sortBy];
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT id, name, email FROM users ORDER BY {$column} {$dir}";
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * 3. Safe Dynamic Filtering (Search Filter Builder).
     *
     * @param array{status?: string, search?: string, min_age?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function searchUsers(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(name LIKE :search OR email LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['min_age'])) {
            $conditions[] = 'age >= :min_age';
            $params['min_age'] = (int) $filters['min_age'];
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT id, name, email, status, age FROM users WHERE {$whereClause} ORDER BY id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * 4. Multi-Row Batch Insert (High performance bulk insert).
     *
     * @param list<array{name: string, email: string, role: string}> $users
     */
    public function bulkInsertUsers(array $users): int
    {
        if (empty($users)) {
            return 0;
        }

        $rowsCount = count($users);
        $rowPlaceholders = '(?, ?, ?)';
        $allPlaceholders = implode(', ', array_fill(0, $rowsCount, $rowPlaceholders));

        $sql = "INSERT INTO users (name, email, role) VALUES {$allPlaceholders}";

        $values = [];
        foreach ($users as $user) {
            $values[] = $user['name'];
            $values[] = $user['email'];
            $values[] = $user['role'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount();
    }
}
