<?php

declare(strict_types=1);

$title = 'Chi tiết Yêu cầu Bảo trì & Sự cố';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$isAdmin = (currentUserRole() === 'Admin');
$baseUrl = url($isAdmin ? '/admin/bao-tri' : '/user/bao-tri');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã bảo trì không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// Fetch chi tiết yêu cầu bảo trì JOIN CanHo và KhachThue
$sql = 'SELECT bt.*, ch.SoPhong, ch.DiaChi, ch.TrangThai AS TrangThaiCanHo,
               kt.HoTen AS TenKhach, kt.SoDienThoai, kt.CCCD, kt.Email
        FROM YeuCauBaoTri bt
        JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
        LEFT JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
        WHERE bt.MaBaoTri = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('error', 'Không tìm thấy thông tin yêu cầu bảo trì.');
    redirect($baseUrl . '/index.php');
}

if (!isStaffAssignedBuilding((string)($item['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền truy cập yêu cầu bảo trì thuộc tòa nhà này.');
    redirect($baseUrl . '/index.php');
}

$statusColorMap = [
    'Hoàn thành'   => ['bg' => '#ecfdf5', 'text' => '#059669', 'border' => '#a7f3d0', 'dot' => '#10b981'],
    'Đang xử lý'   => ['bg' => '#fffbeb', 'text' => '#b45309', 'border' => '#fde68a', 'dot' => '#f59e0b'],
    'Đã tiếp nhận' => ['bg' => '#eff6ff', 'text' => '#1d4ed8', 'border' => '#bfdbfe', 'dot' => '#3b82f6'],
];
$curStatus = $item['TrangThai'] ?? 'Đã tiếp nhận';
$st = $statusColorMap[$curStatus] ?? ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#cbd5e1', 'dot' => '#94a3b8'];
?>

<style>
.bt-detail-wrapper {
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #1e293b;
    max-width: 100%;
    box-sizing: border-box;
}

/* HEADER BAR */
.bt-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.bt-title-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.bt-heading {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.025em;
}
.bt-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.82rem;
    font-weight: 700;
}
.bt-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

/* ACTIONS */
.bt-actions-bar {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.bt-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.18s ease;
    cursor: pointer;
    border: 1px solid transparent;
}
.bt-btn-outline {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #334155;
}
.bt-btn-outline:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}
.bt-btn-primary {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
}
.bt-btn-primary:hover {
    background: #1d4ed8;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.bt-btn-danger {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}
.bt-btn-danger:hover {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #b91c1c;
}

/* MAIN CARD */
.bt-main-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.bt-card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 1.15rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bt-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.bt-card-body {
    padding: 1.5rem;
}

/* 2-COLUMN BALANCED INFO GRID (NEVER OVERFLOWS) */
.bt-info-grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.15rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 768px) {
    .bt-info-grid-2col {
        grid-template-columns: 1fr;
    }
}

.bt-cell {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
    transition: all 0.18s ease;
}
.bt-cell:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.04);
}
.bt-cell-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.bt-cell-value {
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.45;
    word-break: break-word;
}

/* CONTENT SECTIONS */
.bt-section-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1.15rem 1.35rem;
    margin-top: 1.15rem;
}
.bt-section-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.bt-section-body {
    font-size: 0.98rem;
    color: #1e293b;
    line-height: 1.6;
}
</style>

<div class="bt-detail-wrapper">
    <!-- HEADER BAR -->
    <div class="bt-header-bar">
        <div class="bt-title-group">
            <h1 class="bt-heading">
                Sự Cố Bảo Trì #<?= e((string)$item['MaBaoTri']) ?>
            </h1>
            <span class="bt-status-pill" style="background: <?= $st['bg'] ?>; color: <?= $st['text'] ?>; border: 1px solid <?= $st['border'] ?>;">
                <span class="bt-status-dot" style="background: <?= $st['dot'] ?>;"></span>
                <?= e($curStatus) ?>
            </span>
        </div>
        <div class="bt-actions-bar">
            <a href="<?= $baseUrl ?>/index.php" class="bt-btn bt-btn-outline">
                <?= svgIcon('arrow-left', '', 15) ?>
                <span>Danh sách</span>
            </a>
            <a href="<?= $baseUrl ?>/edit.php?id=<?= $id ?>" class="bt-btn bt-btn-primary">
                <?= svgIcon('edit', '', 15) ?>
                <span>Cập nhật tiến độ</span>
            </a>
            <?php if ($isAdmin): ?>
                <a href="<?= $baseUrl ?>/delete.php?id=<?= $id ?>" 
                   class="bt-btn bt-btn-danger" 
                   onclick="return confirm('Bạn có chắc chắn muốn xóa yêu cầu bảo trì này?');">
                    <?= svgIcon('trash', '', 15) ?>
                    <span>Xóa yêu cầu</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN CARD -->
    <div class="bt-main-card">
        <div class="bt-card-header">
            <h3 class="bt-card-title">
                <?= svgIcon('tool', '', 18) ?>
                <span>Thông Tin Sự Cố & Tiến Độ Bảo Trì</span>
            </h3>
        </div>
        <div class="bt-card-body">
            <!-- 8 Ô THÔNG TIN BỐ TRÍ 2 CỘT CÂN ĐỐI - KHÔNG BAO GIỜ BỊ TRÀN VIỀN -->
            <div class="bt-info-grid-2col">
                <!-- 1. Địa chỉ nhà (Ở TRÊN) -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('map-pin', '', 15) ?>
                        <span>Địa chỉ nhà</span>
                    </div>
                    <div class="bt-cell-value" style="font-size: 0.95rem; font-weight: 500; color: #334155; line-height: 1.5;">
                        <?= e($item['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?>
                    </div>
                </div>

                <!-- 2. Người chịu chi phí -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('shield', '', 15) ?>
                        <span>Người chịu chi phí</span>
                    </div>
                    <div class="bt-cell-value" style="font-weight: 700; color: #1e293b;">
                        <?= e($item['NguoiChiuChiPhi'] ?? 'Chủ nhà') ?>
                    </div>
                </div>

                <!-- 3. Mã phòng (Ở DƯỚI) -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('door', '', 15) ?>
                        <span>Mã phòng</span>
                    </div>
                    <div class="bt-cell-value">
                        <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-size: 1.05rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; border: 1px solid #bfdbfe; display: inline-block;">
                            Phòng <?= e(formatSoPhong($item['SoPhong'])) ?>
                        </span>
                    </div>
                </div>

                <!-- 4. Chi phí bảo trì -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('credit-card', '', 15) ?>
                        <span>Chi phí bảo trì</span>
                    </div>
                    <div class="bt-cell-value" style="font-size: 1.3rem; font-weight: 800; color: #059669;">
                        <?= formatMoney($item['ChiPhi']) ?>
                    </div>
                </div>

                <!-- 5. Khách Thuê -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('users', '', 15) ?>
                        <span>Khách Thuê</span>
                    </div>
                    <div class="bt-cell-value">
                        <?php if (!empty($item['MaKhach']) && !empty($item['TenKhach'])): ?>
                            <a href="<?= ($isAdmin ? '/admin' : '/user') ?>/khach-thue/detail.php?id=<?= $item['MaKhach'] ?>" style="color: #2563eb; text-decoration: none; font-weight: 700;">
                                <?= e($item['TenKhach']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic; font-weight: 500;">(Quản lý báo / Trống)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 6. Ngày tiếp nhận -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('clock', '', 15) ?>
                        <span>Ngày tiếp nhận</span>
                    </div>
                    <div class="bt-cell-value" style="color: #334155; font-weight: 600;">
                        <?= formatDateTime($item['NgayTiepNhan']) ?>
                    </div>
                </div>

                <!-- 7. Số điện thoại liên hệ -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('phone', '', 15) ?>
                        <span>Số điện thoại liên hệ</span>
                    </div>
                    <div class="bt-cell-value">
                        <?php if (!empty($item['SoDienThoai'])): ?>
                            <a href="tel:<?= e($item['SoDienThoai']) ?>" style="color: #0f172a; text-decoration: none; font-weight: 700; letter-spacing: 0.02em;">
                                <?= e($item['SoDienThoai']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic; font-weight: 500;">-</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 8. Ngày hoàn thành -->
                <div class="bt-cell">
                    <div class="bt-cell-label">
                        <?= svgIcon('calendar', '', 15) ?>
                        <span>Ngày hoàn thành</span>
                    </div>
                    <div class="bt-cell-value" style="color: #334155; font-weight: 600;">
                        <?= $item['NgayHoanThanh'] ? formatDateTime($item['NgayHoanThanh']) : '<span style="color: #94a3b8; font-style: italic; font-weight: 500;">Chưa hoàn thành</span>' ?>
                    </div>
                </div>
            </div>

            <!-- MÔ TẢ CHI TIẾT SỰ CỐ -->
            <div class="bt-section-card" style="border-left: 4px solid #2563eb;">
                <div class="bt-section-label">
                    <?= svgIcon('alert-triangle', '', 15) ?>
                    <span>Mô Tả Chi Tiết Sự Cố</span>
                </div>
                <div class="bt-section-body" style="font-weight: 500;">
                    <?= nl2br(e($item['NoiDung'])) ?>
                </div>
            </div>

            <!-- GHI CHÚ XỬ LÝ -->
            <div class="bt-section-card" style="margin-top: 1rem;">
                <div class="bt-section-label">
                    <?= svgIcon('file-text', '', 15) ?>
                    <span>Ghi Chú Xử Lý / Linh Kiện Thay Thế</span>
                </div>
                <div class="bt-section-body">
                    <?php if (!empty($item['GhiChu'])): ?>
                        <?= nl2br(e($item['GhiChu'])) ?>
                    <?php else: ?>
                        <span style="color: #94a3b8; font-style: italic;">Chưa có ghi chú xử lý nào.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
