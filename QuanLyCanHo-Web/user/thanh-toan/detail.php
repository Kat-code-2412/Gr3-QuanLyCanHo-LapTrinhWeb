<?php

declare(strict_types=1);

$title = 'Chi tiết hóa đơn';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$isAdmin = currentUserRole() === 'Admin';
$redirectBase = $isAdmin ? '/admin/hoa-don' : '/user/thanh-toan';
$baseUrl = url($redirectBase);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Không tìm thấy hóa đơn cần xem.');
    redirect($redirectBase . '/index.php');
}

$sql = "SELECT hd.*, hp.MaHopDong, hp.MaCanHo, ch.MaCanHoHienThi AS SoPhong, CONCAT('Tầng ', ch.Tang) AS DiaChi,
              kt.HoTen AS TenKhach, kt.SoDienThoai, kt.CCCD
        FROM HoaDon hd
        JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        WHERE hd.MaHoaDon = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('error', 'Hóa đơn không tồn tại hoặc đã bị xóa.');
    redirect($redirectBase . '/index.php');
}

$paymentStmt = $pdo->prepare('SELECT * FROM LichSuThanhToan WHERE MaHoaDon = ? ORDER BY NgayThanhToan DESC');
$paymentStmt->execute([$id]);
$paymentHistory = $paymentStmt->fetchAll();
$totalPaid = array_sum(array_map(static fn(array $payment): float => (float)$payment['SoTien'], $paymentHistory));
$isPaid = $totalPaid >= (float)$invoice['TongTien'];
$kyThanhToan = (string)$invoice['KyThanhToan'];
$kyDate = DateTime::createFromFormat('Y-m-d', $kyThanhToan . '-01')
    ?: DateTime::createFromFormat('m/Y', $kyThanhToan);
$isOverdue = !$isPaid
    && $kyDate instanceof DateTime
    && $kyDate < new DateTime('first day of this month');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Chi tiết hóa đơn #<?= e((string)$invoice['MaHoaDon']) ?></h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Quay lại</a>
        <?php if (!$isPaid): ?>
            <a href="<?= $baseUrl ?>/thanh-toan.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn btn-primary">Thanh toán</a>
        <?php else: ?>
            <span class="badge badge-success">✓ Đã thanh toán</span>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Thông tin hóa đơn</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div class="detail-item">
                <div class="detail-label">Khách thuê</div>
                <div class="detail-value"><?= e($invoice['TenKhach']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Phòng</div>
                <div class="detail-value"><?= e($invoice['SoPhong']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Kỳ thanh toán</div>
                <div class="detail-value"><?= e($invoice['KyThanhToan']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Trạng thái</div>
                <div class="detail-value">
                    <?php if ($isPaid): ?>
                        <span class="badge badge-success">✓ Đã thanh toán</span>
                    <?php elseif ($isOverdue): ?>
                        <span class="badge badge-danger">⚠ Quá hạn</span>
                    <?php else: ?>
                        <?= renderStatusBadge('Chưa TT') ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày tạo</div>
                <div class="detail-value"><?= formatDate((string)$invoice['NgayTao']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày thanh toán</div>
                <div class="detail-value"><?= formatDate((string)$invoice['NgayThanhToan']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Chi tiết khoản tiền</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width: 220px;">Tiền thuê</th>
                        <td><?= formatMoney($invoice['TienThue']) ?></td>
                    </tr>
                    <tr>
                        <th>Tiền điện</th>
                        <td><?= formatMoney($invoice['TienDien']) ?></td>
                    </tr>
                    <tr>
                        <th>Tiền nước</th>
                        <td><?= formatMoney($invoice['TienNuoc']) ?></td>
                    </tr>
                    <tr>
                        <th>Tiền dịch vụ</th>
                        <td><?= formatMoney($invoice['TienDichVu']) ?></td>
                    </tr>
                    <tr>
                        <th>Tổng tiền</th>
                        <td style="font-weight: 700; color: var(--primary-color);"><?= formatMoney($invoice['TongTien']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Lịch sử thanh toán</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($paymentHistory)): ?>
            <div class="empty-state">
                <p>Chưa có giao dịch thanh toán nào.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Số tiền</th>
                            <th>Hình thức</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $item): ?>
                            <tr>
                                <td><?= formatDate((string)$item['NgayThanhToan']) ?></td>
                                <td><?= formatMoney($item['SoTien']) ?></td>
                                <td><?= e($item['HinhThuc']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
