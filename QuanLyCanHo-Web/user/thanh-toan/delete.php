<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$id = (int)($_REQUEST['id'] ?? 0);
$ky = trim((string)($_REQUEST['ky_thanh_toan'] ?? ''));

if ($id <= 0) {
    setFlash('error', 'Mã hóa đơn không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

try {
    $stmt = $pdo->prepare('SELECT hd.*, ch.SoPhong FROM HoaDon hd JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo WHERE hd.MaHoaDon = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        setFlash('error', 'Hóa đơn không tồn tại hoặc đã được xóa.');
        redirect($baseUrl . '/index.php');
    }

    $pdo->beginTransaction();

    // Xóa lịch sử thanh toán liên quan trước để tránh ràng buộc khóa ngoại
    $delPay = $pdo->prepare('DELETE FROM LichSuThanhToan WHERE MaHoaDon = ?');
    $delPay->execute([$id]);

    // Xóa hóa đơn
    $delHd = $pdo->prepare('DELETE FROM HoaDon WHERE MaHoaDon = ?');
    $delHd->execute([$id]);

    $pdo->commit();

    logAudit('DELETE_INVOICE', 'HoaDon', (string)$id, 'Xóa hóa đơn #' . $id . ' phòng ' . $invoice['SoPhong'] . ' kỳ ' . $invoice['KyThanhToan']);
    setFlash('success', 'Đã xóa hóa đơn #' . $id . ' (Phòng ' . $invoice['SoPhong'] . ' - Kỳ ' . $invoice['KyThanhToan'] . ') thành công.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', 'Lỗi khi xóa hóa đơn: ' . $e->getMessage());
}

$redirectUrl = $baseUrl . '/index.php';
if ($ky !== '') {
    $redirectUrl .= '?ky_thanh_toan=' . urlencode($ky);
}
redirect($redirectUrl);
