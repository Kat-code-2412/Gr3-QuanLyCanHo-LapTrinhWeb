<?php

declare(strict_types=1);

$title = 'Thanh toán hóa đơn';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
markOverdueInvoices($pdo);
$returnPath = currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan';
$baseUrl = url($returnPath);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Không tìm thấy hóa đơn cần thanh toán.');
    redirect($returnPath . '/index.php');
}

$stmt = $pdo->prepare('SELECT hd.*, hp.MaHopDong, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach FROM HoaDon hd JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach WHERE hd.MaHoaDon = ?');
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('error', 'Hóa đơn không tồn tại.');
    redirect($returnPath . '/index.php');
}

if (!isStaffAssignedBuilding((string)($invoice['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền thu tiền hóa đơn thuộc tòa nhà này.');
    redirect($baseUrl . '/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maHoaDon = (int)($_POST['maHoaDon'] ?? 0);
    $soTien = (float)str_replace('.', '', trim((string)($_POST['soTien'] ?? '0')));
    if ($soTien > 0 && $soTien < 10000) {
        $soTien *= 1000;
    }
    $hinhThuc = trim((string)($_POST['hinhThuc'] ?? ''));

    if ($maHoaDon <= 0) {
        $error = 'Mã hóa đơn không hợp lệ.';
    } elseif ($soTien <= 0) {
        $error = 'Số tiền thanh toán phải lớn hơn 0.';
    } elseif (!in_array($hinhThuc, ['Tiền mặt', 'Chuyển khoản'], true)) {
        $error = 'Hình thức thanh toán không hợp lệ (Chọn Tiền mặt hoặc Chuyển khoản).';
    } else {
        try {
            $pdo->beginTransaction();
            $stmtPay = $pdo->prepare('INSERT INTO LichSuThanhToan (MaHoaDon, NgayThanhToan, SoTien, HinhThuc) VALUES (?, NOW(), ?, ?)');
            $stmtPay->execute([$maHoaDon, $soTien, $hinhThuc]);

            $stmtUp = $pdo->prepare("UPDATE HoaDon SET TrangThai = 'Đã TT', TrangThaiThanhToan = 'Đã thanh toán', NgayThanhToan = NOW() WHERE MaHoaDon = ?");
            $stmtUp->execute([$maHoaDon]);
            $pdo->commit();

            logAudit('PAYMENT', 'HoaDon', (string)$maHoaDon, 'Thanh toán ' . number_format($soTien, 0, ',', '.') . ' đ qua hình thức ' . $hinhThuc);
            setFlash('success', 'Thanh toán hóa đơn #' . $maHoaDon . ' thành công.');
            redirect($baseUrl . '/detail.php?id=' . $maHoaDon);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Lỗi thanh toán: ' . $e->getMessage();
        }
    }
}
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Thanh toán hóa đơn #<?= e((string)$invoice['MaHoaDon']) ?></h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/detail.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn btn-outline">&larr; Chi tiết</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 580px; margin: 0 auto;">
    <div class="card-header">
        <h3 style="margin: 0;">Thông tin thanh toán</h3>
    </div>
    <div class="card-body">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.1rem 1.25rem; margin-bottom: 1.25rem;">
            <p style="margin: 0 0 0.45rem 0;"><strong>Khách thuê:</strong> <?= e($invoice['TenKhach']) ?></p>
            <p style="margin: 0 0 0.45rem 0;"><strong>Phòng:</strong> <span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 700;"><?= e(formatSoPhong($invoice['SoPhong'])) ?></span></p>
            <p style="margin: 0 0 0.45rem 0;"><strong>Địa chỉ căn hộ:</strong> <?= e($invoice['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?></p>
            <p style="margin: 0 0 0.45rem 0;"><strong>Kỳ thanh toán:</strong> <?= e($invoice['KyThanhToan']) ?></p>
            <p style="margin: 0;"><strong>Tổng tiền hóa đơn:</strong> <span style="font-weight: 700; color: #1d4ed8; font-size: 1.1rem;"><?= formatMoney($invoice['TongTien']) ?></span></p>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="maHoaDon" value="<?= (int)$invoice['MaHoaDon'] ?>">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="soTien" style="font-weight: 600;">Số tiền thanh toán (VNĐ) <span class="text-danger">*</span></label>
                <input type="text" id="soTien" name="soTien" class="form-control currency-mask" value="<?= number_format((float)$invoice['TongTien'], 0, '', '.') ?>" placeholder="Ví dụ: 6.890.000" style="font-size: 1.15rem; font-weight: 700; color: #1d4ed8;" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="hinhThuc" style="font-weight: 600;">Hình thức thanh toán <span class="text-danger">*</span></label>
                <select id="hinhThuc" name="hinhThuc" class="form-control" style="font-size: 0.95rem; font-weight: 500;" required>
                    <option value="Tiền mặt">💵 Tiền mặt</option>
                    <option value="Chuyển khoản">💳 Chuyển khoản</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.4rem; font-weight: 600;">Xác nhận thu tiền</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('soTien');
    if (input) {
        input.addEventListener('input', function() {
            let raw = this.value.replace(/\D/g, '');
            this.value = raw !== '' ? Number(raw).toLocaleString('vi-VN') : '';
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
