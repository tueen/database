<?php

declare(strict_types=1);

namespace Tueen\Database\Config;

final class ConnectionConfig
{
    /**
     * @param array<string, mixed> $extraOptions
     */
    public function __construct(
        public private(set) string $driver = 'pdo_sqlite',
        public private(set) ?string $path = null,
        public private(set) ?string $host = null,
        public private(set) int $port = 3306,
        public private(set) ?string $dbname = null,
        public private(set) ?string $user = null,
        public private(set) ?string $password = null,
        public private(set) string $charset = 'utf8mb4',
        public private(set) array $extraOptions = [],
    ) {}

    public static function sqlite(string $path = ':memory:'): self
    {
        return new self(
            driver: 'pdo_sqlite',
            path: $path,
        );
    }

    public static function mysql(
        string $dbname,
        string $user = 'root',
        ?string $password = null,
        string $host = '127.0.0.1',
        int $port = 3306,
        string $charset = 'utf8mb4',
    ): self {
        return new self(
            driver: 'pdo_mysql',
            host: $host,
            port: $port,
            dbname: $dbname,
            user: $user,
            password: $password,
            charset: $charset,
        );
    }

    public static function postgres(
        string $dbname,
        string $user = 'postgres',
        ?string $password = null,
        string $host = '127.0.0.1',
        int $port = 5432,
        string $charset = 'utf8',
    ): self {
        return new self(
            driver: 'pdo_pgsql',
            host: $host,
            port: $port,
            dbname: $dbname,
            user: $user,
            password: $password,
            charset: $charset,
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            driver: (string) ($params['driver'] ?? 'pdo_sqlite'),
            path: isset($params['path']) ? (string) $params['path'] : null,
            host: isset($params['host']) ? (string) $params['host'] : null,
            port: isset($params['port']) ? (int) $params['port'] : 3306,
            dbname: isset($params['dbname']) ? (string) $params['dbname'] : null,
            user: isset($params['user']) ? (string) $params['user'] : null,
            password: isset($params['password']) ? (string) $params['password'] : null,
            charset: (string) ($params['charset'] ?? 'utf8mb4'),
            extraOptions: (array) ($params['options'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDbalParams(): array
    {
        $params = [
            'driver' => $this->driver,
        ];

        if ($this->driver === 'pdo_sqlite') {
            if ($this->path === ':memory:' || $this->path === null) {
                $params['memory'] = true;
            } else {
                $params['path'] = $this->path;
            }
            return array_merge($params, $this->extraOptions);
        }

        if ($this->host !== null) {
            $params['host'] = $this->host;
        }
        $params['port'] = $this->port;
        if ($this->dbname !== null) {
            $params['dbname'] = $this->dbname;
        }
        if ($this->user !== null) {
            $params['user'] = $this->user;
        }
        if ($this->password !== null) {
            $params['password'] = $this->password;
        }
        $params['charset'] = $this->charset;

        return array_merge($params, $this->extraOptions);
    }
}
