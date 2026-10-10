<?php

declare(strict_types=1);

namespace App\Database;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Production-ready PDO Database Connection Factory & Manager.
 * 
 * Supports MySQL, PostgreSQL, SQLite, and SQL Server with best-practice
 * default options, transaction encapsulation, and PRAGMA configuration.
 */
final class Database
{
    private ?PDO $pdo = null;

    /**
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(
        private readonly array $config
    ) {
    }

    /**
     * Returns the active PDO instance, establishing it lazily if not already connected.
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createConnection();
        }

        return $this->pdo;
    }

    /**
     * Creates and configures a driver-specific PDO instance.
     */
    private function createConnection(): PDO
    {
        $driver = strtolower((string) ($this->config['driver'] ?? 'mysql'));

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        // Merge any user-provided PDO attributes
        if (!empty($this->config['options']) && is_array($this->config['options'])) {
            $options = array_replace($options, $this->config['options']);
        }

        try {
            switch ($driver) {
                case 'mysql':
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                        $this->config['host'] ?? '127.0.0.1',
                        (int) ($this->config['port'] ?? 3306),
                        $this->config['database'] ?? '',
                        $this->config['charset'] ?? 'utf8mb4'
                    );
                    $pdo = new PDO($dsn, $this->config['username'] ?? null, $this->config['password'] ?? null, $options);
                    break;

                case 'pgsql':
                case 'postgres':
                case 'postgresql':
                    $optionsList = [];
                    if (!empty($this->config['search_path'])) {
                        $optionsList[] = sprintf('-c search_path=%s', $this->config['search_path']);
                    }
                    $optionsString = !empty($optionsList) ? sprintf(";options='%s'", implode(' ', $optionsList)) : '';

                    $dsn = sprintf(
                        'pgsql:host=%s;port=%d;dbname=%s%s',
                        $this->config['host'] ?? '127.0.0.1',
                        (int) ($this->config['port'] ?? 5432),
                        $this->config['database'] ?? '',
                        $optionsString
                    );
                    $pdo = new PDO($dsn, $this->config['username'] ?? null, $this->config['password'] ?? null, $options);
                    break;

                case 'sqlite':
                    $path = $this->config['database'] ?? ':memory:';
                    $dsn = 'sqlite:' . $path;
                    $pdo = new PDO($dsn, null, null, $options);

                    // Essential SQLite production PRAGMAs
                    $pdo->exec('PRAGMA foreign_keys = ON;');
                    $pdo->exec('PRAGMA journal_mode = WAL;');
                    $pdo->exec('PRAGMA busy_timeout = 5000;');
                    $pdo->exec('PRAGMA synchronous = NORMAL;');
                    break;

                case 'sqlsrv':
                    $dsn = sprintf(
                        'sqlsrv:Server=%s,%d;Database=%s',
                        $this->config['host'] ?? '127.0.0.1',
                        (int) ($this->config['port'] ?? 1433),
                        $this->config['database'] ?? ''
                    );
                    $pdo = new PDO($dsn, $this->config['username'] ?? null, $this->config['password'] ?? null, $options);
                    break;

                default:
                    throw new InvalidArgumentException(sprintf('Unsupported database driver: "%s"', $driver));
            }

            return $pdo;
        } catch (PDOException $e) {
            // Do not leak database credentials or internal hosts in production logs
            error_log(sprintf('PDO Connection Failed [%s]: %s', $driver, $e->getMessage()));
            throw new RuntimeException('Unable to establish database connection.', 0, $e);
        }
    }

    /**
     * Executes a callable inside a managed database transaction.
     * Automatically commits on success and rolls back on any Throwable.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->getPdo();

        if ($pdo->inTransaction()) {
            // Already inside a transaction, run callback directly
            return $callback($pdo);
        }

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
