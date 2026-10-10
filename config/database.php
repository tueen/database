<?php

declare(strict_types=1);

use Tueen\Database\Config\ConnectionConfig;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\Security\CredentialManager;

// Load environment variables if available
CredentialManager::loadEnv();

$driver = CredentialManager::get('DB_DRIVER', 'pdo_sqlite');

$connection = match ($driver) {
    'pdo_mysql' => ConnectionConfig::mysql(
        dbname: CredentialManager::get('DB_NAME', 'tueen_bot') ?? 'tueen_bot',
        user: CredentialManager::get('DB_USER', 'root') ?? 'root',
        password: CredentialManager::get('DB_PASSWORD'),
        host: CredentialManager::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1',
        port: CredentialManager::getInt('DB_PORT', 3306),
    ),
    'pdo_pgsql' => ConnectionConfig::postgres(
        dbname: CredentialManager::get('DB_NAME', 'tueen_bot') ?? 'tueen_bot',
        user: CredentialManager::get('DB_USER', 'postgres') ?? 'postgres',
        password: CredentialManager::get('DB_PASSWORD'),
        host: CredentialManager::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1',
        port: CredentialManager::getInt('DB_PORT', 5432),
    ),
    default => ConnectionConfig::sqlite(
        path: CredentialManager::get('DB_PATH', getcwd() . '/var/database.sqlite') ?? (getcwd() . '/var/database.sqlite'),
    ),
};

return new DatabaseConfig(
    connection: $connection,
    autoMigrate: CredentialManager::getBool('DB_AUTO_MIGRATE', true),
    devMode: CredentialManager::getBool('DB_DEV_MODE', false),
);
