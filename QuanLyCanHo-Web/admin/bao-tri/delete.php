<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/bao-tri' : '/user/bao-tri');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Mã bảo trì không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// 1. Kiểm tra tồn tại
$stmt = $pdo->prepare('SELECT MaBaoTri FROM YeuCauBaoTri WHERE MaBaoTri = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('error', 'Không tìm thấy yêu cầu bảo trì cần xóa.');
    redirect($baseUrl . '/index.php');
}

// 2. Thực hiện xóa
try {
    $deleteStmt = $pdo->prepare('DELETE FROM YeuCauBaoTri WHERE MaBaoTri = ?');
    $deleteStmt->execute([$id]);

    setFlash('success', 'Đã xóa thành công yêu cầu bảo trì #' . $id . '.');
} catch (PDOException $ex) {
    setFlash('error', 'Lỗi khi xóa yêu cầu bảo trì: ' . $ex->getMessage());
}

redirect($baseUrl . '/index.php');
