<?php

declare(strict_types=1);

$title = 'Chi Tiết Hợp Đồng Thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$role = currentUserRole();
$baseUrl = url(($role === 'Admin') ? '/admin/hop-dong' : '/user/hop-dong');
$khachThueUrl = url(($role === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã hợp đồng không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// Fetch chi tiết Hợp đồng JOIN CanHo, LoaiCanHo, KhachThue, NhanVien
$sql = "SELECT hp.*, 
               ch.SoPhong, ch.DiaChi AS DiaChiCanHo, ch.DienTich, COALESCE(l.TenLoai, 'Căn hộ tiêu chuẩn') AS TenLoai,
               kt.HoTen AS TenKhach, kt.SoDienThoai, kt.Email, kt.CCCD, kt.GioiTinh, kt.NgaySinh, kt.DiaChiThuongTru, kt.NgheNghiep, kt.GhiChu AS GhiChuKhach,
               nv.HoTen AS TenNhanVien
        FROM HopDong hp
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        LEFT JOIN LoaiCanHo l ON ch.MaLoai = l.MaLoai
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        LEFT JOIN NhanVien nv ON hp.MaNV = nv.MaNV
        WHERE hp.MaHopDong = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    setFlash('error', 'Không tìm thấy thông tin hợp đồng.');
    redirect($baseUrl . '/index.php');
}

if (!isStaffAssignedBuilding((string)($contract['DiaChiCanHo'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền truy cập hợp đồng thuộc tòa nhà này.');
    redirect($baseUrl . '/index.php');
}

// Xác định link quay lại danh sách giữ nguyên trang hiện tại của người dùng
$backUrl = $baseUrl . '/index.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($host) && str_contains($ref, $host) && str_contains($ref, 'hop-dong/index.php')) {
        $backUrl = $ref;
    }
}
?>

<style>
    .contract-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.9rem;
    }
    .contract-info-cell {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
        transition: all 0.18s ease;
    }
    .contract-info-cell:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
    .contract-info-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.35rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .contract-info-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.45;
        word-break: break-word;
    }
    .contract-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem 1.35rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    .contract-kpi-card:hover {
        transform: translateY(-2px);
    }
    .contract-kpi-card.kpi-rent {
        background: linear-gradient(145deg, #ffffff 0%, #f0fdf4 100%);
        border-color: #bbf7d0;
    }
    .contract-kpi-card.kpi-rent:hover {
        box-shadow: 0 10px 22px -4px rgba(16, 185, 129, 0.16);
        border-color: #86efac;
    }
    .contract-kpi-card.kpi-deposit {
        background: linear-gradient(145deg, #ffffff 0%, #eff6ff 100%);
        border-color: #bfdbfe;
    }
    .contract-kpi-card.kpi-deposit:hover {
        box-shadow: 0 10px 22px -4px rgba(37, 99, 235, 0.16);
        border-color: #93c5fd;
    }
    .contract-kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .contract-kpi-icon.icon-rent {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);
    }
    .contract-kpi-icon.icon-deposit {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
    }
    .kpi-badge-pill {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .contract-fee-chip {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.7rem 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
        transition: all 0.15s ease;
    }
    .contract-fee-chip:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
</style>

<div class="page-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
            <h1 class="page-title" style="margin: 0; font-size: 1.6rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
                Hợp Đồng Thuê #<?= e((string)$contract['MaHopDong']) ?> – Phòng <?= e($contract['SoPhong']) ?>
            </h1>
            <?= renderStatusBadge($contract['TrangThai']) ?>
        </div>
        <p class="text-muted" style="margin: 0; font-size: 0.875rem;">
            Khách thuê: <strong style="color: #1e293b;"><?= e($contract['TenKhach']) ?></strong> &bull; Loại căn: <strong style="color: #1e293b;"><?= e($contract['TenLoai']) ?></strong>
        </p>
    </div>
    <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
        <a href="<?= e($backUrl) ?>" class="btn btn-outline">
            <?= svgIcon('arrow-left', '', 15) ?>
            <span>Danh sách hợp đồng</span>
        </a>
        <a href="<?= $baseUrl ?>/edit.php?id=<?= $id ?>" class="btn btn-primary">
            <?= svgIcon('edit', '', 15) ?>
            <span>Chỉnh sửa</span>
        </a>
        <?php if ($contract['TrangThai'] === 'Đang hiệu lực'): ?>
            <a href="<?= $baseUrl ?>/thanh-ly.php?id=<?= $id ?>&return_url=<?= urlencode($baseUrl . '/detail.php?id=' . $id) ?>" class="btn btn-danger" onclick="return confirm('Xác nhận thanh lý hợp đồng này?');">
                <?= svgIcon('door', '', 15) ?>
                <span>Thanh lý hợp đồng</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- SECTION 1 & 2: KHÁCH THUÊ & PHÒNG THUÊ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Box Khách thuê -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="background-color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
            <h3 style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
                <?= svgIcon('users', '', 18) ?>
                <span>Thông Tin Khách Thuê</span>
            </h3>
            <a href="<?= $khachThueUrl ?>/detail.php?id=<?= $contract['MaKhach'] ?>" class="btn btn-sm btn-outline" style="font-size: 0.75rem; padding: 4px 9px;">
                <?= svgIcon('eye', '', 12) ?> <span>Hồ sơ khách</span>
            </a>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <div class="contract-info-grid">
                <!-- HỌ VÀ TÊN -->
                <div class="contract-info-cell">
                    <div class="contract-info-label"><?= svgIcon('user', '', 13) ?> Họ và tên</div>
                    <div class="contract-info-value">
                        <a href="<?= $khachThueUrl ?>/detail.php?id=<?= $contract['MaKhach'] ?>" style="font-size: 1.1rem; font-weight: 700; color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <span><?= e($contract['TenKhach']) ?></span>
                        </a>
                    </div>
                </div>

                <!-- SỐ ĐIỆN THOẠI -->
                <div class="contract-info-cell">
                    <div class="contract-info-label"><?= svgIcon('phone', '', 13) ?> Số điện thoại</div>
                    <div class="contract-info-value">
                        <a href="tel:<?= e($contract['SoDienThoai']) ?>" style="font-size: 1.05rem; font-weight: 700; color: #0f172a; text-decoration: none; font-family: ui-monospace, monospace;">
                            <?= e($contract['SoDienThoai']) ?>
                        </a>
                    </div>
                </div>

                <!-- SỐ CCCD -->
                <div class="contract-info-cell">
                    <div class="contract-info-label"><?= svgIcon('shield', '', 13) ?> Số CCCD / CMND</div>
                    <div class="contract-info-value">
                        <code style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-size: 0.95rem; font-weight: 700; color: #0f172a; border: 1px solid #e2e8f0;"><?= e($contract['CCCD'] ?? 'Chưa cập nhật') ?></code>
                    </div>
                </div>

                <!-- EMAIL -->
                <div class="contract-info-cell">
                    <div class="contract-info-label"><?= svgIcon('mail', '', 13) ?> Email</div>
                    <div class="contract-info-value">
                        <?php if (!empty($contract['Email'])): ?>
                            <a href="mailto:<?= e($contract['Email']) ?>" style="color: #2563eb; font-weight: 600; text-decoration: none; font-size: 0.92rem;"><?= e($contract['Email']) ?></a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic;">Chưa cập nhật</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- NGÀY SINH -->
                <div class="contract-info-cell">
                    <div class="contract-info-label"><?= svgIcon('calendar', '', 13) ?> Ngày sinh</div>
                    <div class="contract-info-value" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
                        <?= formatDate($contract['NgaySinh']) ?>
                    </div>
                </div>

                <!-- GIỚI TÍNH -->
                <div class="contract-info-cell">
                    <div class="contract-info-label"><?= svgIcon('user-check', '', 13) ?> Giới tính</div>
                    <div class="contract-info-value" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
                        <?= e($contract['GioiTinh'] ?? 'Chưa cập nhật') ?>
                    </div>
                </div>

                <!-- ĐỊA CHỈ THƯỜNG TRÚ -->
                <div class="contract-info-cell" style="grid-column: 1 / -1;">
                    <div class="contract-info-label"><?= svgIcon('map-pin', '', 13) ?> Địa chỉ thường trú</div>
                    <div class="contract-info-value" style="font-size: 0.92rem; font-weight: 500; color: #334155; line-height: 1.5;">
                        <?= e($contract['DiaChiThuongTru'] ?? 'Chưa cập nhật địa chỉ') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Box Căn hộ -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="background-color: #ffffff; display: flex; align-items: center; justify-content: space-between;">
            <h3 style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
                <?= svgIcon('door', '', 18) ?>
                <span>Thông Tin Căn Hộ / Phòng</span>
            </h3>
            <a href="<?= url('/admin/can-ho/detail.php?id=' . (int)$contract['MaCanHo']) ?>" class="btn btn-sm btn-outline" style="font-size: 0.75rem; padding: 4px 9px;">
                <?= svgIcon('eye', '', 12) ?> <span>Chi tiết căn</span>
            </a>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <div class="contract-info-grid">
                <!-- SỐ PHÒNG -->
                <div class="contract-info-cell">
                    <div class="contract-info-label"><?= svgIcon('door', '', 13) ?> Số phòng</div>
                    <div class="contract-info-value" style="font-size: 1.25rem; font-weight: 800; color: #2563eb;">
                        Phòng <?= e($contract['SoPhong']) ?>
                    </div>
                </div>

                <!-- LOẠI PHÒNG & DIỆN TÍCH -->
                <div class="contract-info-cell">
                    <div class="contract-info-label">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
                        <span>Loại phòng & Diện tích</span>
                    </div>
                    <div class="contract-info-value">
                        <?php
                        $dt = (float)($contract['DienTich'] ?? 0);
                        $formattedDt = ($dt == (int)$dt) ? (string)(int)$dt : number_format($dt, 1, ',', '.');
                        ?>
                        <span style="font-size: 1.05rem; font-weight: 700; color: #0f172a;"><?= e($contract['TenLoai']) ?></span>
                        <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">(<?= $formattedDt ?> m²)</span>
                    </div>
                </div>

                <!-- ĐỊA CHỈ TÒA NHÀ -->
                <div class="contract-info-cell" style="grid-column: 1 / -1;">
                    <div class="contract-info-label"><?= svgIcon('building', '', 13) ?> Địa chỉ tòa nhà / Căn hộ</div>
                    <div class="contract-info-value" style="font-size: 0.92rem; font-weight: 600; color: #1e293b; line-height: 1.5;">
                        <?= e($contract['DiaChiCanHo'] ?? 'Chưa cập nhật') ?>
                    </div>
                </div>

                <!-- MÔ TẢ TIỆN ÍCH -->
                <div class="contract-info-cell" style="grid-column: 1 / -1;">
                    <div class="contract-info-label"><?= svgIcon('sparkles', '', 13) ?> Mô tả / Tiện ích căn hộ</div>
                    <div class="contract-info-value" style="font-size: 0.9rem; font-weight: 500; color: #475569; line-height: 1.5;">
                        <?= nl2br(e($contract['NoiThatCanHo'] ?? 'Tiện nghi cơ bản')) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 3: THỜI HẠN & GIÁ THUÊ THỎA THUẬN -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="background-color: #ffffff;">
        <h3 style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
            <?= svgIcon('contract', '', 18) ?>
            <span>Thời Hạn & Giá Thuê Thỏa Thuận</span>
        </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <!-- 2 KPI THẺ TÀI CHÍNH NỔI BẬT -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.15rem; margin-bottom: 1.25rem;">
            <div class="contract-kpi-card kpi-rent">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #047857;">Giá Thuê Thỏa Thuận</span>
                    <div class="contract-kpi-icon icon-rent">
                        <?= svgIcon('payment', '', 20) ?>
                    </div>
                </div>
                <div style="display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 1.7rem; font-weight: 800; color: #047857; letter-spacing: -0.03em; line-height: 1.15;">
                        <?= formatMoney($contract['GiaThueThoaThuan']) ?>
                    </span>
                    <span class="kpi-badge-pill" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;">
                        <?= svgIcon('clock', '', 12) ?> / tháng
                    </span>
                </div>
                <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.55rem; display: flex; align-items: center; gap: 0.35rem;">
                    <span style="color: #10b981;">●</span> Thanh toán định kỳ mỗi tháng
                </div>
            </div>

            <div class="contract-kpi-card kpi-deposit">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #1d4ed8;">Tiền Đặt Cọc</span>
                    <div class="contract-kpi-icon icon-deposit">
                        <?= svgIcon('shield', '', 20) ?>
                    </div>
                </div>
                <div style="display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 1.7rem; font-weight: 800; color: #1d4ed8; letter-spacing: -0.03em; line-height: 1.15;">
                        <?= formatMoney($contract['TienCoc']) ?>
                    </span>
                </div>
                <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.55rem; display: flex; align-items: center; gap: 0.35rem;">
                    <span style="color: #3b82f6;">●</span> Hoàn lại khi thanh lý hợp đồng
                </div>
            </div>
        </div>

        <!-- CÁC MỐC THỜI GIAN & THÔNG TIN HỢP ĐỒNG -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem;">
            <div class="contract-info-cell">
                <div class="contract-info-label"><?= svgIcon('calendar', '', 13) ?> Thời hạn hợp đồng</div>
                <div class="contract-info-value" style="font-size: 1.05rem; font-weight: 700; color: #0f172a;">
                    <?= (int)($contract['ThoiHanThang'] ?? 6) ?> tháng
                </div>
            </div>

            <div class="contract-info-cell">
                <div class="contract-info-label"><?= svgIcon('edit', '', 13) ?> Ngày ký hợp đồng</div>
                <div class="contract-info-value" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
                    <?= formatDate($contract['NgayKy'] ?? $contract['NgayBatDau']) ?>
                </div>
            </div>

            <div class="contract-info-cell">
                <div class="contract-info-label"><?= svgIcon('calendar', '', 13) ?> Ngày bắt đầu thuê</div>
                <div class="contract-info-value" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
                    <?= formatDate($contract['NgayBatDau']) ?>
                </div>
            </div>

            <div class="contract-info-cell">
                <div class="contract-info-label"><?= svgIcon('calendar', '', 13) ?> Ngày hết hạn</div>
                <div class="contract-info-value" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
                    <?= formatDate($contract['NgayKetThuc']) ?>
                </div>
            </div>

            <div class="contract-info-cell">
                <div class="contract-info-label"><?= svgIcon('user-check', '', 13) ?> Nhân viên phụ trách</div>
                <div class="contract-info-value" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
                    <?= e($contract['TenNhanVien'] ?? 'Hệ thống') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 4: ĐƠN GIÁ ĐIỆN / NƯỚC / DỊCH VỤ -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="background-color: #ffffff;">
        <h3 style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
            <?= svgIcon('sparkles', '', 18) ?>
            <span>Đơn Giá Điện / Nước & Dịch Vụ Dùng Chung</span>
        </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
            <!-- TIỀN ĐIỆN -->
            <div class="contract-fee-chip">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <?= svgIcon('electric', '', 16) ?>
                </div>
                <div style="min-width: 0;">
                    <div style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Đơn giá điện</div>
                    <div style="font-size: 0.88rem; font-weight: 800; color: #0f172a; white-space: nowrap;">
                        <?= formatMoney(normalizeServiceFee($contract['GiaDien'] ?? 3800, 'dien')) ?><span style="font-size: 0.7rem; font-weight: 500; color: #64748b;">/kWh</span>
                    </div>
                </div>
            </div>

            <!-- TIỀN NƯỚC -->
            <div class="contract-fee-chip">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <?= svgIcon('water', '', 16) ?>
                </div>
                <div style="min-width: 0;">
                    <div style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Tiền nước khoán</div>
                    <div style="font-size: 0.88rem; font-weight: 800; color: #0f172a; white-space: nowrap;">
                        <?= formatMoney(normalizeServiceFee($contract['GiaNuoc'] ?? 100000, 'nuoc')) ?><span style="font-size: 0.7rem; font-weight: 500; color: #64748b;">/tháng</span>
                    </div>
                </div>
            </div>

            <!-- XE MÁY -->
            <div class="contract-fee-chip">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <?= svgIcon('motorcycle', '', 16) ?>
                </div>
                <div style="min-width: 0;">
                    <div style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Phí xe máy</div>
                    <div style="font-size: 0.88rem; font-weight: 800; color: #0f172a; white-space: nowrap;">
                        <?= formatMoney(normalizeServiceFee($contract['GiaXeMay'] ?? 120000, 'xemay')) ?><span style="font-size: 0.7rem; font-weight: 500; color: #64748b;">/tháng</span>
                    </div>
                </div>
            </div>

            <!-- Ô TÔ -->
            <div class="contract-fee-chip">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8C2.1 11 2 11.5 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
                </div>
                <div style="min-width: 0;">
                    <div style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Phí ô tô</div>
                    <div style="font-size: 0.88rem; font-weight: 800; color: #0f172a; white-space: nowrap;">
                        <?= formatMoney(normalizeServiceFee($contract['GiaOto'] ?? 1200000, 'oto')) ?><span style="font-size: 0.7rem; font-weight: 500; color: #64748b;">/tháng</span>
                    </div>
                </div>
            </div>

            <!-- WIFI -->
            <div class="contract-fee-chip">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h.01"/><path d="M2 8.82a15 15 0 0 1 20 0"/><path d="M5 12.86a10 10 0 0 1 14 0"/><path d="M8.5 16.43a5 5 0 0 1 7 0"/></svg>
                </div>
                <div style="min-width: 0;">
                    <div style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Phí Internet</div>
                    <div style="font-size: 0.88rem; font-weight: 800; color: #0f172a; white-space: nowrap;">
                        <?= formatMoney(normalizeServiceFee($contract['GiaInternet'] ?? 100000, 'internet')) ?><span style="font-size: 0.7rem; font-weight: 500; color: #64748b;">/tháng</span>
                    </div>
                </div>
            </div>

            <!-- VỆ SINH -->
            <div class="contract-fee-chip">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <?= svgIcon('shield', '', 16) ?>
                </div>
                <div style="min-width: 0;">
                    <div style="font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">Phí Vệ sinh</div>
                    <div style="font-size: 0.88rem; font-weight: 800; color: #0f172a; white-space: nowrap;">
                        <?= formatMoney(normalizeServiceFee($contract['GiaVeSinh'] ?? 50000, 'vesinh')) ?><span style="font-size: 0.7rem; font-weight: 500; color: #64748b;">/tháng</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILE ĐÍNH KÈM -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="background-color: #ffffff;">
        <h3 style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
            <?= svgIcon('download', '', 18) ?>
            <span>Hình Ảnh & File Đính Kèm</span>
        </h3>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
            <!-- File Hợp đồng -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.15rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; box-shadow: 0 1px 2px rgba(15,23,42,0.02);">
                <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?= svgIcon('contract', '', 18) ?>
                    </div>
                    <div style="min-width: 0;">
                        <div style="font-size: 0.88rem; font-weight: 700; color: #0f172a;">File Hợp đồng Scan</div>
                        <div style="font-size: 0.75rem; color: #64748b;">
                            <?= !empty($contract['FileHopDong']) ? 'Đã đính kèm' : 'Chưa upload' ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($contract['FileHopDong'])): ?>
                    <a href="<?= e(url($contract['FileHopDong'])) ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.78rem; padding: 5px 10px; white-space: nowrap;">
                        <?= svgIcon('eye', '', 13) ?> Xem file
                    </a>
                <?php else: ?>
                    <span style="font-size: 0.78rem; color: #94a3b8; font-style: italic;">Chưa có</span>
                <?php endif; ?>
            </div>

            <!-- Ảnh CCCD / CMND (Gộp chung) -->
            <?php 
            $cccdTruoc = $contract['AnhCCCDMatTruoc'] ?? $contract['AnhCCCD'] ?? '';
            $cccdSau = $contract['AnhCCCDMatSau'] ?? '';
            $hasTruoc = !empty($cccdTruoc);
            $hasSau = !empty($cccdSau);
            $cccdCount = ($hasTruoc ? 1 : 0) + ($hasSau ? 1 : 0);
            ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.15rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; box-shadow: 0 1px 2px rgba(15,23,42,0.02);">
                <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?= svgIcon('id-card', '', 18) ?>
                    </div>
                    <div style="min-width: 0;">
                        <div style="font-size: 0.88rem; font-weight: 700; color: #0f172a;">Ảnh CCCD / CMND</div>
                        <div style="font-size: 0.75rem; color: #64748b;">
                            <?php if ($cccdCount === 0): ?>
                                Chưa upload
                            <?php elseif ($cccdCount === 1): ?>
                                Đã đính kèm 1 ảnh
                            <?php else: ?>
                                Đã đính kèm 2 mặt
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.35rem; flex-shrink: 0;">
                    <?php if ($cccdCount === 0): ?>
                        <span style="font-size: 0.78rem; color: #94a3b8; font-style: italic;">Chưa có</span>
                    <?php elseif ($hasTruoc && $hasSau): ?>
                        <a href="<?= e(url($cccdTruoc)) ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.78rem; padding: 5px 9px; white-space: nowrap;">
                            <?= svgIcon('eye', '', 12) ?> Mặt trước
                        </a>
                        <a href="<?= e(url($cccdSau)) ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.78rem; padding: 5px 9px; white-space: nowrap;">
                            <?= svgIcon('eye', '', 12) ?> Mặt sau
                        </a>
                    <?php else: ?>
                        <a href="<?= e(url($hasTruoc ? $cccdTruoc : $cccdSau)) ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.78rem; padding: 5px 10px; white-space: nowrap;">
                            <?= svgIcon('eye', '', 13) ?> Xem ảnh
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
