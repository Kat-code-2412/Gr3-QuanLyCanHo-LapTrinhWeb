<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$role = currentUserRole();
$baseUrl = url(($role === 'Admin') ? '/admin/hop-dong' : '/user/hop-dong');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã hợp đồng không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// 1. Kiểm tra tồn tại hợp đồng
$stmt = $pdo->prepare('SELECT hp.*, ch.SoPhong FROM HopDong hp JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo WHERE hp.MaHopDong = ?');
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    setFlash('error', 'Không tìm thấy thông tin hợp đồng cần thanh lý.');
    redirect($baseUrl . '/index.php');
}

// 2. Thực hiện Thanh lý hợp đồng trong DB Transaction
try {
    $pdo->beginTransaction();

    // A. Cập nhật Hợp đồng -> Đã thanh lý, lưu ngày Check-out thực tế
    $today = date('Y-m-d');
    $timeNow = date('H:i');
    $updateHd = $pdo->prepare("UPDATE HopDong SET TrangThai = 'Đã thanh lý' WHERE MaHopDong = ?");
    $updateHd->execute([$id]);

    // B. Cập nhật Căn hộ -> Trống
    $updateCh = $pdo->prepare("UPDATE CanHo SET TrangThai = 'Trống' WHERE MaCanHo = ?");
    $updateCh->execute([$contract['MaCanHo']]);

    $pdo->commit();

    setFlash('success', 'Thanh lý Hợp đồng #' . $id . ' thành công! Phòng ' . $contract['SoPhong'] . ' đã chuyển về trạng thái Trống.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', 'Lỗi khi thanh lý hợp đồng: ' . $e->getMessage());
}

redirect($baseUrl . '/index.php');
