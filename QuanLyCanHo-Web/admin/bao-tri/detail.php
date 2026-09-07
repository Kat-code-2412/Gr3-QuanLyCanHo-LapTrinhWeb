<?php

declare(strict_types=1);

$title = 'Chi tiết Yêu cầu Bảo trì';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = (currentUserRole() === 'Admin') ? '/admin/bao-tri' : '/user/bao-tri';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã bảo trì không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// Fetch chi tiết yêu cầu bảo trì JOIN CanHo và KhachThue
$sql = 'SELECT bt.*, ch.SoPhong, ch.TrangThai AS TrangThaiCanHo,
               kt.HoTen AS TenKhach, kt.SoDienThoai, kt.CCCD, kt.Email
        FROM YeuCauBaoTri bt
        JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
        WHERE bt.MaBaoTri = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('error', 'Không tìm thấy thông tin yêu cầu bảo trì.');
    redirect($baseUrl . '/index.php');
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Yêu Cầu Bảo Trì #<?= e((string)$item['MaBaoTri']) ?></h1>
        <p class="page-subtitle">Ngày tiếp nhận: <?= formatDateTime($item['NgayTiepNhan']) ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Danh sách</a>
        <a href="<?= $baseUrl ?>/edit.php?id=<?= $id ?>" class="btn btn-secondary">✏️ Cập nhật</a>
        <a href="<?= $baseUrl ?>/delete.php?id=<?= $id ?>" 
           class="btn btn-danger" 
           onclick="return confirm('Bạn có chắc chắn muốn xóa yêu cầu bảo trì này?');">
            🗑️ Xóa yêu cầu
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>🛠️ Thông Tin Sự Cố & Tiến Độ Bảo Trì</h3>
        <div>
            <?= renderStatusBadge($item['TrangThai']) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Căn hộ / Số phòng</div>
                <div class="detail-value" style="color: var(--primary-color); font-weight: 700; font-size: 1.1rem;">
                    Phòng <?= e($item['SoPhong']) ?>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Khách thuê phản ánh</div>
                <div class="detail-value">
                    <a href="<?= (currentUserRole() === 'Admin' ? '/admin' : '/user') ?>/khach-thue/detail.php?id=<?= $item['MaKhach'] ?>">
                        <?= e($item['TenKhach']) ?>
                    </a>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Số điện thoại liên hệ</div>
                <div class="detail-value"><?= e($item['SoDienThoai']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Số CCCD khách thuê</div>
                <div class="detail-value"><code><?= e($item['CCCD']) ?></code></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày tiếp nhận</div>
                <div class="detail-value"><?= formatDateTime($item['NgayTiepNhan']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày hoàn thành</div>
                <div class="detail-value"><?= formatDateTime($item['NgayHoanThanh']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Chi phí bảo trì</div>
                <div class="detail-value" style="font-size: 1.1rem; color: var(--success-color);">
                    <strong><?= formatMoney($item['ChiPhi']) ?></strong>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Trạng thái hiện tại</div>
                <div class="detail-value"><?= renderStatusBadge($item['TrangThai']) ?></div>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <div class="detail-label">Nội dung chi tiết sự cố</div>
                <div class="detail-value" style="font-size: 1.05rem; background: #fff; padding: 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <?= e($item['NoiDung']) ?>
                </div>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <div class="detail-label">Ghi chú xử lý</div>
                <div class="detail-value"><?= e($item['GhiChu'] ?? 'Chưa có ghi chú') ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
