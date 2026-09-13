<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php'; require_once __DIR__ . '/../../auth/guard.php'; require_once __DIR__ . '/../../config/database.php'; require_once __DIR__ . '/../../includes/module2_helpers.php'; requirePermission('CANHO_MANAGE'); $isAdmin = (currentUserRole() === 'Admin');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'ID không hợp lệ.');
    redirect('/admin/can-ho/index.php');
}
$s = $pdo->prepare('SELECT c.*, l.TenLoai FROM CanHo c JOIN LoaiCanHo l ON l.MaLoai = c.MaLoai WHERE c.MaCanHo = :id');
$s->execute([':id' => $id]);
$can = $s->fetch();
if (!$can) {
    setFlash('error', 'Không tìm thấy căn hộ.');
    redirect('/admin/can-ho/index.php');
}

if (!isStaffAssignedBuilding((string)($can['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền truy cập căn hộ thuộc tòa nhà này.');
    redirect('/admin/can-ho/index.php');
}
$s = $pdo->prepare('SELECT * FROM CanHo_Anh WHERE MaCanHo = :id ORDER BY ThuTu, MaAnh');
$s->execute([':id' => $id]);
$images = $s->fetchAll();

$s = $pdo->prepare('SELECT h.*, k.HoTen TenKhach FROM HopDong h JOIN KhachThue k ON k.MaKhach = h.MaKhach WHERE h.MaCanHo = :id ORDER BY h.NgayBatDau DESC');
$s->execute([':id' => $id]);
$contracts = $s->fetchAll();

$title = 'Chi tiết căn hộ ' . $can['SoPhong'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Hồ Sơ Căn Hộ</h1>
        <p class="text-muted">Quản lý thông tin niêm yết, biểu phí dịch vụ & album ảnh căn hộ</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a class="btn" style="background: #eff6ff; color: #1d4ed8; border: 1.5px solid #bfdbfe; font-weight: 700;" href="create.php?mode=room&DiaChi=<?= urlencode((string)$can['DiaChi']) ?>">
            <?= svgIcon('door', '', 15) ?> <span>Thêm phòng tòa này</span>
        </a>
        <a class="btn btn-secondary" href="edit.php?id=<?= $id ?>">
            <?= svgIcon('edit', '', 15) ?> <span>Chỉnh sửa</span>
        </a>
        <a class="btn btn-outline" href="index.php">
            &larr; <span>Danh sách căn hộ</span>
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- CỘT TRÁI: THÔNG TIN CĂN HỘ (PHONG CÁCH BẤT ĐỘNG SẢN / BOOKING) -->
    <div class="card" style="margin-bottom: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06); border: 1px solid #e2e8f0;">
        <style>
            .booking-badge-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                margin-bottom: 0.75rem;
                flex-wrap: wrap;
            }
            .booking-type-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                font-size: 0.78rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #2563eb;
                background: #eff6ff;
                padding: 0.35rem 0.85rem;
                border-radius: 9999px;
                border: 1px solid #dbeafe;
            }
            .booking-address-hero {
                display: flex;
                align-items: flex-start;
                gap: 0.65rem;
                margin-top: 0.35rem;
            }
            .booking-address-icon-box {
                width: 30px;
                height: 30px;
                border-radius: 8px;
                background: #fef2f2;
                border: 1px solid #fee2e2;
                color: #ef4444;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-top: 2px;
            }
            .booking-address-text {
                font-size: 1.12rem;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.45;
                font-family: 'Plus Jakarta Sans', var(--font-sans, sans-serif);
                letter-spacing: -0.01em;
            }

            /* PRICE BANNER AIRBNB */
            .booking-price-banner {
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                border: 1px solid #86efac;
                border-radius: 12px;
                padding: 0.95rem 1.25rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin: 1.15rem 0 1.35rem;
                box-shadow: 0 2px 8px rgba(16, 185, 129, 0.06);
                flex-wrap: wrap;
            }
            .booking-price-label {
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #166534;
                margin-bottom: 0.2rem;
            }
            .booking-price-amount {
                font-size: 1.35rem;
                font-weight: 800;
                color: #15803d;
                letter-spacing: -0.015em;
                line-height: 1.15;
                font-family: 'Plus Jakarta Sans', var(--font-sans, sans-serif);
            }
            .booking-price-unit {
                font-size: 0.82rem;
                font-weight: 600;
                color: #166534;
            }
            .booking-price-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                font-size: 0.78rem;
                font-weight: 700;
                color: #166534;
                background: #ffffff;
                padding: 0.35rem 0.8rem;
                border-radius: 9999px;
                border: 1px solid #bbf7d0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            }

            /* KEY HIGHLIGHTS 3 CHIPS */
            .booking-highlights-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 0.85rem;
                margin-bottom: 1.5rem;
            }
            .booking-highlight-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 0.85rem 1rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
                transition: all 0.2s ease;
            }
            .booking-highlight-card:hover {
                border-color: #cbd5e1;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
                transform: translateY(-1px);
            }
            .booking-highlight-icon {
                width: 42px;
                height: 42px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .booking-highlight-label {
                font-size: 0.72rem;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin-bottom: 0.15rem;
            }
            .booking-highlight-val {
                font-size: 1.05rem;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.2;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* AMENITIES 2-COL */
            .booking-amenities-section {
                margin-bottom: 1.5rem;
            }
            .booking-section-title {
                font-size: 0.95rem;
                font-weight: 700;
                color: #0f172a;
                margin-bottom: 0.85rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .booking-amenities-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            .booking-amenity-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 0.75rem 0.95rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                transition: all 0.18s ease;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
            }
            .booking-amenity-card:hover {
                border-color: #cbd5e1;
                background: #fafafa;
                box-shadow: 0 3px 10px rgba(15, 23, 42, 0.05);
                transform: translateY(-1px);
            }
            .booking-amenity-icon {
                width: 38px;
                height: 38px;
                border-radius: 9px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .booking-amenity-info {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
            }
            .booking-amenity-name {
                font-size: 0.78rem;
                font-weight: 600;
                color: #64748b;
                margin-bottom: 0.15rem;
            }
            .booking-amenity-val {
                font-size: 0.92rem;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.2;
            }
            .booking-amenity-val small {
                font-size: 0.75rem;
                font-weight: 500;
                color: #64748b;
            }

            /* ABOUT SPACE */
            .booking-about-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 1.15rem 1.35rem;
                font-size: 0.92rem;
                color: #334155;
                line-height: 1.7;
            }

            @media (max-width: 640px) {
                .booking-highlights-grid {
                    grid-template-columns: 1fr;
                }
                .booking-amenities-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <?php
        $dt = (float)$can['DienTich'];
        $formattedDt = ($dt == (int)$dt) ? (string)(int)$dt : number_format($dt, 1, ',', '.');
        ?>

        <!-- HEADER CĂN HỘ -->
        <div style="padding: 1.5rem 1.75rem 1.25rem; border-bottom: 1px solid #f1f5f9;">
            <div class="booking-badge-row">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span class="booking-type-pill">
                        <?= svgIcon('building', '', 13) ?> <?= e($can['TenLoai']) ?>
                    </span>
                    <span style="font-size: 0.8rem; font-weight: 600; color: #64748b;">Mã căn #<?= $id ?></span>
                </div>
                <div>
                    <?= renderStatusBadge($can['TrangThai']) ?>
                </div>
            </div>

            <!-- ĐỊA CHỈ NỔI BẬT Ở TRÊN (THAY THẾ TIÊU ĐỀ PHÒNG) -->
            <div class="booking-address-hero">
                <div class="booking-address-icon-box">
                    <?= svgIcon('map-pin', '', 16) ?>
                </div>
                <div class="booking-address-text">
                    <?= e($can['DiaChi'] ?? 'Chưa cập nhật địa chỉ') ?>
                </div>
            </div>
        </div>

        <div class="card-body" style="padding: 1.5rem 1.75rem;">
            <!-- BANNER GIÁ THUÊ NIÊM YẾT CHUẨN BOOKING -->
            <div class="booking-price-banner">
                <div>
                    <div class="booking-price-label">Giá thuê niêm yết</div>
                    <div style="display: flex; align-items: baseline; gap: 0.35rem;">
                        <span class="booking-price-amount"><?= formatMoney($can['GiaThue']) ?></span>
                        <span class="booking-price-unit">/ tháng</span>
                    </div>
                </div>
                <div>
                    <span class="booking-price-badge">
                        <?= svgIcon('check', '', 14) ?> Giá thuê chính thức
                    </span>
                </div>
            </div>

            <!-- 3 ĐẶC ĐIỂM CỐT LÕI (ICON TRỰC QUAN) -->
            <div class="booking-highlights-grid">
                <!-- DIỆN TÍCH -->
                <div class="booking-highlight-card">
                    <div class="booking-highlight-icon" style="background: #eff6ff; color: #2563eb;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
                    </div>
                    <div>
                        <div class="booking-highlight-label">Diện tích</div>
                        <div class="booking-highlight-val"><?= $formattedDt ?> m²</div>
                    </div>
                </div>

                <!-- LOẠI PHÒNG -->
                <div class="booking-highlight-card">
                    <div class="booking-highlight-icon" style="background: #fdf2f8; color: #db2777;">
                        <?= svgIcon('building', '', 20) ?>
                    </div>
                    <div>
                        <div class="booking-highlight-label">Loại phòng</div>
                        <div class="booking-highlight-val" title="<?= e($can['TenLoai']) ?>"><?= e($can['TenLoai']) ?></div>
                    </div>
                </div>

                <!-- SỐ PHÒNG -->
                <div class="booking-highlight-card">
                    <div class="booking-highlight-icon" style="background: #f0fdf4; color: #16a34a;">
                        <?= svgIcon('door', '', 20) ?>
                    </div>
                    <div>
                        <div class="booking-highlight-label">Số phòng</div>
                        <div class="booking-highlight-val"><?= e($can['SoPhong']) ?></div>
                    </div>
                </div>
            </div>

            <!-- DỊCH VỤ & TIỆN ÍCH TÒA NHÀ -->
            <div class="booking-amenities-section">
                <div class="booking-section-title">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: #6366f1;"><?= svgIcon('sparkles', '', 16) ?></span>
                        <span>Biểu phí dịch vụ & tiện ích</span>
                    </span>
                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">Áp dụng theo định mức</span>
                </div>

                <div class="booking-amenities-grid">
                    <!-- TIỀN ĐIỆN -->
                    <div class="booking-amenity-card">
                        <div class="booking-amenity-icon" style="background: #fffbeb; color: #d97706;">
                            <?= svgIcon('electric', '', 18) ?>
                        </div>
                        <div class="booking-amenity-info">
                            <span class="booking-amenity-name">Tiền điện</span>
                            <span class="booking-amenity-val">
                                <?= formatMoney(normalizeServiceFee($can['GiaDien'] ?? 3800, 'dien')) ?> <small>/kWh</small>
                            </span>
                        </div>
                    </div>

                    <!-- TIỀN NƯỚC -->
                    <div class="booking-amenity-card">
                        <div class="booking-amenity-icon" style="background: #f0f9ff; color: #0284c7;">
                            <?= svgIcon('water', '', 18) ?>
                        </div>
                        <div class="booking-amenity-info">
                            <span class="booking-amenity-name">Tiền nước</span>
                            <span class="booking-amenity-val">
                                <?= formatMoney(normalizeServiceFee($can['GiaNuoc'] ?? 100000, 'nuoc')) ?> <small>/tháng</small>
                            </span>
                        </div>
                    </div>

                    <!-- PHÍ XE MÁY -->
                    <div class="booking-amenity-card">
                        <div class="booking-amenity-icon" style="background: #f5f3ff; color: #7c3aed;">
                            <?= svgIcon('motorcycle', '', 18) ?>
                        </div>
                        <div class="booking-amenity-info">
                            <span class="booking-amenity-name">Phí xe máy</span>
                            <span class="booking-amenity-val">
                                <?= formatMoney(normalizeServiceFee($can['GiaXeMay'] ?? 120000, 'xemay')) ?> <small>/xe</small>
                            </span>
                        </div>
                    </div>

                    <!-- PHÍ Ô TÔ -->
                    <div class="booking-amenity-card">
                        <div class="booking-amenity-icon" style="background: #eff6ff; color: #2563eb;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8C2.1 11 2 11.5 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
                        </div>
                        <div class="booking-amenity-info">
                            <span class="booking-amenity-name">Phí ô tô</span>
                            <span class="booking-amenity-val">
                                <?= formatMoney(normalizeServiceFee($can['GiaOto'] ?? 1200000, 'oto')) ?> <small>/xe</small>
                            </span>
                        </div>
                    </div>

                    <!-- WIFI INTERNET -->
                    <div class="booking-amenity-card">
                        <div class="booking-amenity-icon" style="background: #fdf2f8; color: #db2777;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h.01"/><path d="M2 8.82a15 15 0 0 1 20 0"/><path d="M5 12.86a10 10 0 0 1 14 0"/><path d="M8.5 16.43a5 5 0 0 1 7 0"/></svg>
                        </div>
                        <div class="booking-amenity-info">
                            <span class="booking-amenity-name">Wifi Internet</span>
                            <span class="booking-amenity-val">
                                <?= formatMoney(normalizeServiceFee($can['GiaInternet'] ?? 100000, 'internet')) ?> <small>/tháng</small>
                            </span>
                        </div>
                    </div>

                    <!-- PHÍ VỆ SINH -->
                    <div class="booking-amenity-card">
                        <div class="booking-amenity-icon" style="background: #ecfdf5; color: #059669;">
                            <?= svgIcon('shield', '', 18) ?>
                        </div>
                        <div class="booking-amenity-info">
                            <span class="booking-amenity-name">Phí vệ sinh</span>
                            <span class="booking-amenity-val">
                                <?= formatMoney(normalizeServiceFee($can['GiaVeSinh'] ?? 50000, 'vesinh')) ?> <small>/tháng</small>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GIỚI THIỆU & MÔ TẢ PHÒNG -->
            <div>
                <div class="booking-section-title" style="margin-bottom: 0.65rem;">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="color: #64748b;"><?= svgIcon('info', '', 16) ?></span>
                        <span>Mô tả & Ghi chú căn hộ</span>
                    </span>
                </div>
                <div class="booking-about-box">
                    <?= nl2br(e($can['MoTa'] ?? '')) ?: '<span style="color: #94a3b8; font-style: italic;">Chưa có thông tin mô tả chi tiết cho căn hộ này.</span>' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: ALBUM ẢNH -->
    <div class="card" style="margin-bottom: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06); border: 1px solid #e2e8f0;">
        <div class="card-header" style="background: #ffffff; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
            <h3 style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0;">
                <span style="color: #2563eb; display: inline-flex;"><?= svgIcon('camera', '', 18) ?></span>
                <span>Bộ Sưu Tập Hình Ảnh (<?= count($images) ?>)</span>
            </h3>
            <?php if (!empty($images)): ?>
                <form method="post" action="delete-anh.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa TẤT CẢ ảnh của căn hộ này không? Thao tác này sẽ xóa vĩnh viễn và không thể khôi phục.');" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?= e(module2_csrf_token()) ?>">
                    <input type="hidden" name="ma_can_ho" value="<?= $id ?>">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="submit" class="btn btn-sm btn-danger" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <?= svgIcon('trash', '', 14) ?> <span>Xóa hết ảnh</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding: 1.25rem 1.5rem;">
            <?php if (empty($images)): ?>
                <div style="padding: 3rem 1rem; text-align: center; color: #94a3b8;">
                    <div style="margin-bottom: 0.5rem;"><?= svgIcon('building', '', 40) ?></div>
                    <div style="font-size: 0.95rem; font-weight: 500;">Căn hộ hiện chưa có ảnh nào.</div>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem;" id="apartmentGallery">
                    <?php foreach ($images as $index => $img): $imageUrl = appUrl($img['DuongDan']); ?>
                        <div style="position: relative; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; background: #000; box-shadow: 0 2px 8px rgba(0,0,0,0.06); group">
                            <a href="<?= e($imageUrl) ?>" target="_blank" style="display: block; height: 130px; overflow: hidden;">
                                <img src="<?= e($imageUrl) ?>" alt="Ảnh căn <?= e($can['SoPhong']) ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;">
                            </a>

                            <?php if ((int)$img['LaAnhDaiDien'] === 1): ?>
                                <span style="position: absolute; top: 6px; left: 6px; background: rgba(37, 99, 235, 0.9); backdrop-filter: blur(4px); color: #fff; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 6px;">
                                    Đại diện
                                </span>
                            <?php endif; ?>

                            <div style="display: flex; gap: 0.25rem; padding: 0.4rem; background: #ffffff; border-top: 1px solid #f1f5f9; justify-content: flex-end;">
                                <?php if ((int)$img['LaAnhDaiDien'] !== 1): ?>
                                    <form method="post" action="set-anh-dai-dien.php" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= e(module2_csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= $img['MaAnh'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" style="font-size: 0.725rem; padding: 2px 6px;" title="Đặt làm ảnh đại diện">
                                            Đặt đại diện
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="delete-anh.php" onsubmit="return confirm('Xác nhận xóa ảnh này?');" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= e(module2_csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= $img['MaAnh'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" style="font-size: 0.725rem; padding: 2px 6px;" title="Xóa ảnh">
                                        <?= svgIcon('trash', '', 12) ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- KHUNG TẢI THÊM ẢNH NHANH TRỰC TIẾP TẠI TRANG CHI TIẾT -->
            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                <form method="post" action="upload-anh.php" enctype="multipart/form-data" style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="csrf_token" value="<?= e(module2_csrf_token()) ?>">
                    <input type="hidden" name="ma_can_ho" value="<?= $id ?>">
                    <div style="flex: 1; min-width: 180px;">
                        <label style="font-weight: 600; font-size: 0.8rem; color: #475569; margin-bottom: 0.35rem; display: block;">
                            Tải thêm ảnh vào album:
                        </label>
                        <input type="file" name="images[]" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required style="font-size: 0.825rem; padding: 0.4rem 0.6rem;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 1rem; font-size: 0.825rem; font-weight: 600;">
                        <?= svgIcon('plus', '', 14) ?> <span>Tải lên</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SECTION: LỊCH SỬ HỢP ĐỒNG THUÊ -->
<div class="card">
    <div class="card-header">
        <h3 style="display: flex; align-items: center; gap: 0.5rem;">
            <?= svgIcon('contract', '', 18) ?>
            <span>Lịch Sử Hợp Đồng Thuê Căn Hộ</span>
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($contracts)): ?>
            <div style="padding: 2.5rem; text-align: center; color: #64748b; font-size: 0.9rem;">
                Căn hộ này chưa có lịch sử hợp đồng thuê nào.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Mã HĐ</th>
                            <th>Khách thuê</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Giá thuê thỏa thuận</th>
                            <th>Trạng thái hợp đồng</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $h): ?>
                            <tr>
                                <td><strong>#<?= (int)$h['MaHopDong'] ?></strong></td>
                                <td>
                                    <div style="font-weight: 600; color: #0f172a;"><?= e($h['TenKhach']) ?></div>
                                </td>
                                <td><?= formatDate($h['NgayBatDau']) ?></td>
                                <td><?= formatDate($h['NgayKetThuc']) ?></td>
                                <td><strong style="color: #2563eb;"><?= formatMoney($h['GiaThueThoaThuan']) ?></strong></td>
                                <td><?= renderStatusBadge($h['TrangThai']) ?></td>
                                <td style="text-align: right;">
                                    <a href="<?= url('/admin/hop-dong/detail.php?id=' . (int)$h['MaHopDong']) ?>" class="btn btn-sm btn-outline">
                                        <?= svgIcon('eye', '', 13) ?> Xem HĐ
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>