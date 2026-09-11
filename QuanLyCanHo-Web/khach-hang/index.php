<?php

declare(strict_types=1);

$title = 'Hóa đơn của tôi';
require_once __DIR__ . '/../includes/header.php';
requireCustomerLogin();

$pdo = require __DIR__ . '/../config/database.php';
$customerId = currentCustomerId();
$stmt = $pdo->prepare('SELECT hd.MaHoaDon, hd.KyThanhToan, hd.TongTien, hd.TrangThai, hd.NgayTao, ch.SoPhong FROM HoaDon hd JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo WHERE hp.MaKhach = ? ORDER BY hd.MaHoaDon DESC');
$stmt->execute([$customerId]);
$invoices = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Xin chào <?= e((string)($_SESSION['HoTenKhach'] ?? 'Khách hàng')) ?></h1>
        <p class="page-subtitle">Danh sách hóa đơn của bạn</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Hóa đơn cần thanh toán</h3></div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($invoices)): ?>
            <div class="empty-state"><p>Bạn chưa có hóa đơn nào.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Mã hóa đơn</th><th>Kỳ</th><th>Phòng</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>#<?= e((string)$invoice['MaHoaDon']) ?></td>
                            <td><?= e((string)$invoice['KyThanhToan']) ?></td>
                            <td><?= e((string)$invoice['SoPhong']) ?></td>
                            <td><?= formatMoney($invoice['TongTien']) ?></td>
                            <td><?= renderStatusBadge((string)$invoice['TrangThai']) ?></td>
                            <td>
                                <?php if ((string)$invoice['TrangThai'] === 'Đã TT'): ?>
                                    <span class="btn btn-sm" style="opacity: 0.6; cursor: not-allowed;">✓ Đã thanh toán</span>
                                <?php else: ?>
                                    <a class="btn btn-sm btn-outline" href="<?= url('/khach-hang/hoa-don.php?id=' . (int)$invoice['MaHoaDon']) ?>">Xem / thanh toán</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
