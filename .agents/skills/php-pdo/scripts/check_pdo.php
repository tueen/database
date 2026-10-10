<?php

declare(strict_types=1);

/**
 * PDO Environment Diagnostics & Connectivity Checker
 * 
 * Usage:
 *   php check_pdo.php
 *   php check_pdo.php "mysql:host=127.0.0.1;dbname=test;charset=utf8mb4" root secret
 */

echo "====================================================\n";
echo "       PHP PDO Diagnostics & Connectivity Tool      \n";
echo "====================================================\n\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI:    " . PHP_SAPI . "\n\n";

// 1. Check PDO extension
if (!extension_loaded('pdo')) {
    echo "[FAIL] The core 'pdo' extension is NOT loaded in your php.ini.\n";
    exit(1);
}
echo "[PASS] Core 'pdo' extension is loaded.\n\n";

// 2. Enumerate drivers
$drivers = PDO::getAvailableDrivers();
echo "Installed PDO Drivers (" . count($drivers) . "):\n";
if (empty($drivers)) {
    echo "  [WARNING] No PDO drivers found. Enable pdo_mysql, pdo_pgsql, or pdo_sqlite in php.ini.\n";
} else {
    foreach ($drivers as $driver) {
        echo "  - pdo_" . $driver . "\n";
    }
}
echo "\n";

// 3. Quick SQLite Self-Test if pdo_sqlite is available
if (in_array('sqlite', $drivers, true)) {
    echo "Running In-Memory SQLite Self-Test...\n";
    try {
        $testPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $testPdo->exec('CREATE TABLE test_diag (id INTEGER PRIMARY KEY, msg TEXT)');
        $stmt = $testPdo->prepare('INSERT INTO test_diag (msg) VALUES (:m)');
        $stmt->execute(['m' => 'PDO is operational!']);

        $res = $testPdo->query('SELECT msg FROM test_diag WHERE id = 1')->fetch();
        echo "  [PASS] SQLite In-Memory verification succeeded: '" . $res['msg'] . "'\n\n";
    } catch (Throwable $e) {
        echo "  [FAIL] SQLite Self-Test failed: " . $e->getMessage() . "\n\n";
    }
}

// 4. Custom DSN connection test if supplied via CLI arguments
if ($argc >= 2) {
    $dsn  = $argv[1];
    $user = $argv[2] ?? null;
    $pass = $argv[3] ?? null;

    echo "Testing custom DSN: " . $dsn . "\n";
    try {
        $customPdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        echo "[PASS] Successfully connected to custom DSN!\n";
        echo "  Server Version: " . $customPdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
        echo "  Driver:         " . $customPdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    } catch (Throwable $e) {
        echo "[FAIL] Connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Tip: You can test any DSN by passing it as arguments:\n";
    echo "  php check_pdo.php \"mysql:host=127.0.0.1;dbname=app;charset=utf8mb4\" root password\n";
}
echo "\n====================================================\n";
