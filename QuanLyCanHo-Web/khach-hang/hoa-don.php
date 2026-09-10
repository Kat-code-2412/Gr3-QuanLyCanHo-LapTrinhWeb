<?php

declare(strict_types=1);

$title = 'Thanh toán hóa đơn';
require_once __DIR__ . '/../includes/header.php';
requireCustomerLogin();

$pdo = require __DIR__ . '/../config/database.php';
$customerId = currentCustomerId();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT hd.*, ch.SoPhong FROM HoaDon hd JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo WHERE hd.MaHoaDon = ? AND hp.MaKhach = ?');
$stmt->execute([$id, $customerId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('error', 'Không tìm thấy hóa đơn của bạn.');
    redirect('/khach-hang/index.php');
}

$qrBankId = $_ENV['QR_BANK_ID'] ?? '';
$qrBankAccount = $_ENV['QR_BANK_ACCOUNT'] ?? '';
$qrAccountName = $_ENV['QR_ACCOUNT_NAME'] ?? '';
$qrReady = $qrBankId !== '' && $qrBankAccount !== '' && $qrAccountName !== '';
$qrInfo = 'Thanh toan HD ' . $invoice['MaHoaDon'] . ' - ' . ($_SESSION['HoTenKhach'] ?? 'Khach hang');
$qrUrl = $qrReady
    ? 'https://img.vietqr.io/image/' . rawurlencode($qrBankId) . '-' . rawurlencode($qrBankAccount) . '-compact2.png?amount=' . (int)$invoice['TongTien'] . '&addInfo=' . rawurlencode($qrInfo) . '&accountName=' . rawurlencode($qrAccountName)
    : '';
?>

<div class="page-header">
    <div><h1 class="page-title">Thanh toán hóa đơn #<?= e((string)$invoice['MaHoaDon']) ?></h1></div>
    <a class="btn btn-outline" href="<?= url('/khach-hang/index.php') ?>">Quay lại</a>
</div>

<div class="card" style="max-width: 620px;">
    <div class="card-header"><h3>Thông tin hóa đơn</h3></div>
    <div class="card-body">
        <p><strong>Khách hàng:</strong> <?= e((string)($_SESSION['HoTenKhach'] ?? '')) ?></p>
        <p><strong>Phòng:</strong> <?= e((string)$invoice['SoPhong']) ?></p>
        <p><strong>Kỳ:</strong> <?= e((string)$invoice['KyThanhToan']) ?></p>
        <p><strong>Số tiền cần thanh toán:</strong> <span style="font-weight: 700; color: var(--primary-color);"> <?= formatMoney($invoice['TongTien']) ?></span></p>
        <p><strong>Trạng thái:</strong> <?= renderStatusBadge((string)$invoice['TrangThai']) ?></p>

        <?php if ((string)$invoice['TrangThai'] === 'Đã TT'): ?>
            <div class="alert alert-success">Hóa đơn này đã được ghi nhận thanh toán.</div>
        <?php elseif ($qrReady): ?>
            <div style="text-align: center; padding: 1rem; background: #f8fafc; border: 1px solid #dbeafe; border-radius: 8px;">
                <h3>Quét mã QR bằng ứng dụng ngân hàng</h3>
                <img src="<?= e($qrUrl) ?>" alt="Mã QR thanh toán" style="width: min(100%, 300px); height: auto;">
                <p>Nội dung chuyển khoản: <?= e($qrInfo) ?></p>
                <p style="color: #64748b;">Sau khi chuyển khoản, vui lòng chờ nhân viên xác nhận.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Chưa cấu hình tài khoản nhận tiền để tạo mã QR.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
