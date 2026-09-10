<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/module2_helpers.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/can-ho/index.php');
}

module2_require_csrf();
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Căn hộ không hợp lệ.');
    redirect('/admin/can-ho/index.php');
}

$stmt = $pdo->prepare('SELECT MaCanHo, SoPhong FROM CanHo WHERE MaCanHo = :id');
$stmt->execute([':id' => $id]);
$apartment = $stmt->fetch();
if (!$apartment) {
    setFlash('error', 'Không tìm thấy căn hộ.');
    redirect('/admin/can-ho/index.php');
}

$checks = [
    'HopDong' => 'SELECT COUNT(*) FROM HopDong WHERE MaCanHo = :id',
    'YeuCauBaoTri' => 'SELECT COUNT(*) FROM YeuCauBaoTri WHERE MaCanHo = :id',
];
foreach ($checks as $label => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        setFlash('error', 'Không thể xóa căn hộ ' . $apartment['SoPhong'] . ' vì còn dữ liệu ' . $label . '.');
        redirect('/admin/can-ho/index.php');
    }
}

$imageStmt = $pdo->prepare('SELECT DuongDan FROM CanHo_Anh WHERE MaCanHo = :id');
$imageStmt->execute([':id' => $id]);
$imagePaths = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM CanHo_Anh WHERE MaCanHo = :id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM CanHo WHERE MaCanHo = :id')->execute([':id' => $id]);
    $pdo->commit();

    foreach ($imagePaths as $path) {
        $physicalPath = module2_image_path((string)$path);
        if (is_file($physicalPath)) {
            @unlink($physicalPath);
        }
    }

    setFlash('success', 'Đã xóa căn hộ ' . $apartment['SoPhong'] . '.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($error->getMessage());
    setFlash('error', 'Không thể xóa căn hộ.');
}

redirect('/admin/can-ho/index.php');
