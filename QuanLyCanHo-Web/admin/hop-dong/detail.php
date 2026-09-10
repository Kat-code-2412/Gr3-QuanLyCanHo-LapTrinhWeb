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
$sql = "SELECT hp.*, ch.MaCanHoHienThi AS SoPhong, CONCAT('Tầng ', ch.Tang) AS DiaChiCanHo, ch.DienTich, '' AS NoiThatCanHo, 'Căn hộ' AS TenLoai,
         kt.HoTen AS TenKhach, kt.SoDienThoai, kt.Email, kt.CCCD, NULL AS GioiTinh, NULL AS NgaySinh, NULL AS DiaChiThuongTru, NULL AS NgheNghiep, NULL AS GhiChuKhach,
               nv.HoTen AS TenNhanVien
        FROM HopDong hp
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
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
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Hợp Đồng Thuê #<?= e((string)$contract['MaHopDong']) ?> - Phòng <?= e($contract['SoPhong']) ?></h1>
        <p class="page-subtitle">Trạng thái: <?= renderStatusBadge($contract['TrangThai']) ?></p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Danh sách hợp đồng</a>
        <a href="<?= $baseUrl ?>/edit.php?id=<?= $id ?>" class="btn btn-primary">✏️ Chỉnh sửa</a>
        <?php if ($contract['TrangThai'] === 'Đang hiệu lực'): ?>
            <a href="<?= $baseUrl ?>/thanh-ly.php?id=<?= $id ?>" class="btn btn-danger" onclick="return confirm('Xác nhận thanh lý hợp đồng này?');">🚪 Thanh lý hợp đồng</a>
        <?php endif; ?>
    </div>
</div>

<!-- SECTION 1 & 2: KHÁCH THUÊ & PHÒNG THUÊ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Box Khách thuê -->
    <div class="card">
        <div class="card-header" style="background-color: #f8fafc;">
            <h3>👤 THÔNG TIN KHÁCH THUÊ</h3>
        </div>
        <div class="card-body">
            <div class="detail-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="detail-item">
                    <div class="detail-label">Họ và tên</div>
                    <div class="detail-value">
                        <a href="<?= $khachThueUrl ?>/detail.php?id=<?= $contract['MaKhach'] ?>" style="font-weight: 700; color: var(--primary-color);">
                            <?= e($contract['TenKhach']) ?>
                        </a>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Số điện thoại</div>
                    <div class="detail-value"><strong><?= e($contract['SoDienThoai']) ?></strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Số CCCD / CMND</div>
                    <div class="detail-value"><code><?= e($contract['CCCD'] ?? 'Chưa cập nhật') ?></code></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?= e($contract['Email'] ?? '-') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Ngày sinh</div>
                    <div class="detail-value"><?= formatDate($contract['NgaySinh']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Giới tính</div>
                    <div class="detail-value"><?= e($contract['GioiTinh']) ?></div>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-label">Địa chỉ thường trú</div>
                    <div class="detail-value"><?= e($contract['DiaChiThuongTru'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Box Căn hộ -->
    <div class="card">
        <div class="card-header" style="background-color: #f8fafc;">
            <h3>🏠 THÔNG TIN CĂN HỘ / PHÒNG</h3>
        </div>
        <div class="card-body">
            <div class="detail-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="detail-item">
                    <div class="detail-label">Số phòng</div>
                    <div class="detail-value" style="font-size: 1.2rem; font-weight: 700; color: var(--primary-color);">
                        Phòng <?= e($contract['SoPhong']) ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Loại phòng & Diện tích</div>
                    <div class="detail-value"><?= e($contract['TenLoai']) ?> (<?= $contract['DienTich'] ?>m²)</div>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-label">Địa chỉ tòa nhà / Căn hộ</div>
                    <div class="detail-value">📍 <?= e($contract['DiaChiCanHo'] ?? 'Tòa nhà A') ?></div>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-label">Mô tả / Tiện ích căn hộ</div>
                    <div class="detail-value"><?= e($contract['NoiThatCanHo'] ?? 'Tiện nghi cơ bản') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 3: THỜI HẠN & GIÁ THUÊ CỌC -->
<div class="card mb-3">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>📄 THỜI HẠN & GIÁ THUÊ THỎA THUẬN</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Giá thuê thỏa thuận</div>
                <div class="detail-value" style="font-size: 1.2rem; font-weight: 700; color: var(--primary-color);">
                    <?= formatMoney($contract['GiaThueThoaThuan']) ?> / tháng
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Tiền đặt cọc</div>
                <div class="detail-value" style="font-size: 1.2rem; font-weight: 700; color: var(--success-color);">
                    <?= formatMoney($contract['TienCoc']) ?>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Thời hạn hợp đồng</div>
                <div class="detail-value"><strong><?= (int)($contract['ThoiHanThang'] ?? 6) ?> tháng</strong></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày ký hợp đồng</div>
                <div class="detail-value"><?= formatDate($contract['NgayKy'] ?? $contract['NgayBatDau']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày bắt đầu thuê</div>
                <div class="detail-value"><?= formatDate($contract['NgayBatDau']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ngày hết hạn hợp đồng</div>
                <div class="detail-value"><?= formatDate($contract['NgayKetThuc']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Số người ở / Phương tiện</div>
                    <div class="detail-value">Thông tin người ở và phương tiện chưa có trong cơ sở dữ liệu hiện tại.</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Nhân viên phụ trách</div>
                <div class="detail-value"><?= e($contract['TenNhanVien'] ?? 'Hệ thống') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 4: ĐƠN GIÁ ĐIỆN / NƯỚC / DỊCH VỤ -->
<div class="card mb-3">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>⚡ ĐƠN GIÁ ĐIỆN / NƯỚC & DỊCH VỤ DÙNG CHUNG</h3>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">⚡ Đơn giá điện</div>
                <div class="detail-value"><strong><?= number_format((float)($contract['GiaDien'] ?? 3500), 0, ',', '.') ?> đ/kWh</strong></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">💧 Đơn giá nước</div>
                <div class="detail-value"><strong><?= number_format((float)($contract['GiaNuoc'] ?? 15000), 0, ',', '.') ?> đ</strong></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🛵 Phí xe máy</div>
                <div class="detail-value"><?= formatMoney($contract['GiaXeMay'] ?? 150000) ?> / tháng</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🚗 Phí ô tô</div>
                <div class="detail-value"><?= formatMoney($contract['GiaOto'] ?? 1200000) ?> / tháng</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🌐 Phí Internet</div>
                <div class="detail-value"><?= formatMoney($contract['GiaInternet'] ?? 200000) ?> / tháng</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🧹 Phí Vệ sinh</div>
                <div class="detail-value"><?= formatMoney($contract['GiaVeSinh'] ?? 100000) ?> / tháng</div>
            </div>
        </div>
    </div>
</div>

<!-- FILE ĐÍNH KÈM -->
<div class="card mb-3">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>📁 HÌNH ẢNH & FILE ĐÍNH KÈM</h3>
    </div>
    <div class="card-body">
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
            <li style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.5rem;">
                <span>📄 File Hợp đồng Scan:</span>
                <?php if (!empty($contract['FileHopDong'])): ?>
                    <a href="<?= e(url($contract['FileHopDong'])) ?>" target="_blank" class="btn btn-sm btn-outline">Xem / Tải file</a>
                <?php else: ?>
                    <span style="color: #94a3b8;">Chưa upload</span>
                <?php endif; ?>
            </li>
            <li style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.5rem;">
                <span>💳 Ảnh CCCD Mặt Trước:</span>
                <?php 
                $cccdTruoc = $contract['AnhCCCDMatTruoc'] ?? $contract['AnhCCCD'] ?? '';
                if (!empty($cccdTruoc)): 
                ?>
                    <a href="<?= e(url($cccdTruoc)) ?>" target="_blank" class="btn btn-sm btn-outline">Xem ảnh</a>
                <?php else: ?>
                    <span style="color: #94a3b8;">Chưa upload</span>
                <?php endif; ?>
            </li>
            <li style="display: flex; justify-content: space-between; align-items: center;">
                <span>💳 Ảnh CCCD Mặt Sau:</span>
                <?php if (!empty($contract['AnhCCCDMatSau'])): ?>
                    <a href="<?= e(url($contract['AnhCCCDMatSau'])) ?>" target="_blank" class="btn btn-sm btn-outline">Xem ảnh</a>
                <?php else: ?>
                    <span style="color: #94a3b8;">Chưa upload</span>
                <?php endif; ?>
            </li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
