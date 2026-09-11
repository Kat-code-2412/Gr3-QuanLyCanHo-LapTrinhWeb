<?php

declare(strict_types=1);

$title = 'Tạo hóa đơn hàng tháng';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kyThanhToan = trim((string)($_POST['ky_thanh_toan'] ?? ''));

    if (preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', $kyThanhToan, $matches)) {
        $kyThanhToan = $matches[2] . '-' . $matches[1];
    }

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $kyThanhToan)) {
        $error = 'Kỳ thanh toán không hợp lệ. Ví dụ đúng: 08/2026';
    } else {
        try {
            $stmt = $pdo->prepare('CALL SP_TaoHoaDonHangThang(:ky)');
            $stmt->execute([':ky' => $kyThanhToan]);

            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM HoaDon WHERE KyThanhToan = ?');
            $checkStmt->execute([$kyThanhToan]);
            if ((int)$checkStmt->fetchColumn() === 0) {
                $error = 'Không tạo được hóa đơn: không có hợp đồng phù hợp trong kỳ này.';
            } else {
                $success = 'Đã tạo hóa đơn cho kỳ ' . $kyThanhToan . ' thành công.';
                setFlash('success', $success);
                redirect(currentUserRole() === 'Admin' ? '/admin/hoa-don/index.php' : '/user/thanh-toan/index.php');
            }
        } catch (Throwable $e) {
            $error = 'Lỗi khi tạo hóa đơn: ' . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tạo hóa đơn hàng tháng</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Quay lại</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span class="alert-icon">✕</span>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Tạo hóa đơn theo kỳ</h3>
    </div>
    <div class="card-body" style="max-width: 500px;">
        <form method="POST">
            <div class="form-group">
                <label for="ky_thanh_toan" style="font-weight: 600;">Kỳ thanh toán</label>
                <input type="text" id="ky_thanh_toan" name="ky_thanh_toan" class="form-control" value="<?= e(date('Y-m')) ?>" placeholder="Ví dụ: 2026-08 hoặc 08/2026" required>
            </div>

            <button type="submit" class="btn btn-primary">Tạo hóa đơn</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
