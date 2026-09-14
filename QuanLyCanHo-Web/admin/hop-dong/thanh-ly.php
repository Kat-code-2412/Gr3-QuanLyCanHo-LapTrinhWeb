<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$role = currentUserRole();
$baseUrl = url(($role === 'Admin') ? '/admin/hop-dong' : '/user/hop-dong');

$id = (int)($_GET['id'] ?? 0);

// Xác định URL chuyển hướng về để giữ nguyên trang và bộ lọc người dùng đang xem
$page = (int)($_GET['page'] ?? 0);
$keyword = trim($_GET['keyword'] ?? '');
$trangThai = trim($_GET['trang_thai'] ?? '');
$returnUrl = trim($_GET['return_url'] ?? '');

$redirectUrl = $baseUrl . '/index.php';
if (!empty($returnUrl) && (str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, $baseUrl))) {
    $redirectUrl = $returnUrl;
} else {
    $queryParams = [];
    if ($page > 1) {
        $queryParams['page'] = $page;
    }
    if ($keyword !== '') {
        $queryParams['keyword'] = $keyword;
    }
    if ($trangThai !== '') {
        $queryParams['trang_thai'] = $trangThai;
    }
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $ref = $_SERVER['HTTP_REFERER'];
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($host) && str_contains($ref, $host) && str_contains($ref, 'hop-dong/index.php')) {
            $redirectUrl = $ref;
        }
    }
}

if ($id <= 0) {
    setFlash('error', 'Mã hợp đồng không hợp lệ.');
    redirect($redirectUrl);
}

// 1. Kiểm tra tồn tại hợp đồng
$stmt = $pdo->prepare('SELECT hp.*, ch.SoPhong, ch.DiaChi FROM HopDong hp JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo WHERE hp.MaHopDong = ?');
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    setFlash('error', 'Không tìm thấy thông tin hợp đồng cần thanh lý.');
    redirect($redirectUrl);
}

if (!isStaffAssignedBuilding((string)($contract['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền thanh lý hợp đồng thuộc tòa nhà này.');
    redirect($redirectUrl);
}

// 2. Thực hiện Thanh lý hợp đồng trong DB Transaction
try {
    $pdo->exec("UPDATE HoaDon
        SET TrangThai = 'Quá hạn'
        WHERE TrangThai = 'Chưa TT'
          AND NgayTao < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $call = $pdo->prepare('CALL SP_ThanhLyHopDong(:maHopDong)');
    $call->execute([':maHopDong' => $id]);

    logAudit('LIQUIDATE', 'HopDong', (string)$id, "Thanh lý hợp đồng #{$id} phòng {$contract['SoPhong']} - Chuyển phòng về trạng thái Trống");

    setFlash('success', 'Thanh lý Hợp đồng #' . $id . ' thành công! Phòng ' . $contract['SoPhong'] . ' đã chuyển về trạng thái Trống.');
} catch (Throwable $e) {
    setFlash('error', 'Lỗi khi thanh lý hợp đồng: ' . $e->getMessage());
}

redirect($redirectUrl);
