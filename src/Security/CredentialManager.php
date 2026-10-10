<?php

declare(strict_types=1);

namespace Tueen\Database\Security;

use Symfony\Component\Dotenv\Dotenv;

final class CredentialManager
{
    private static bool $dotenvLoaded = false;

    public static function loadEnv(?string $envFile = null): void
    {
        if (self::$dotenvLoaded) {
            return;
        }

        $dotenv = new Dotenv();

        if ($envFile !== null && file_exists($envFile)) {
            $dotenv->load($envFile);
            self::$dotenvLoaded = true;
            return;
        }

        $defaultPath = getcwd() . '/.env';
        if (file_exists($defaultPath)) {
            $dotenv->load($defaultPath);
            self::$dotenvLoaded = true;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::loadEnv();

        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return (string) $val;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }

        return $default;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null) {
            throw new \RuntimeException("Required credential / environment variable [{$key}] is not set.");
        }

        return $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === null) {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::get($key);
        if ($val === null) {
            return $default;
        }

        return (int) $val;
    }
}
