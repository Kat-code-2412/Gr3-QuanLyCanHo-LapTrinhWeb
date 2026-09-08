<?php

declare(strict_types=1);

$title = 'Chi Tiết Hồ Sơ Khách Thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue');

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

// 2. Lấy danh sách hợp đồng của khách (mới nhất xếp trước)
$contractsSql = 'SELECT hp.*, ch.SoPhong, ch.DiaChi, ch.MoTa AS NoiThatCanHo, ch.DienTich, lch.TenLoai
                 FROM HopDong hp
                 JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
                 JOIN LoaiCanHo lch ON ch.MaLoai = lch.MaLoai
                 WHERE hp.MaKhach = ?
                 ORDER BY hp.MaHopDong DESC';
$contractsStmt = $pdo->prepare($contractsSql);
$contractsStmt->execute([$id]);
$allContracts = $contractsStmt->fetchAll();

// Tách hợp đồng đang hiệu lực
$activeContract = null;
foreach ($allContracts as $c) {
    if ($c['TrangThai'] === 'Đang hiệu lực') {
        $activeContract = $c;
        break;
    }
}

// Tính Trạng thái Khách thuê
if ($activeContract !== null) {
    $tenantStatus = 'Đang thuê';
} elseif (!empty($allContracts)) {
    $tenantStatus = 'Đã trả phòng';
} else {
    $tenantStatus = 'Chưa thuê';
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Hồ Sơ Khách Thuê: <?= e($tenant['HoTen']) ?></h1>
        <p class="page-subtitle">Mã khách: #<?= e((string)$tenant['MaKhach']) ?> | Trạng thái: <?= renderStatusBadge($tenantStatus) ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Danh sách</a>
        <a href="<?= $baseUrl ?>/edit.php?id=<?= $id ?>" class="btn btn-secondary">✏️ Sửa</a>
        <a href="<?= $baseUrl ?>/delete.php?id=<?= $id ?>" 
           class="btn btn-danger" 
           onclick="return confirm('Bạn có chắc chắn muốn xóa khách thuê này không?');">
            🗑️ Xóa
        </a>
    </div>
</div>

<!-- 1. THÔNG TIN KHÁCH THUÊ -->
<div class="card mb-3">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>📋 THÔNG TIN KHÁCH THUÊ</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Mã khách thuê</div>
                <div class="detail-value"><strong>#<?= e((string)$tenant['MaKhach']) ?></strong></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Họ và tên</div>
                <div class="detail-value"><?= e($tenant['HoTen']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Số điện thoại</div>
                <div class="detail-value"><strong><?= e($tenant['SoDienThoai']) ?></strong></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Email</div>
                <div class="detail-value"><?= e($tenant['Email'] ?? 'Chưa đăng ký') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">CCCD / CMND</div>
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
                <div class="detail-label">Nghề nghiệp</div>
                <div class="detail-value"><?= e($tenant['NgheNghiep'] ?? 'Chưa cập nhật') ?></div>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <div class="detail-label">Địa chỉ đăng ký thường trú</div>
                <div class="detail-value"><?= e($tenant['DiaChiThuongTru'] ?? 'Chưa cập nhật') ?></div>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <div class="detail-label">Ghi chú</div>
                <div class="detail-value"><?= nl2br(e($tenant['GhiChu'] ?? 'Không có ghi chú.')) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Trạng thái</div>
                <div class="detail-value"><?= renderStatusBadge($tenantStatus) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 2. HỢP ĐỒNG HIỆN TẠI -->
<div class="card mb-3">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>🏠 HỢP ĐỒNG HIỆN TẠI</h3>
    </div>
    <div class="card-body">
        <?php if ($activeContract !== null): ?>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Mã Hợp Đồng</div>
                    <div class="detail-value"><strong>#<?= e((string)$activeContract['MaHopDong']) ?></strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Phòng đang thuê</div>
                    <div class="detail-value" style="font-size: 1.2rem; font-weight: 700; color: var(--primary-color);">
                        Phòng <?= e($activeContract['SoPhong']) ?> (<?= e($activeContract['TenLoai']) ?>)
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Địa chỉ căn hộ</div>
                    <div class="detail-value"><?= e($activeContract['DiaChi'] ?? 'Tòa nhà A') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Giá thuê thỏa thuận</div>
                    <div class="detail-value"><strong><?= formatMoney($activeContract['GiaThueThoaThuan']) ?></strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tiền cọc</div>
                    <div class="detail-value"><span style="color: var(--success-color); font-weight: 600;"><?= formatMoney($activeContract['TienCoc']) ?></span></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Thời hạn thuê</div>
                    <div class="detail-value"><?= formatDate($activeContract['NgayBatDau']) ?> → <?= formatDate($activeContract['NgayKetThuc']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Trạng thái hợp đồng</div>
                    <div class="detail-value"><?= renderStatusBadge($activeContract['TrangThai']) ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 1.5rem; text-align: center;">
                <p style="font-size: 1.05rem; color: #64748b; margin: 0;">Khách thuê chưa có hợp đồng.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 3. LỊCH SỬ HỢP ĐỒNG -->
<div class="card mb-3">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>📄 LỊCH SỬ HỢP ĐỒNG (<?= count($allContracts) ?> hợp đồng)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($allContracts)): ?>
            <div class="empty-state">
                <p>Khách thuê chưa có hợp đồng nào trong lịch sử.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Phòng</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Giá thuê</th>
                            <th>Tiền cọc</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allContracts as $c): ?>
                            <tr>
                                <td><strong>#<?= e((string)$c['MaHopDong']) ?></strong></td>
                                <td>
                                    <span style="font-weight: 700; color: var(--primary-color);">
                                        Phòng <?= e($c['SoPhong']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($c['NgayBatDau']) ?></td>
                                <td><?= formatDate($c['NgayKetThuc']) ?></td>
                                <td><strong><?= formatMoney($c['GiaThueThoaThuan']) ?></strong></td>
                                <td><?= formatMoney($c['TienCoc']) ?></td>
                                <td><?= renderStatusBadge($c['TrangThai']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
