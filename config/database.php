<?php
declare(strict_types=1);

$configFile = __DIR__ . '/database.local.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Database configuration missing. Create config/database.local.php.');
}

$config = require $configFile;

$host = $config['host'];
$db   = $config['dbname'];
$user = $config['username'];
$pass = $config['password'];

$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Start MySQL in XAMPP and import database/database.sql.');
}
