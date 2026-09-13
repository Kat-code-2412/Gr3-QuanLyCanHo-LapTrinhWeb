<?php

declare(strict_types=1);

$title = 'Quản Lý Khách Thuê - Hệ Thống Căn Hộ Dịch Vụ';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue');

// --- PHẠM VI TÒA NHÀ PHÂN CÔNG (BUILDING SCOPING) ---
$staffAssigned = getStaffAssignedBuildings();
$bldCondCh = buildStaffBuildingCondition('ch.DiaChi');
$bldCondCanHo = buildStaffBuildingCondition('DiaChi');

if ($staffAssigned !== null) {
    // Thống kê theo phạm vi tòa nhà nhân viên quản lý
    $totalRoomStmt = $pdo->prepare("SELECT COUNT(*) FROM CanHo WHERE {$bldCondCanHo['sql']}");
    $totalRoomStmt->execute($bldCondCanHo['params']);
    $totalRoomCount = (int)$totalRoomStmt->fetchColumn();

    $rentingRoomStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT hp.MaCanHo) 
        FROM HopDong hp 
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo 
        WHERE hp.TrangThai = 'Đang hiệu lực' AND {$bldCondCh['sql']}
    ");
    $rentingRoomStmt->execute($bldCondCh['params']);
    $rentingRoomCount = (int)$rentingRoomStmt->fetchColumn();

    $rentingTenantStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT kt.MaKhach) 
        FROM KhachThue kt 
        JOIN HopDong hd ON kt.MaKhach = hd.MaKhach 
        JOIN CanHo ch ON hd.MaCanHo = ch.MaCanHo 
        WHERE hd.TrangThai = 'Đang hiệu lực' AND {$bldCondCh['sql']}
    ");
    $rentingTenantStmt->execute($bldCondCh['params']);
    $rentingTenantCount = (int)$rentingTenantStmt->fetchColumn();

    $totalTenantStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT kt.MaKhach) 
        FROM KhachThue kt 
        JOIN HopDong hd ON kt.MaKhach = hd.MaKhach 
        JOIN CanHo ch ON hd.MaCanHo = ch.MaCanHo 
        WHERE {$bldCondCh['sql']}
    ");
    $totalTenantStmt->execute($bldCondCh['params']);
    $totalTenantCount = (int)$totalTenantStmt->fetchColumn();
} else {
    // Admin: toàn hệ thống
    $totalTenantCount = (int)$pdo->query('SELECT COUNT(*) FROM KhachThue')->fetchColumn();
    $rentingTenantCount = (int)$pdo->query("
        SELECT COUNT(DISTINCT kt.MaKhach) 
        FROM KhachThue kt 
        JOIN HopDong hd ON kt.MaKhach = hd.MaKhach 
        WHERE hd.TrangThai = 'Đang hiệu lực'
    ")->fetchColumn();
    $totalRoomCount = (int)$pdo->query('SELECT COUNT(*) FROM CanHo')->fetchColumn();
    $rentingRoomCount = (int)$pdo->query("
        SELECT COUNT(DISTINCT MaCanHo) 
        FROM HopDong 
        WHERE TrangThai = 'Đang hiệu lực'
    ")->fetchColumn();
}
$pastTenantCount = max(0, $totalTenantCount - $rentingTenantCount);
$vacantRoomCount = max(0, $totalRoomCount - $rentingRoomCount);
$occupancyRate = ($totalRoomCount > 0) ? round(($rentingRoomCount / $totalRoomCount) * 100, 1) : 0;

// --- 2. LẤY THAM SỐ LỌC, TÌM KIẾM, PHÂN TRANG ---
$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50], true)) {
    $perPage = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// --- 3. XÂY DỰNG MỆNH ĐỀ WHERE ---
$whereConditions = [];
$params = [];

if ($keyword !== '') {
    $whereConditions[] = '(kt.MaKhach LIKE ? OR kt.HoTen LIKE ? OR kt.SoDienThoai LIKE ? OR kt.CCCD LIKE ? OR kt.Email LIKE ? OR ch.SoPhong LIKE ?)';
    $k = '%' . $keyword . '%';
    $params = [$k, $k, $k, $k, $k, $k];
}

if ($statusFilter === 'Đang thuê') {
    $whereConditions[] = '(SELECT COUNT(*) FROM HopDong h1 WHERE h1.MaKhach = kt.MaKhach AND h1.TrangThai = "Đang hiệu lực") > 0';
} elseif ($statusFilter === 'Đã trả phòng' || $statusFilter === 'Chưa thuê') {
    $whereConditions[] = '(SELECT COUNT(*) FROM HopDong h1 WHERE h1.MaKhach = kt.MaKhach AND h1.TrangThai = "Đang hiệu lực") = 0';
}

// Bắt buộc nhân viên chỉ thấy khách thuê thuộc tòa nhà được phân công
if ($staffAssigned !== null) {
    if (empty($staffAssigned)) {
        $whereConditions[] = '1=0';
    } else {
        $inPh = implode(',', array_fill(0, count($staffAssigned), '?'));
        $whereConditions[] = "kt.MaKhach IN (
            SELECT DISTINCT h_sub.MaKhach 
            FROM HopDong h_sub 
            JOIN CanHo ch_sub ON h_sub.MaCanHo = ch_sub.MaCanHo 
            WHERE ch_sub.DiaChi IN ($inPh)
        )";
        foreach ($staffAssigned as $ab) {
            $params[] = $ab;
        }
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// --- 4. ĐẾM TỔNG SỐ DÒNG CHO PHÂN TRANG ---
$countSql = "
    SELECT COUNT(DISTINCT kt.MaKhach) 
    FROM KhachThue kt 
    LEFT JOIN HopDong active_hp ON kt.MaKhach = active_hp.MaKhach AND active_hp.TrangThai = 'Đang hiệu lực'
    LEFT JOIN CanHo ch ON active_hp.MaCanHo = ch.MaCanHo
    $whereClause
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalMatching = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalMatching / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// --- 5. QUERY LẤY DANH SÁCH KHÁCH THUÊ ---
$sql = "SELECT kt.*, 
               ch.SoPhong, 
               ch.DiaChi AS DiaChiCanHo,
               active_hp.MaHopDong AS ActiveMaHopDong,
               active_hp.NgayBatDau AS NgayBatDauThue,
               (SELECT COUNT(*) FROM HopDong h1 WHERE h1.MaKhach = kt.MaKhach AND h1.TrangThai = 'Đang hiệu lực') AS ActiveCount
        FROM KhachThue kt
        LEFT JOIN HopDong active_hp ON kt.MaKhach = active_hp.MaKhach AND active_hp.TrangThai = 'Đang hiệu lực'
        LEFT JOIN CanHo ch ON active_hp.MaCanHo = ch.MaCanHo
        $whereClause
        ORDER BY kt.MaKhach DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();
?>

<!-- ========================================================================
     HEADER & NÚT TÁC NGHIỆP
     ======================================================================== -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; color: #0f172a; margin: 0;">
            Quản Lý Khách Thuê
        </h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 0.9rem; padding: 0.65rem 1.25rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
            <?= svgIcon('user-plus', '', 18) ?>
            <span>Thêm Khách Thuê Mới</span>
        </a>
    </div>
</div>

<!-- ========================================================================
     4 THẺ THỐNG KÊ KPI KHÁCH THUÊ & CÔNG SUẤT VẬN HÀNH
     ======================================================================== -->
<div class="detail-grid" style="margin-bottom: 1.5rem;">
    <!-- CARD 1: TỔNG KHÁCH THUÊ -->
    <div class="detail-item" style="border-top: 3px solid #2563eb; background: #ffffff;">
        <div class="detail-label">
            <span>TỔNG KHÁCH THUÊ</span>
            <?= svgIcon('users', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #1e293b;">
            <?= $totalTenantCount ?> <span style="font-size: 1rem; font-weight: 600; color: #64748b;">người</span>
        </div>
        <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
            Hồ sơ cư dân đã lưu trữ trong hệ thống
        </div>
    </div>

    <!-- CARD 2: KHÁCH ĐANG THUÊ -->
    <div class="detail-item" style="border-top: 3px solid #10b981; background: #ffffff;">
        <div class="detail-label">
            <span>KHÁCH ĐANG THUÊ PHÒNG</span>
            <?= svgIcon('user-check', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #059669;">
            <?= $rentingTenantCount ?> <span style="font-size: 1rem; font-weight: 600; color: #64748b;">người</span>
        </div>
        <div style="font-size: 0.825rem; color: #047857; margin-top: 0.35rem;">
            Hợp đồng đang trong thời hạn hiệu lực
        </div>
    </div>

    <!-- CARD 3: TỶ LỆ LẤP ĐẦY -->
    <div class="detail-item" style="border-top: 3px solid #0284c7; background: #ffffff;">
        <div class="detail-label">
            <span>CÔNG SUẤT PHÒNG</span>
            <?= svgIcon('building', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #0284c7;">
            <?= $occupancyRate ?>%
        </div>
        <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
            <strong><?= $rentingRoomCount ?></strong> / <?= $totalRoomCount ?> căn hộ đang được thuê
        </div>
    </div>

    <!-- CARD 4: PHÒNG TRỐNG SẴN SÀNG -->
    <div class="detail-item" style="border-top: 3px solid #f59e0b; background: #ffffff;">
        <div class="detail-label">
            <span>PHÒNG TRỐNG SẴN SÀNG</span>
            <?= svgIcon('key', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #d97706;">
            <?= $vacantRoomCount ?> <span style="font-size: 1rem; font-weight: 600; color: #64748b;">căn</span>
        </div>
        <div style="font-size: 0.825rem; color: #b45309; margin-top: 0.35rem;">
            Sẵn sàng đón thêm khách thuê mới
        </div>
    </div>
</div>

<!-- ========================================================================
     BỘ LỌC TÌM KIẾM CAO CẤP
     ======================================================================== -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0;">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; margin: 0;">
        <div style="flex: 2; min-width: 260px;">
            <label for="keyword" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.35rem;">
                <?= svgIcon('search', '', 14) ?> Tìm kiếm khách thuê:
            </label>
            <input type="text" 
                   id="keyword" 
                   name="keyword" 
                   class="form-control" 
                   placeholder="Nhập tên khách, SĐT, CCCD, email, số phòng..." 
                   value="<?= e($keyword) ?>"
                   style="height: 42px; font-size: 0.9rem; border-radius: 8px;">
        </div>

        <div style="flex: 1; min-width: 170px;">
            <label for="status" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.35rem;">
                <?= svgIcon('filter', '', 14) ?> Trạng thái thuê:
            </label>
            <select name="status" id="status" class="form-control" onchange="this.form.submit()" style="height: 42px; font-size: 0.9rem; border-radius: 8px; font-weight: 500;">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Đang thuê" <?= ($statusFilter === 'Đang thuê') ? 'selected' : '' ?>>Đang Thuê Phòng</option>
                <option value="Đã trả phòng" <?= ($statusFilter === 'Đã trả phòng' || $statusFilter === 'Chưa thuê') ? 'selected' : '' ?>>Đã Trả Phòng</option>
            </select>
        </div>

        <div style="flex: 0 0 130px;">
            <label for="per_page" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                Hiển thị:
            </label>
            <select name="per_page" id="per_page" class="form-control" onchange="this.form.submit()" style="height: 42px; font-size: 0.9rem; border-radius: 8px; font-weight: 500;">
                <option value="10" <?= ($perPage === 10) ? 'selected' : '' ?>>10 / trang</option>
                <option value="20" <?= ($perPage === 20) ? 'selected' : '' ?>>20 / trang</option>
                <option value="50" <?= ($perPage === 50) ? 'selected' : '' ?>>50 / trang</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.25rem; font-weight: 600; border-radius: 8px;">
                <?= svgIcon('search', '', 15) ?> Lọc Dữ Liệu
            </button>
            <?php if ($keyword !== '' || $statusFilter !== '' || $perPage !== 10): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="height: 42px; display: inline-flex; align-items: center; border-radius: 8px; font-weight: 500;">
                    Đặt lại
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ========================================================================
     BẢNG DANH SÁCH KHÁCH THUÊ
     ======================================================================== -->
<div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid #e2e8f0;">
    <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
            <?= svgIcon('users', '', 18) ?>
            <span>Danh Sách Hồ Sơ Khách Thuê (<?= $totalMatching ?> khách)</span>
        </h3>
        <span style="font-size: 0.85rem; color: #64748b; font-weight: 500;">
            Trang <?= $page ?> / <?= $totalPages ?>
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tenants)): ?>
            <div class="empty-state" style="padding: 3.5rem 1rem; text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem; opacity: 0.4;">👥</div>
                <h4 style="color: #1e293b; margin-bottom: 0.25rem; font-weight: 700;">Không tìm thấy khách thuê</h4>
                <p style="color: #64748b; font-size: 0.9rem;">
                    Không có hồ sơ khách thuê nào phù hợp với điều kiện tìm kiếm.
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr style="background: #f1f5f9; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em;">
                            <th style="padding: 0.85rem 1rem;">Khách Thuê</th>
                            <th style="padding: 0.85rem 1rem;">Liên Hệ</th>
                            <th style="padding: 0.85rem 1rem;">Giấy Tờ & Cá Nhân</th>
                            <th style="padding: 0.85rem 1rem;">Phòng Thuê</th>
                            <th style="padding: 0.85rem 1rem;">Ngày Bắt Đầu</th>
                            <th style="padding: 0.85rem 1rem;">Trạng Thái</th>
                            <th style="padding: 0.85rem 1rem; text-align: center; width: 140px;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $kt): 
                            $tenantStatus = ((int)$kt['ActiveCount'] > 0) ? 'Đang Thuê Phòng' : 'Đã Trả Phòng';
                            $initials = getInitials($kt['HoTen']);
                            $isFemale = ($kt['GioiTinh'] === 'Nữ');
                        ?>
                            <tr style="vertical-align: middle; border-bottom: 1px solid #f1f5f9;">
                                <!-- CỘT 1: AVATAR & TÊN KHÁCH -->
                                <td style="padding: 0.85rem 1rem;">
                                    <div>
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= $kt['MaKhach'] ?>" 
                                           style="font-weight: 700; color: #0f172a; text-decoration: none; font-size: 0.95rem; display: block; white-space: nowrap;"
                                           onmouseover="this.style.color='#0284c7'"
                                           onmouseout="this.style.color='#0f172a'">
                                            <?= e($kt['HoTen']) ?>
                                        </a>
                                        <span style="font-size: 0.75rem; color: #64748b; font-family: monospace;">
                                            #KT-<?= str_pad((string)$kt['MaKhach'], 4, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- CỘT 2: LIÊN HỆ -->
                                <td style="padding: 0.85rem 1rem;">
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.875rem; display: flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                                        <?= svgIcon('phone', '', 13) ?>
                                        <span><?= e($kt['SoDienThoai']) ?></span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($kt['Email'] ?? '') ?>">
                                        <?= svgIcon('mail', '', 12) ?>
                                        <span><?= e($kt['Email'] ?: 'Chưa cập nhật') ?></span>
                                    </div>
                                </td>

                                <!-- CỘT 3: CCCD & CÁ NHÂN -->
                                <td style="padding: 0.85rem 1rem;">
                                    <div>
                                        <code style="background: #f1f5f9; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.825rem; color: #334155; font-weight: 600; border: 1px solid #e2e8f0;">
                                            <?= e($kt['CCCD']) ?>
                                        </code>
                                    </div>
                                    <div style="font-size: 0.775rem; color: #64748b; margin-top: 0.25rem;">
                                        <span><?= e($kt['GioiTinh'] ?: '-') ?></span> &bull; <span><?= formatDate($kt['NgaySinh']) ?></span>
                                    </div>
                                </td>

                                <!-- CỘT 4: PHÒNG ĐANG THUÊ -->
                                <td style="padding: 0.85rem 1rem;">
                                    <?php if (!empty($kt['SoPhong'])): ?>
                                        <div style="font-weight: 700; font-size: 0.85rem; background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.65rem; border-radius: 6px; border: 1px solid #bae6fd; display: inline-block; white-space: nowrap;">
                                            Phòng <?= e($kt['SoPhong']) ?>
                                        </div>
                                        <?php if (!empty($kt['DiaChiCanHo'])): ?>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($kt['DiaChiCanHo']) ?>">
                                                <?= e($kt['DiaChiCanHo']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.825rem; font-style: italic;">
                                            Chưa nhận phòng
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- CỘT 5: NGÀY BẮT ĐẦU -->
                                <td style="padding: 0.85rem 1rem; font-size: 0.875rem; color: #475569; white-space: nowrap;">
                                    <?= !empty($kt['NgayBatDauThue']) ? formatDate($kt['NgayBatDauThue']) : '-' ?>
                                </td>

                                <!-- CỘT 6: TRẠNG THÁI -->
                                <td style="padding: 0.85rem 1rem; white-space: nowrap;">
                                    <?php if ($tenantStatus === 'Đang Thuê Phòng'): ?>
                                        <span class="badge badge-success" style="font-size: 0.78rem; font-weight: 600; padding: 0.25rem 0.6rem;">
                                            Đang Thuê Phòng
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" style="font-size: 0.78rem; font-weight: 600; padding: 0.25rem 0.6rem; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">
                                            Đã Trả Phòng
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- CỘT 7: THAO TÁC -->
                                <td style="padding: 0.85rem 1rem; text-align: center;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= $kt['MaKhach'] ?>" 
                                           class="btn btn-sm btn-outline" 
                                           style="padding: 0.25rem 0.55rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px;" 
                                           title="Xem hồ sơ chi tiết">
                                            <?= svgIcon('eye', '', 14) ?> Xem
                                        </a>
                                        <a href="<?= $baseUrl ?>/edit.php?id=<?= $kt['MaKhach'] ?>" 
                                           class="btn btn-sm btn-outline" 
                                           style="padding: 0.25rem 0.45rem; font-size: 0.78rem; border-radius: 6px;" 
                                           title="Sửa thông tin khách">
                                            <?= svgIcon('edit', '', 14) ?>
                                        </a>
                                        <a href="<?= $baseUrl ?>/delete.php?id=<?= $kt['MaKhach'] ?>" 
                                           class="btn btn-sm btn-outline" 
                                           style="padding: 0.25rem 0.45rem; font-size: 0.78rem; color: #ef4444; border-color: #fca5a5; border-radius: 6px;" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa hồ sơ khách thuê này không?');" 
                                           title="Xóa khách thuê">
                                            <?= svgIcon('trash', '', 14) ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================
     PHÂN TRANG
     ======================================================================== -->
<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.35rem;">
        <?php 
        $queryParams = $_GET; 
        if ($page > 1): 
            $queryParams['page'] = $page - 1;
        ?>
            <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-sm btn-outline" style="min-width: 36px; text-align: center;">&lt;</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): 
            $queryParams['page'] = $i;
        ?>
            <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-sm <?= ($i === $page) ? 'btn-primary' : 'btn-outline' ?>" style="min-width: 36px; text-align: center; font-weight: 600;">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): 
            $queryParams['page'] = $page + 1;
        ?>
            <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-sm btn-outline" style="min-width: 36px; text-align: center;">&gt;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
