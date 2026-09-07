<?php

declare(strict_types=1);

$title = 'Chi tiết Khách thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = (currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã khách thuê không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// 1. Thông tin cá nhân khách thuê
$stmt = $pdo->prepare('SELECT * FROM KhachThue WHERE MaKhach = ?');
$stmt->execute([$id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    setFlash('error', 'Không tìm thấy thông tin khách thuê.');
    redirect($baseUrl . '/index.php');
}

// 2. Các hợp đồng của khách thuê (JOIN HopDong & CanHo)
$contractsSql = 'SELECT hp.*, ch.SoPhong, ch.TrangThai AS TrangThaiCanHo
                 FROM HopDong hp
                 JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
                 WHERE hp.MaKhach = ?
                 ORDER BY hp.MaHopDong DESC';
$contractsStmt = $pdo->prepare($contractsSql);
$contractsStmt->execute([$id]);
$contracts = $contractsStmt->fetchAll();

// 3. Lịch sử yêu cầu bảo trì của khách thuê (JOIN YeuCauBaoTri & CanHo)
$maintenanceSql = 'SELECT bt.*, ch.SoPhong
                   FROM YeuCauBaoTri bt
                   JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
                   WHERE bt.MaKhach = ?
                   ORDER BY bt.MaBaoTri DESC';
$maintStmt = $pdo->prepare($maintenanceSql);
$maintStmt->execute([$id]);
$maintenances = $maintStmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Hồ Sơ Khách Thuê: <?= e($tenant['HoTen']) ?></h1>
        <p class="page-subtitle">Mã khách: #<?= e((string)$tenant['MaKhach']) ?> | CCCD: <?= e($tenant['CCCD']) ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Danh sách</a>
        <a href="<?= $baseUrl ?>/edit.php?id=<?= $id ?>" class="btn btn-secondary">✏️ Chỉnh sửa</a>
        <a href="<?= $baseUrl ?>/delete.php?id=<?= $id ?>" 
           class="btn btn-danger" 
           onclick="return confirm('Bạn có chắc chắn muốn xóa khách thuê này?');">
            🗑️ Xóa khách
        </a>
    </div>
</div>

<!-- Grid Thông tin cá nhân -->
<div class="card mb-3">
    <div class="card-header">
        <h3>📋 Thông Tin Cá Nhân</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Họ và Tên</div>
                <div class="detail-value"><?= e($tenant['HoTen']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Số CCCD / CMND</div>
                <div class="detail-value"><code><?= e($tenant['CCCD']) ?></code></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày sinh</div>
                <div class="detail-value"><?= formatDate($tenant['NgaySinh']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Giới tính</div>
                <div class="detail-value"><?= e($tenant['GioiTinh']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Số điện thoại</div>
                <div class="detail-value"><strong><?= e($tenant['SoDienThoai']) ?></strong></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Email</div>
                <div class="detail-value"><?= e($tenant['Email'] ?? 'Chưa đăng ký') ?></div>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <div class="detail-label">Địa chỉ thường trú</div>
                <div class="detail-value"><?= e($tenant['DiaChiThuongTru'] ?? 'Chưa cập nhật') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Danh sách Hợp đồng -->
<div class="card mb-3">
    <div class="card-header">
        <h3>📄 Các Hợp Đồng Thuê Căn Hộ (<?= count($contracts) ?> hợp đồng)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($contracts)): ?>
            <div class="empty-state">
                <p>Khách thuê này chưa đăng ký hợp đồng nào.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Căn hộ (Phòng)</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Giá thuê thỏa thuận</th>
                            <th>Tiền cọc</th>
                            <th>Trạng thái HĐ</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $contract): ?>
                            <tr>
                                <td><strong>#<?= e((string)$contract['MaHopDong']) ?></strong></td>
                                <td>
                                    <span style="font-weight: 600; color: var(--primary-color);">
                                        Phòng <?= e($contract['SoPhong']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($contract['NgayBatDau']) ?></td>
                                <td><?= formatDate($contract['NgayKetThuc']) ?></td>
                                <td><strong><?= formatMoney($contract['GiaThueThoaThuan']) ?></strong></td>
                                <td><?= formatMoney($contract['TienCoc']) ?></td>
                                <td><?= renderStatusBadge($contract['TrangThai']) ?></td>
                                <td><?= e($contract['GhiChu'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lịch sử Yêu cầu Bảo trì -->
<div class="card mb-3">
    <div class="card-header">
        <h3>🛠️ Lịch Sử Yêu Cầu Bảo Trì (<?= count($maintenances) ?> yêu cầu)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($maintenances)): ?>
            <div class="empty-state">
                <p>Khách thuê này chưa gửi yêu cầu bảo trì nào.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã BT</th>
                            <th>Phòng</th>
                            <th>Nội dung yêu cầu</th>
                            <th>Ngày tiếp nhận</th>
                            <th>Ngày hoàn thành</th>
                            <th>Chi phí</th>
                            <th>Trạng thái</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenances as $maint): ?>
                            <tr>
                                <td><strong>#<?= e((string)$maint['MaBaoTri']) ?></strong></td>
                                <td>Phòng <?= e($maint['SoPhong']) ?></td>
                                <td><?= e($maint['NoiDung']) ?></td>
                                <td><?= formatDateTime($maint['NgayTiepNhan']) ?></td>
                                <td><?= formatDateTime($maint['NgayHoanThanh']) ?></td>
                                <td><?= formatMoney($maint['ChiPhi']) ?></td>
                                <td><?= renderStatusBadge($maint['TrangThai']) ?></td>
                                <td><?= e($maint['GhiChu'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
