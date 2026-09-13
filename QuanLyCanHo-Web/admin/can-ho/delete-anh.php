<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php'; require_once __DIR__ . '/../../auth/guard.php'; require_once __DIR__ . '/../../config/database.php'; require_once __DIR__ . '/../../includes/module2_helpers.php'; requirePermission('CANHO_MANAGE');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/can-ho/index.php');
}
module2_require_csrf();

$deleteAll = !empty($_POST['delete_all']);
$maCanHo = (int)($_POST['ma_can_ho'] ?? 0);

if ($deleteAll && $maCanHo > 0) {
    $canCheck = $pdo->prepare('SELECT DiaChi FROM CanHo WHERE MaCanHo = :id');
    $canCheck->execute([':id' => $maCanHo]);
    $diaChi = (string)$canCheck->fetchColumn();
    if (!isStaffAssignedBuilding($diaChi)) {
        setFlash('error', 'Bạn không có quyền quản lý ảnh của tòa nhà này.');
        redirect('/admin/can-ho/index.php');
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM CanHo_Anh WHERE MaCanHo = :id');
        $stmt->execute([':id' => $maCanHo]);
        $allImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->prepare('DELETE FROM CanHo_Anh WHERE MaCanHo = :id')->execute([':id' => $maCanHo]);

        foreach ($allImages as $img) {
            $physical = module2_image_path((string)$img['DuongDan']);
            if (is_file($physical)) {
                @unlink($physical);
            }
        }
        setFlash('success', 'Đã xóa tất cả ảnh của căn hộ thành công.');
    } catch (Throwable $e) {
        error_log($e->getMessage());
        setFlash('error', 'Không thể xóa ảnh.');
    }
    redirect('/admin/can-ho/detail.php?id=' . $maCanHo);
}

$imgId = (int)($_POST['id'] ?? 0);
$s = $pdo->prepare('SELECT a.*, c.DiaChi FROM CanHo_Anh a JOIN CanHo c ON c.MaCanHo = a.MaCanHo WHERE a.MaAnh = :id');
$s->execute([':id' => $imgId]);
$img = $s->fetch();
if (!$img) {
    setFlash('error', 'Không tìm thấy ảnh.');
    redirect('/admin/can-ho/index.php');
}

if (!isStaffAssignedBuilding((string)($img['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền quản lý ảnh của tòa nhà này.');
    redirect('/admin/can-ho/index.php');
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM CanHo_Anh WHERE MaAnh = :id')->execute([':id' => $imgId]);
    module2_repair_cover($pdo, (int)$img['MaCanHo']);
    $pdo->commit();

    $physical = module2_image_path((string)$img['DuongDan']);
    if (is_file($physical)) {
        @unlink($physical);
    }
    setFlash('success', 'Đã xóa ảnh thành công.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    setFlash('error', 'Không thể xóa ảnh.');
}

redirect('/admin/can-ho/detail.php?id=' . (int)$img['MaCanHo']);