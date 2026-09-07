<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = (currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Mã khách thuê không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// 1. Kiểm tra tồn tại khách thuê
$stmt = $pdo->prepare('SELECT HoTen FROM KhachThue WHERE MaKhach = ?');
$stmt->execute([$id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    setFlash('error', 'Không tìm thấy thông tin khách thuê cần xóa.');
    redirect($baseUrl . '/index.php');
}

// 2. Kiểm tra ràng buộc dữ liệu: Hợp đồng (HopDong)
$checkHopDong = $pdo->prepare('SELECT COUNT(*) FROM HopDong WHERE MaKhach = ?');
$checkHopDong->execute([$id]);
$countHopDong = (int)$checkHopDong->fetchColumn();

// 3. Kiểm tra ràng buộc dữ liệu: Yêu cầu bảo trì (YeuCauBaoTri)
$checkBaoTri = $pdo->prepare('SELECT COUNT(*) FROM YeuCauBaoTri WHERE MaKhach = ?');
$checkBaoTri->execute([$id]);
$countBaoTri = (int)$checkBaoTri->fetchColumn();

// Nếu có hợp đồng hoặc bảo trì liên quan -> KHÔNG ĐƯỢC XÓA
if ($countHopDong > 0 || $countBaoTri > 0) {
    setFlash('error', 'Không thể xóa khách thuê vì đang có dữ liệu hợp đồng/bảo trì liên quan.');
    redirect($baseUrl . '/index.php');
}

// 4. Nếu không có ràng buộc -> Thực hiện xóa
try {
    $deleteStmt = $pdo->prepare('DELETE FROM KhachThue WHERE MaKhach = ?');
    $deleteStmt->execute([$id]);

    setFlash('success', 'Đã xóa thành công khách thuê "' . $tenant['HoTen'] . '".');
} catch (PDOException $ex) {
    setFlash('error', 'Lỗi khi xóa khách thuê: ' . $ex->getMessage());
}

redirect($baseUrl . '/index.php');
