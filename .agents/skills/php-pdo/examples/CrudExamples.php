<?php

declare(strict_types=1);

namespace App\Database\Examples;

use Generator;
use PDO;

final class UserDTO
{
    public int $id;
    public string $name;
    public string $email;
    public string $status;
    public string $created_at;
}

final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * 1. Find a single record by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, status, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch();
        return $user !== false ? $user : null;
    }

    /**
     * 2. Find and map directly into a strongly-typed class (PDO::FETCH_CLASS).
     */
    public function findByIdAsDto(int $id): ?UserDTO
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, status, created_at FROM users WHERE id = :id');
        $stmt->setFetchMode(PDO::FETCH_CLASS, UserDTO::class);
        $stmt->execute(['id' => $id]);

        $dto = $stmt->fetch();
        return $dto instanceof UserDTO ? $dto : null;
    }

    /**
     * 3. Fetch a single column list (e.g. array of active user email strings).
     *
     * @return list<string>
     */
    public function getActiveEmails(): array
    {
        $stmt = $this->pdo->prepare("SELECT email FROM users WHERE status = 'active'");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * 4. Fetch Key-Value pairs (e.g. [id => name]).
     *
     * @return array<int, string>
     */
    public function getIdToNameMap(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM users');
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * 5. Insert record and return inserted ID.
     */
    public function create(string $name, string $email, string $status = 'active'): int
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            // PostgreSQL RETURNING pattern
            $stmt = $this->pdo->prepare('
                INSERT INTO users (name, email, status)
                VALUES (:name, :email, :status)
                RETURNING id
            ');
            $stmt->execute([
                'name'   => $name,
                'email'  => $email,
                'status' => $status,
            ]);
            return (int) $stmt->fetchColumn();
        }

        // MySQL & SQLite pattern
        $stmt = $this->pdo->prepare('
            INSERT INTO users (name, email, status)
            VALUES (:name, :email, :status)
        ');
        $stmt->execute([
            'name'   => $name,
            'email'  => $email,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 6. Update record and check affected rows count.
     */
    public function updateStatus(int $userId, string $newStatus): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute([
            'status' => $newStatus,
            'id'     => $userId,
        ]);

        // rowCount() returns number of rows affected by UPDATE/DELETE
        return $stmt->rowCount() > 0;
    }

    /**
     * 7. Delete record.
     */
    public function delete(int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 8. Stream massive result set using PHP Generator to keep memory footprint minimal.
     *
     * @return Generator<array<string, mixed>>
     */
    public function streamAllUsers(): Generator
    {
        $stmt = $this->pdo->query('SELECT id, name, email, created_at FROM users');

        // Iterate over statement directly without fetchAll()
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }
}
