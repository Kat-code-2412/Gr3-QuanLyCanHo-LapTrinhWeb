<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue');

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

// 2. Kiểm tra ràng buộc dữ liệu: Hợp đồng đang hiệu lực
$checkHopDong = $pdo->prepare("SELECT COUNT(*) FROM HopDong WHERE MaKhach = ? AND TrangThai = 'Đang hiệu lực'");
$checkHopDong->execute([$id]);
$countHopDongActive = (int)$checkHopDong->fetchColumn();

if ($countHopDongActive > 0) {
    setFlash('error', 'Khách thuê đang có hợp đồng hiệu lực. Vui lòng kết thúc hợp đồng trước khi xóa.');
    redirect($baseUrl . '/index.php');
}

// 3. Nếu không có hợp đồng đang hiệu lực -> Cho phép xóa toàn bộ dữ liệu lịch sử liên quan
try {
    $pdo->beginTransaction();

    // Lấy danh sách hợp đồng cũ của khách này
    $stmtHd = $pdo->prepare("SELECT MaHopDong FROM HopDong WHERE MaKhach = ?");
    $stmtHd->execute([$id]);
    $contractIds = $stmtHd->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($contractIds)) {
        $inHdClause = implode(',', array_fill(0, count($contractIds), '?'));

        // Lấy danh sách hóa đơn liên quan đến các hợp đồng này
        $stmtInvoice = $pdo->prepare("SELECT MaHoaDon FROM HoaDon WHERE MaHopDong IN ($inHdClause)");
        $stmtInvoice->execute($contractIds);
        $invoiceIds = $stmtInvoice->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($invoiceIds)) {
            $inInvClause = implode(',', array_fill(0, count($invoiceIds), '?'));

            // Xóa lịch sử thanh toán của các hóa đơn
            $pdo->prepare("DELETE FROM LichSuThanhToan WHERE MaHoaDon IN ($inInvClause)")->execute($invoiceIds);

            // Xóa chi tiết hóa đơn dịch vụ
            $pdo->prepare("DELETE FROM HoaDon_DichVu WHERE MaHoaDon IN ($inInvClause)")->execute($invoiceIds);

            // Xóa các hóa đơn
            $pdo->prepare("DELETE FROM HoaDon WHERE MaHoaDon IN ($inInvClause)")->execute($invoiceIds);
        }

        // Xóa các hợp đồng cũ
        $pdo->prepare("DELETE FROM HopDong WHERE MaKhach = ?")->execute([$id]);
    }

    // Xóa yêu cầu bảo trì của khách này (nếu có)
    $pdo->prepare("DELETE FROM YeuCauBaoTri WHERE MaKhach = ?")->execute([$id]);

    // Xóa thông tin khách thuê
    $pdo->prepare("DELETE FROM KhachThue WHERE MaKhach = ?")->execute([$id]);

    $pdo->commit();

    setFlash('success', 'Xóa khách thuê "' . e($tenant['HoTen']) . '" thành công.');
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', 'Lỗi khi xóa khách thuê: ' . $ex->getMessage());
}

redirect($baseUrl . '/index.php');

