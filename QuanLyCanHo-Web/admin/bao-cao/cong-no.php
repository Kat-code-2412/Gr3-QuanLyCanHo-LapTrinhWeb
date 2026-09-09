<?php

declare(strict_types=1);

$title = 'Báo cáo Công nợ - Quản lý Căn dịch vụ';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$totalRows = (int)$pdo->query('SELECT COUNT(*) FROM View_HoaDonChuaThanhToan')->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT * FROM View_HoaDonChuaThanhToan
        ORDER BY TrangThai DESC, SoNgayKeTuNgayTao DESC
        LIMIT $perPage OFFSET $offset";
$rows = $pdo->query($sql)->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Báo cáo Công nợ</h1>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Hóa đơn chưa thanh toán (<?= $totalRows ?> hóa đơn)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <p>Không có hóa đơn nào chưa thanh toán.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Kỳ</th>
                            <th>Phòng</th>
                            <th>Khách thuê</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Số ngày</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $badgeClass = ($row['TrangThai'] === 'Quá hạn') ? 'badge-danger' : 'badge-warning'; ?>
                            <tr>
                                <td><strong>#<?= e((string)$row['MaHoaDon']) ?></strong></td>
                                <td><?= e($row['KyThanhToan']) ?></td>
                                <td>
                                    <span style="font-weight: 600; color: var(--primary-color);">
                                        Phòng <?= e($row['SoPhong']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e($row['TenKhachThue']) ?>
                                    <br><small style="color: var(--text-muted);"><?= e($row['SoDienThoai']) ?></small>
                                </td>
                                <td><strong><?= formatMoney($row['TongTien']) ?></strong></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= e($row['TrangThai']) ?></span></td>
                                <td><?= (int)$row['SoNgayKeTuNgayTao'] ?> ngày</td>
                                <td class="text-center">
                                    <a href="<?= url('/admin/hoa-don/detail.php?id=' . $row['MaHoaDon']) ?>" class="btn btn-sm btn-outline">
                                        👁️ Xem
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= ($i === $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>