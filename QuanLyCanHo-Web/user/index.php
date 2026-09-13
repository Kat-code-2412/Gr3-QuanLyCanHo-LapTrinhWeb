<?php

declare(strict_types=1);

$title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../config/database.php';

$staffId = (int)($_SESSION['MaNV'] ?? 0);
$staffStmt = $pdo->prepare('SELECT HoTen FROM NhanVien WHERE MaNV = ?');
$staffStmt->execute([$staffId]);
$currentStaffName = (string)($staffStmt->fetchColumn() ?: ($_SESSION['HoTen'] ?? 'Nhân viên'));
$_SESSION['HoTen'] = $currentStaffName;

// Lấy phạm vi tòa nhà được phân công của nhân viên
$assignedBuildings = getStaffAssignedBuildings($staffId);
$bldCondCanHo = buildStaffBuildingCondition('DiaChi', $staffId);
$bldCondCh = buildStaffBuildingCondition('ch.DiaChi', $staffId);

// Dữ liệu thống kê tác nghiệp của nhân viên (chỉ tính các tòa nhà được phân công)
$stmtRoomsOccupied = $pdo->prepare("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Đang thuê' AND {$bldCondCanHo['sql']}");
$stmtRoomsOccupied->execute($bldCondCanHo['params']);
$countPhongDangThue = (int)$stmtRoomsOccupied->fetchColumn();

$stmtRoomsVacant = $pdo->prepare("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Trống' AND {$bldCondCanHo['sql']}");
$stmtRoomsVacant->execute($bldCondCanHo['params']);
$countPhongTrong = (int)$stmtRoomsVacant->fetchColumn();

// Đếm số khách thuê thuộc các hợp đồng ở tòa nhà được phân công
$stmtTenants = $pdo->prepare("
    SELECT COUNT(DISTINCT hp.MaKhach) 
    FROM HopDong hp 
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo 
    WHERE hp.TrangThai = 'Đang hiệu lực' AND {$bldCondCh['sql']}
");
$stmtTenants->execute($bldCondCh['params']);
$countKhachThue = (int)$stmtTenants->fetchColumn();

// Đếm hợp đồng đang hiệu lực thuộc tòa nhà
$stmtContracts = $pdo->prepare("
    SELECT COUNT(*) 
    FROM HopDong hp 
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo 
    WHERE hp.TrangThai = 'Đang hiệu lực' AND {$bldCondCh['sql']}
");
$stmtContracts->execute($bldCondCh['params']);
$countHopDongActive = (int)$stmtContracts->fetchColumn();

// Đếm yêu cầu bảo trì đang chờ xử lý thuộc tòa nhà
$stmtMaintenance = $pdo->prepare("
    SELECT COUNT(*) 
    FROM YeuCauBaoTri bt 
    JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo 
    WHERE bt.TrangThai <> 'Hoàn thành' AND bt.TrangThai <> 'Từ chối' AND {$bldCondCh['sql']}
");
$stmtMaintenance->execute($bldCondCh['params']);
$countBaoTriPending = (int)$stmtMaintenance->fetchColumn();

// Hợp đồng sắp hết hạn trong 30 ngày (chỉ thuộc tòa nhà được phân công)
$stmtExpiring = $pdo->prepare("
    SELECT hp.*, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach, kt.SoDienThoai,
           DATEDIFF(hp.NgayKetThuc, CURDATE()) AS SoNgayConLai
    FROM HopDong hp
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
    WHERE hp.TrangThai = 'Đang hiệu lực'
      AND hp.NgayKetThuc BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      AND {$bldCondCh['sql']}
    ORDER BY hp.NgayKetThuc ASC LIMIT 5
");
$stmtExpiring->execute($bldCondCh['params']);
$expiringContracts = $stmtExpiring->fetchAll();

// Yêu cầu bảo trì mới cần xử lý (chỉ thuộc tòa nhà được phân công)
$stmtRecentBt = $pdo->prepare("
    SELECT bt.*, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach
    FROM YeuCauBaoTri bt
    JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
    LEFT JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
    WHERE bt.TrangThai <> 'Hoàn thành' AND {$bldCondCh['sql']}
    ORDER BY bt.MaBaoTri DESC LIMIT 5
");
$stmtRecentBt->execute($bldCondCh['params']);
$recentMaintenance = $stmtRecentBt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Tác Nghiệp Nhân Viên</h1>
        <p class="page-subtitle">Xin chào <strong><?= e($currentStaffName) ?></strong>, chúc bạn một ngày làm việc hiệu quả!</p>
    </div>
</div>

<!-- SECTION 2: STATS CARDS -->
<div class="detail-grid">
    <div class="detail-item" style="border-left: 4px solid #2563eb;">
        <div class="detail-label">
            <span>PHÒNG ĐANG THUÊ</span>
            <?= svgIcon('door', '', 16) ?>
        </div>
        <div class="detail-value" style="color: #2563eb;"><?= $countPhongDangThue ?></div>
        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
            <?= $countPhongTrong ?> phòng trống sẵn sàng đón khách
        </div>
    </div>

    <div class="detail-item" style="border-left: 4px solid #10b981;">
        <div class="detail-label">
            <span>HỢP ĐỒNG ĐANG HOẠT ĐỘNG</span>
            <?= svgIcon('contract', '', 16) ?>
        </div>
        <div class="detail-value" style="color: #10b981;"><?= $countHopDongActive ?></div>
        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
            Tổng số khách đang lưu trú: <?= $countKhachThue ?>
        </div>
    </div>

    <div class="detail-item" style="border-left: 4px solid #f59e0b;">
        <div class="detail-label">
            <span>BẢO TRÌ CẦN XỬ LÝ</span>
            <?= svgIcon('tool', '', 16) ?>
        </div>
        <div class="detail-value" style="color: #d97706;"><?= $countBaoTriPending ?></div>
        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
            Yêu cầu sự cố từ các phòng
        </div>
    </div>

    <div class="detail-item" style="border-left: 4px solid #ef4444;">
        <div class="detail-label">
            <span>HỢP ĐỒNG SẮP HẾT HẠN</span>
            <?= svgIcon('alert-triangle', '', 16) ?>
        </div>
        <div class="detail-value" style="color: #ef4444;"><?= count($expiringContracts) ?></div>
        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
            Cần liên hệ gia hạn trong 30 ngày
        </div>
    </div>
</div>

<!-- SECTION 3: TÁC NGHIỆP TRỌNG TÂM -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- CẢNH BÁO HỢP ĐỒNG -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 style="display: flex; align-items: center; gap: 0.5rem;">
                <?= svgIcon('alert-triangle', '', 18) ?>
                <span>Hợp Đồng Sắp Hết Hạn Cần Chăm Sóc</span>
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($expiringContracts)): ?>
                <div style="padding: 2rem; text-align: center; color: #64748b; font-size: 0.875rem;">
                    Không có hợp đồng nào sắp hết hạn trong 30 ngày tới.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Phòng</th>
                                <th>Khách thuê</th>
                                <th>Ngày kết thúc</th>
                                <th>Còn lại</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiringContracts as $hdExp): ?>
                                <tr>
                                    <td><strong><?= e(formatSoPhong($hdExp['SoPhong'])) ?></strong></td>
                                    <td><?= e($hdExp['TenKhach']) ?><br><small style="color: #64748b;"><?= e($hdExp['SoDienThoai']) ?></small></td>
                                    <td><?= formatDate($hdExp['NgayKetThuc']) ?></td>
                                    <td><span class="badge badge-danger"><?= (int)$hdExp['SoNgayConLai'] ?> ngày</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- HÀNG ĐỢI SỰ CỐ BẢO TRÌ -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 style="display: flex; align-items: center; gap: 0.5rem;">
                <?= svgIcon('tool', '', 18) ?>
                <span>Danh Sách Sự Cố Cần Xử Lý</span>
            </h3>
            <a href="<?= url('/admin/bao-tri/index.php') ?>" class="btn btn-sm btn-outline">Xem tất cả &raquo;</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recentMaintenance)): ?>
                <div style="padding: 2rem; text-align: center; color: #64748b; font-size: 0.875rem;">
                    Hiện không có yêu cầu sự cố bảo trì nào đang chờ xử lý.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Phòng</th>
                                <th>Nội dung</th>
                                <th>Tiếp nhận</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMaintenance as $bt): ?>
                                <tr>
                                    <td><strong><?= e(formatSoPhong($bt['SoPhong'])) ?></strong></td>
                                    <td><div style="max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($bt['NoiDung']) ?></div></td>
                                    <td><small><?= formatDate($bt['NgayTiepNhan']) ?></small></td>
                                    <td><?= renderStatusBadge($bt['TrangThai']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
