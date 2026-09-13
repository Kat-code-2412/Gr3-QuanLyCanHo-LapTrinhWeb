<?php

declare(strict_types=1);

$title = 'Chi Tiết Hồ Sơ Khách Thuê - Hệ Thống Căn Hộ Dịch Vụ';
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

// Kiểm tra quyền theo tòa nhà cho nhân viên
$staffAssigned = getStaffAssignedBuildings();
if ($staffAssigned !== null) {
    $hasAccess = false;
    foreach ($allContracts as $c) {
        if (in_array($c['DiaChi'], $staffAssigned, true)) {
            $hasAccess = true;
            break;
        }
    }
    if (!$hasAccess) {
        setFlash('error', 'Bạn không có quyền truy cập hồ sơ khách thuê này (không thuộc tòa nhà được phân công).');
        redirect($baseUrl . '/index.php');
    }
    // Lọc danh sách hợp đồng hiển thị chỉ thuộc tòa nhà nhân viên quản lý
    $allContracts = array_values(array_filter($allContracts, function($c) use ($staffAssigned) {
        return in_array($c['DiaChi'], $staffAssigned, true);
    }));
}

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
    $tenantStatus = 'Đang Thuê Phòng';
} else {
    $tenantStatus = 'Đã Trả Phòng';
}

$initials = getInitials($tenant['HoTen']);
$isFemale = ($tenant['GioiTinh'] === 'Nữ');
?>

<!-- ========================================================================
     HEADER & NÚT ĐIỀU HƯỚNG
     ======================================================================== -->
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div>
        <h1 class="page-title" style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; color: #0f172a; margin: 0;">
            Chi Tiết Hồ Sơ Khách Thuê
        </h1>
    </div>
    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="font-weight: 600;">
            &larr; <span>Danh sách</span>
        </a>
        <?php if (currentUserRole() === 'Admin'): ?>
            <a href="<?= $baseUrl ?>/edit.php?id=<?= $id ?>" 
               class="btn btn-primary" 
               style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border: none; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25); font-weight: 600;">
                <?= svgIcon('edit', '', 14) ?> <span>Sửa Thông Tin</span>
            </a>
            <a href="<?= $baseUrl ?>/delete.php?id=<?= $id ?>" 
               class="btn btn-outline" 
               style="border-color: #ef4444; color: #dc2626; background: #fef2f2; font-weight: 600;" 
               onclick="return confirm('Bạn có chắc chắn muốn xóa hồ sơ khách thuê này không? Thao tác này không thể hoàn tác!');">
                <?= svgIcon('trash', '', 14) ?> <span>Xóa Hồ Sơ</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================
     PROFILE HERO BANNER (THẺ TỔNG QUAN HỒ SƠ CƯ DÂN)
     ======================================================================== -->
<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1.25rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <!-- AVATAR INITIALS -->
        <div style="width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; font-weight: 800; color: #ffffff; flex-shrink: 0; background: <?= $isFemale ? 'linear-gradient(135deg, #ec4899 0%, #db2777 100%)' : 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)' ?>; box-shadow: 0 4px 12px rgba(0,0,0,0.12);">
            <?= e($initials) ?>
        </div>

        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                    <?= e($tenant['HoTen']) ?>
                </h2>
                <?php if ($tenantStatus === 'Đang Thuê Phòng'): ?>
                    <span class="badge badge-success" style="font-size: 0.8rem; font-weight: 600; padding: 0.2rem 0.65rem;">
                        Đang Thuê Phòng
                    </span>
                <?php else: ?>
                    <span class="badge badge-secondary" style="font-size: 0.8rem; font-weight: 600; padding: 0.2rem 0.65rem; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">
                        Đã Trả Phòng
                    </span>
                <?php endif; ?>

                <?php if (!empty($tenant['GhiChu']) && stripos($tenant['GhiChu'], 'VIP') !== false): ?>
                    <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 0.78rem; font-weight: 700;">
                        VIP Member
                    </span>
                <?php endif; ?>
            </div>

            <div style="display: flex; align-items: center; gap: 0.85rem; margin-top: 0.35rem; color: #64748b; font-size: 0.85rem; flex-wrap: wrap;">
                <span>Mã cư dân: <strong style="color: #334155; font-family: monospace;">#KT-<?= str_pad((string)$tenant['MaKhach'], 4, '0', STR_PAD_LEFT) ?></strong></span>
                <span>&bull;</span>
                <span style="display: flex; align-items: center; gap: 0.3rem;">
                    <?= svgIcon('phone', '', 14) ?> <strong style="color: #0f172a;"><?= e($tenant['SoDienThoai']) ?></strong>
                </span>
                <?php if (!empty($tenant['Email'])): ?>
                    <span>&bull;</span>
                    <span style="display: flex; align-items: center; gap: 0.3rem;">
                        <?= svgIcon('mail', '', 14) ?> <?= e($tenant['Email']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- KHỐI PHÒNG ĐANG Ở (BỎ PHẦN TỔNG HỢP ĐỒNG) -->
    <div style="border-left: 2px solid #e2e8f0; padding-left: 1.5rem; min-width: 180px;">
        <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">
            PHÒNG ĐANG Ở
        </div>
        <div style="font-size: 1.25rem; font-weight: 800; color: #0284c7; margin-top: 0.2rem;">
            <?= $activeContract ? 'Phòng ' . e($activeContract['SoPhong']) : '<span style="color: #94a3b8; font-size: 0.95rem; font-weight: 600;">Chưa nhận phòng</span>' ?>
        </div>
        <?php if ($activeContract && !empty($activeContract['DiaChi'])): ?>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($activeContract['DiaChi']) ?>">
                <?= e($activeContract['DiaChi']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================
     BỐ CỤC 2 CỘT THÔNG TIN: GỌN GÀNG, SANG TRỌNG, KHÔNG RỐI MẮT
     ======================================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- CỘT 1: THÔNG TIN NHÂN THÂN & ĐỊNH DANH -->
    <div class="card" style="margin: 0; padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.9rem 1.25rem;">
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                <?= svgIcon('id-card', '', 18) ?>
                <span>Thông Tin Cá Nhân & Định Danh</span>
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; width: 150px; font-weight: 600;">Mã Khách Thuê</td>
                        <td style="padding: 0.85rem 1.25rem; font-weight: 700; color: #0284c7; font-family: monospace;">
                            #KT-<?= str_pad((string)$tenant['MaKhach'], 4, '0', STR_PAD_LEFT) ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600;">Họ và Tên</td>
                        <td style="padding: 0.85rem 1.25rem; font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                            <?= e($tenant['HoTen']) ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600;">Số CCCD / CMND</td>
                        <td style="padding: 0.85rem 1.25rem;">
                            <code style="background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.85rem; color: #1e293b; font-weight: 700; border: 1px solid #e2e8f0;">
                                <?= e($tenant['CCCD']) ?>
                            </code>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600;">Ngày Sinh</td>
                        <td style="padding: 0.85rem 1.25rem; color: #1e293b; font-weight: 600;">
                            <?= formatDate($tenant['NgaySinh']) ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600;">Giới Tính</td>
                        <td style="padding: 0.85rem 1.25rem;">
                            <?php if (!empty($tenant['GioiTinh'])): ?>
                                <span class="badge" style="<?= $isFemale ? 'background: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8;' : 'background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;' ?> font-weight: 600; font-size: 0.8rem; padding: 0.2rem 0.55rem;">
                                    <?= e($tenant['GioiTinh']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600;">Nghề Nghiệp</td>
                        <td style="padding: 0.85rem 1.25rem; color: #334155; font-weight: 500;">
                            <?= !empty($tenant['NgheNghiep']) ? e($tenant['NgheNghiep']) : '<span style="color: #94a3b8; font-style: italic;">Chưa cập nhật</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600; vertical-align: top;">Địa Chỉ Thường Trú</td>
                        <td style="padding: 0.85rem 1.25rem; color: #334155; line-height: 1.45;">
                            <?= !empty($tenant['DiaChiThuongTru']) ? e($tenant['DiaChiThuongTru']) : '<span style="color: #94a3b8; font-style: italic;">Chưa cập nhật</span>' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CỘT 2: THÔNG TIN LIÊN HỆ & VẬN HÀNH -->
    <div class="card" style="margin: 0; padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.9rem 1.25rem;">
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                <?= svgIcon('phone', '', 18) ?>
                <span>Thông Tin Liên Lạc & Ghi Chú</span>
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; width: 140px; font-weight: 600;">Số Điện Thoại</td>
                        <td style="padding: 0.85rem 1.25rem;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                                <?= svgIcon('phone', '', 14) ?>
                                <span><?= e($tenant['SoDienThoai']) ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600;">Email Liên Hệ</td>
                        <td style="padding: 0.85rem 1.25rem;">
                            <?php if (!empty($tenant['Email'])): ?>
                                <div style="color: #0284c7; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                                    <?= svgIcon('mail', '', 14) ?>
                                    <span><?= e($tenant['Email']) ?></span>
                                </div>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Chưa cập nhật</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600;">Trạng Thái Cư Dân</td>
                        <td style="padding: 0.85rem 1.25rem;">
                            <?php if ($tenantStatus === 'Đang Thuê Phòng'): ?>
                                <span class="badge badge-success" style="font-size: 0.8rem; font-weight: 600; padding: 0.2rem 0.65rem;">
                                    Đang Thuê Phòng
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="font-size: 0.8rem; font-weight: 600; padding: 0.2rem 0.65rem; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">
                                    Đã Trả Phòng
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0.85rem 1.25rem; color: #64748b; font-weight: 600; vertical-align: top;">Ghi chú</td>
                        <td style="padding: 0.85rem 1.25rem;">
                            <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 0.75rem 1rem; color: #92400e; font-size: 0.875rem; line-height: 1.5;">
                                <?= !empty($tenant['GhiChu']) ? nl2br(e($tenant['GhiChu'])) : '<span style="color: #b45309; font-style: italic;">Không có ghi chú nào.</span>' ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================
     HỢP ĐỒNG HIỆN TẠI (NẾU CÓ)
     ======================================================================== -->
<div class="card" style="margin-bottom: 1.5rem; padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0;">
    <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.9rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
            <?= svgIcon('key', '', 18) ?>
            <span>Hợp Đồng Thuê Đang Có Hiệu Lực</span>
        </h3>
        <?php if ($activeContract !== null): ?>
            <a href="<?= url('/admin/hop-dong/detail.php?id=' . $activeContract['MaHopDong']) ?>" 
               class="btn btn-sm btn-outline" 
               style="font-size: 0.8rem; font-weight: 600; padding: 0.25rem 0.6rem; color: #0284c7; border-color: #cbd5e1;">
                Xem chi tiết hợp đồng &rarr;
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <?php if ($activeContract !== null): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0284c7; border-radius: 8px; padding: 0.85rem 1rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Phòng Đang Thuê</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #0284c7; margin-top: 0.2rem;">
                        Phòng <?= e($activeContract['SoPhong']) ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.15rem;">
                        <?= e($activeContract['TenLoai']) ?> &bull; <?= (float)$activeContract['DienTich'] ?> m²
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; border-radius: 8px; padding: 0.85rem 1rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Giá Thuê / Tháng</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #059669; margin-top: 0.2rem;">
                        <?= formatMoney($activeContract['GiaThueThoaThuan']) ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.15rem;">
                        Thanh toán định kỳ hằng tháng
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 0.85rem 1rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Tiền Đặt Cọc</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #d97706; margin-top: 0.2rem;">
                        <?= formatMoney($activeContract['TienCoc']) ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.15rem;">
                        Hoàn lại khi kết thúc hợp đồng
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #6366f1; border-radius: 8px; padding: 0.85rem 1rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Thời Hạn Thuê</div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: #4338ca; margin-top: 0.2rem;">
                        <?= formatDate($activeContract['NgayBatDau']) ?> &rarr; <?= formatDate($activeContract['NgayKetThuc']) ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #10b981; margin-top: 0.15rem; font-weight: 600;">
                        ● Đang hiệu lực
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="padding: 2rem 1rem; text-align: center;">
                <p style="color: #64748b; font-size: 0.9rem; margin: 0;">
                    Khách hàng này hiện chưa có hợp đồng thuê phòng nào đang hoạt động.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================
     LỊCH SỬ HỢP ĐỒNG THUÊ (NẾU CÓ NHIỀU HỢP ĐỒNG)
     ======================================================================== -->
<?php if (count($allContracts) > 1): ?>
    <div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.9rem 1.25rem;">
            <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                <?= svgIcon('invoice', '', 18) ?>
                <span>Lịch Sử Các Hợp Đồng Thuê Trước Đây</span>
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr style="background: #f1f5f9; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em;">
                            <th style="padding: 0.8rem 1rem;">Mã HĐ</th>
                            <th style="padding: 0.8rem 1rem;">Phòng Thuê</th>
                            <th style="padding: 0.8rem 1rem;">Thời Hạn Hợp Đồng</th>
                            <th style="padding: 0.8rem 1rem; text-align: right;">Giá Thuê / Tháng</th>
                            <th style="padding: 0.8rem 1rem; text-align: right;">Tiền Đặt Cọc</th>
                            <th style="padding: 0.8rem 1rem; text-align: center;">Trạng Thái</th>
                            <th style="padding: 0.8rem 1rem; text-align: center; width: 100px;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allContracts as $c): ?>
                            <tr style="vertical-align: middle; border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.8rem 1rem;">
                                    <strong style="color: #0284c7; font-family: monospace; font-size: 0.9rem;">
                                        #HD-<?= str_pad((string)$c['MaHopDong'], 4, '0', STR_PAD_LEFT) ?>
                                    </strong>
                                </td>
                                <td style="padding: 0.8rem 1rem;">
                                    <span style="font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                        Phòng <?= e($c['SoPhong']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.8rem 1rem; font-size: 0.875rem; color: #334155;">
                                    <?= formatDate($c['NgayBatDau']) ?> &rarr; <?= formatDate($c['NgayKetThuc']) ?>
                                </td>
                                <td style="padding: 0.8rem 1rem; text-align: right; font-weight: 700; color: #0f172a; font-size: 0.9rem;">
                                    <?= formatMoney($c['GiaThueThoaThuan']) ?>
                                </td>
                                <td style="padding: 0.8rem 1rem; text-align: right; font-weight: 600; color: #059669; font-size: 0.9rem;">
                                    <?= formatMoney($c['TienCoc']) ?>
                                </td>
                                <td style="padding: 0.8rem 1rem; text-align: center;">
                                    <?= renderStatusBadge($c['TrangThai']) ?>
                                </td>
                                <td style="padding: 0.8rem 1rem; text-align: center;">
                                    <a href="<?= url('/admin/hop-dong/detail.php?id=' . $c['MaHopDong']) ?>" 
                                       class="btn btn-sm btn-outline" 
                                       style="padding: 0.25rem 0.55rem; font-size: 0.78rem; font-weight: 600;" 
                                       title="Xem chi tiết hợp đồng">
                                        <?= svgIcon('eye', '', 14) ?> Xem
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
