<?php

declare(strict_types=1);

namespace App\Database\DbalExamples;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Generator;
use Throwable;

final class DbalService
{
    private Connection $connection;

    public function __construct(array $params = [])
    {
        $defaultParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $config = new Configuration();
        $this->connection = DriverManager::getConnection(array_merge($defaultParams, $params), $config);
        $this->initializeSchema();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    private function initializeSchema(): void
    {
        $this->connection->executeStatement('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                role VARCHAR(50) NOT NULL,
                balance INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL
            )
        ');
    }

    /**
     * 1. Insert record and return generated ID.
     */
    public function createUser(string $name, string $email, string $role = 'member', int $balance = 0): int
    {
        $this->connection->insert('users', [
            'name'       => $name,
            'email'      => $email,
            'role'       => $role,
            'balance'    => $balance,
            'created_at' => date('Y-m-d H:i:s'),
        ], [
            'balance'    => ParameterType::INTEGER,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * 2. Find user by ID using fetchAssociative.
     */
    public function findUserById(int $id): ?array
    {
        $result = $this->connection->fetchAssociative(
            'SELECT id, name, email, role, balance, created_at FROM users WHERE id = :id',
            ['id' => $id],
            ['id' => ParameterType::INTEGER]
        );

        return $result !== false ? $result : null;
    }

    /**
     * 3. Fetching list of users using QueryBuilder with typed array parameters.
     *
     * @param list<string> $roles
     * @return list<array<string, mixed>>
     */
    public function findUsersByRoles(array $roles, int $limit = 50): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('id', 'name', 'email', 'role')
           ->from('users')
           ->where('role IN (:roles)')
           ->setParameter('roles', $roles, ArrayParameterType::STRING)
           ->orderBy('id', 'ASC')
           ->setMaxResults($limit);

        return $qb->fetchAllAssociative();
    }

    /**
     * 4. Atomic balance transfer using transactional callback.
     */
    public function transferFunds(int $fromUserId, int $toUserId, int $amount): void
    {
        $this->connection->transactional(function (Connection $conn) use ($fromUserId, $toUserId, $amount): void {
            $senderBalance = (int) $conn->fetchOne(
                'SELECT balance FROM users WHERE id = :id',
                ['id' => $fromUserId],
                ['id' => ParameterType::INTEGER]
            );

            if ($senderBalance < $amount) {
                throw new \UnderflowException('Insufficient account balance.');
            }

            $conn->executeStatement(
                'UPDATE users SET balance = balance - :amount WHERE id = :id',
                ['amount' => $amount, 'id' => $fromUserId],
                ['amount' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER]
            );

            $conn->executeStatement(
                'UPDATE users SET balance = balance + :amount WHERE id = :id',
                ['amount' => $amount, 'id' => $toUserId],
                ['amount' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER]
            );
        });
    }

    /**
     * 5. Stream large datasets using iterateAssociative.
     *
     * @return Generator<array<string, mixed>>
     */
    public function streamAllUsers(): Generator
    {
        $result = $this->connection->executeQuery('SELECT id, name, email, role FROM users');

        foreach ($result->iterateAssociative() as $row) {
            yield $row;
        }
    }
}
