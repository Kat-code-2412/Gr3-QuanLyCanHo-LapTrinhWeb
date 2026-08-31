<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

try {
    $pdo = require __DIR__ . '/database.php';
    $stmt = $pdo->query('SELECT DATABASE() AS db_name');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo 'Kết nối database thành công.<br>';
    echo 'Database hiện tại: ' . htmlspecialchars((string)($row['db_name'] ?? 'unknown'), ENT_QUOTES, 'UTF-8') . '<br>';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Kết nối database thất bại: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
