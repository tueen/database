<?php

declare(strict_types=1);

namespace Tueen\Database\Config;

final class DatabaseConfig
{
    /**
     * @param list<string> $entityPaths
     * @param array<string, string> $migrationPaths
     */
    public function __construct(
        public private(set) ConnectionConfig $connection,
        public private(set) array $entityPaths = [],
        public private(set) array $migrationPaths = [],
        public private(set) bool $autoMigrate = true,
        public private(set) bool $lazyMigration = true,
        public private(set) bool $devMode = false,
        public private(set) ?string $proxyDir = null,
        public private(set) string $cacheDriver = 'array',
        public private(set) ?string $cacheDsn = null,
        public private(set) string $defaultCurrency = 'XTR',
    ) {
        $envCurrency = $_ENV['TUEEN_DEFAULT_CURRENCY'] ?? $_SERVER['TUEEN_DEFAULT_CURRENCY'] ?? getenv('TUEEN_DEFAULT_CURRENCY');
        if ($defaultCurrency === 'XTR' && is_string($envCurrency) && trim($envCurrency) !== '') {
            $this->defaultCurrency = strtoupper(trim($envCurrency));
        } else {
            $this->defaultCurrency = strtoupper(trim($defaultCurrency));
        }

        if ($this->entityPaths === []) {
            $this->entityPaths = [dirname(__DIR__) . '/Entity'];
        }

        if ($this->migrationPaths === []) {
            $this->migrationPaths = [
                'Tueen\\Database\\Migration' => dirname(__DIR__, 2) . '/migrations',
            ];
        }

        if ($this->proxyDir === null) {
            $this->proxyDir = sys_get_temp_dir() . '/tueen_database_proxies';
        }
    }

    public static function createSqlite(string $path = ':memory:', bool $autoMigrate = true): self
    {
        return new self(
            connection: ConnectionConfig::sqlite($path),
            autoMigrate: $autoMigrate,
        );
    }

    public static function createMysql(
        string $dbname,
        string $user = 'root',
        ?string $password = null,
        string $host = '127.0.0.1',
        int $port = 3306,
        bool $autoMigrate = true,
    ): self {
        return new self(
            connection: ConnectionConfig::mysql($dbname, $user, $password, $host, $port),
            autoMigrate: $autoMigrate,
        );
    }

    public static function createPostgres(
        string $dbname,
        string $user = 'postgres',
        ?string $password = null,
        string $host = '127.0.0.1',
        int $port = 5432,
        bool $autoMigrate = true,
    ): self {
        return new self(
            connection: ConnectionConfig::postgres($dbname, $user, $password, $host, $port),
            autoMigrate: $autoMigrate,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        $connection = isset($options['connection']) && is_array($options['connection'])
            ? ConnectionConfig::fromArray($options['connection'])
            : ConnectionConfig::sqlite();

        /** @var list<string> $entityPaths */
        $entityPaths = array_values(array_map('strval', (array) ($options['entity_paths'] ?? [])));
        /** @var array<string, string> $migrationPaths */
        $migrationPaths = (array) ($options['migration_paths'] ?? []);

        return new self(
            connection: $connection,
            entityPaths: $entityPaths,
            migrationPaths: $migrationPaths,
            autoMigrate: filter_var($options['auto_migrate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            lazyMigration: filter_var($options['lazy_migration'] ?? true, FILTER_VALIDATE_BOOLEAN),
            devMode: filter_var($options['dev_mode'] ?? false, FILTER_VALIDATE_BOOLEAN),
            proxyDir: isset($options['proxy_dir']) ? (string) $options['proxy_dir'] : null,
            cacheDriver: (string) ($options['cache_driver'] ?? 'array'),
            cacheDsn: isset($options['cache_dsn']) ? (string) $options['cache_dsn'] : null,
            defaultCurrency: (string) ($options['default_currency'] ?? 'XTR'),
        );
    }

    public function withDefaultCurrency(string $currency): self
    {
        return new self(
            connection: $this->connection,
            entityPaths: $this->entityPaths,
            migrationPaths: $this->migrationPaths,
            autoMigrate: $this->autoMigrate,
            lazyMigration: $this->lazyMigration,
            devMode: $this->devMode,
            proxyDir: $this->proxyDir,
            cacheDriver: $this->cacheDriver,
            cacheDsn: $this->cacheDsn,
            defaultCurrency: strtoupper(trim($currency)),
        );
    }

    public function withAdditionalEntityPath(string $path): self
    {
        $paths = $this->entityPaths;
        if (!in_array($path, $paths, true)) {
            $paths[] = $path;
        }

        return new self(
            connection: $this->connection,
            entityPaths: $paths,
            migrationPaths: $this->migrationPaths,
            autoMigrate: $this->autoMigrate,
            lazyMigration: $this->lazyMigration,
            devMode: $this->devMode,
            proxyDir: $this->proxyDir,
            cacheDriver: $this->cacheDriver,
            cacheDsn: $this->cacheDsn,
            defaultCurrency: $this->defaultCurrency,
        );
    }

    public function withAdditionalMigrationPath(string $namespace, string $path): self
    {
        $migrations = $this->migrationPaths;
        $migrations[$namespace] = $path;

        return new self(
            connection: $this->connection,
            entityPaths: $this->entityPaths,
            migrationPaths: $migrations,
            autoMigrate: $this->autoMigrate,
            lazyMigration: $this->lazyMigration,
            devMode: $this->devMode,
            proxyDir: $this->proxyDir,
            cacheDriver: $this->cacheDriver,
            cacheDsn: $this->cacheDsn,
            defaultCurrency: $this->defaultCurrency,
        );
    }
}
