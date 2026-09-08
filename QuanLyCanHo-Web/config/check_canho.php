<?php

declare(strict_types=1);

$pdo = require __DIR__ . '/database.php';
$rows = $pdo->query('SELECT MaCanHo, SoPhong, DiaChi, TrangThai FROM CanHo')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
