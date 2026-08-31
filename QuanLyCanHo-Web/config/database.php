<?php

declare(strict_types=1);

$envFile = __DIR__ . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost';
$dbUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'quanlycandichvu';
$dbCharset = $_ENV['DB_CHARSET'] ?? $_SERVER['DB_CHARSET'] ?? 'utf8mb4';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbHost, $dbName, $dbCharset);

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    throw new RuntimeException('Kết nối database thất bại: ' . $e->getMessage());
}

return $pdo;
