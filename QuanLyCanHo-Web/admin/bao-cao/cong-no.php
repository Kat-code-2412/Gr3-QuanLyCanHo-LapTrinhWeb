<?php

declare(strict_types=1);

$title = 'Báo Cáo Công Nợ - Hệ Thống Căn Hộ Dịch Vụ';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('BAOCAO_VIEW');

$pdo = require __DIR__ . '/../../config/database.php';
markOverdueInvoices($pdo);

// Kỳ thanh toán mặc định là tháng hiện tại (MM/YYYY)
$curKyMM = date('m/Y');
$selectedKy = trim((string)($_GET['ky'] ?? $curKyMM));
$searchKeyword = trim((string)($_GET['q'] ?? ''));

// 1. LẤY DANH SÁCH CÁC KỲ CÓ HÓA ĐƠN THEO PHÂN QUYỀN
$bldCond = buildStaffBuildingCondition('ch.DiaChi');
$stmtAllKy = $pdo->prepare("
    SELECT 
        hd.KyThanhToan,
        COUNT(*) AS TongSoHD,
        SUM(CASE WHEN hd.TrangThaiThanhToan <> 'Đã thanh toán' AND hd.TrangThai <> 'Đã TT' THEN 1 ELSE 0 END) AS SoHDChuaTT,
        COALESCE(SUM(CASE WHEN hd.TrangThaiThanhToan <> 'Đã thanh toán' AND hd.TrangThai <> 'Đã TT' THEN hd.TongTien ELSE 0 END), 0) AS TongTienNo,
        COALESCE(SUM(CASE WHEN hd.TrangThaiThanhToan = 'Đã thanh toán' OR hd.TrangThai = 'Đã TT' THEN hd.TongTien ELSE 0 END), 0) AS TongTienDaThu
    FROM HoaDon hd
    JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    WHERE {$bldCond['sql']}
    GROUP BY hd.KyThanhToan
    ORDER BY STR_TO_DATE(CONCAT('01/', hd.KyThanhToan), '%d/%m/%Y') DESC
");
$stmtAllKy->execute($bldCond['params']);
$allKyList = $stmtAllKy->fetchAll(PDO::FETCH_ASSOC);

// 2. THỐNG KÊ TỔNG QUAN THEO KỲ ĐANG CHỌN
$kySummary = [
    'TongHD'       => 0,
    'SoHDChuaTT'   => 0,
    'TongTienNo'   => 0.0,
    'TongTienThu'  => 0.0,
    'TongPhatSinh' => 0.0,
    'TyLeThuHoi'   => 0.0,
];

if ($selectedKy === 'all') {
    // Thống kê toàn bộ các kỳ
    foreach ($allKyList as $kItem) {
        $kySummary['TongHD']       += (int)$kItem['TongSoHD'];
        $kySummary['SoHDChuaTT']   += (int)$kItem['SoHDChuaTT'];
        $kySummary['TongTienNo']   += (float)$kItem['TongTienNo'];
        $kySummary['TongTienThu']  += (float)$kItem['TongTienDaThu'];
    }
} else {
    // Thống kê riêng cho kỳ đang chọn
    foreach ($allKyList as $kItem) {
        if ($kItem['KyThanhToan'] === $selectedKy) {
            $kySummary['TongHD']      = (int)$kItem['TongSoHD'];
            $kySummary['SoHDChuaTT']  = (int)$kItem['SoHDChuaTT'];
            $kySummary['TongTienNo']  = (float)$kItem['TongTienNo'];
            $kySummary['TongTienThu'] = (float)$kItem['TongTienDaThu'];
            break;
        }
    }
}
$kySummary['TongPhatSinh'] = $kySummary['TongTienNo'] + $kySummary['TongTienThu'];
if ($kySummary['TongPhatSinh'] > 0) {
    $kySummary['TyLeThuHoi'] = round(($kySummary['TongTienThu'] / $kySummary['TongPhatSinh']) * 100, 1);
}

// 3. TÍNH NỢ CŨ TỒN ĐỌNG (Các kỳ trước kỳ hiện tại mà chưa thu)
$stmtPastDebt = $pdo->prepare("
    SELECT COUNT(*) AS Cnt, COALESCE(SUM(hd.TongTien), 0) AS Total 
    FROM HoaDon hd
    JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    WHERE STR_TO_DATE(CONCAT('01/', hd.KyThanhToan), '%d/%m/%Y') < STR_TO_DATE(CONCAT('01/', ?), '%d/%m/%Y')
      AND (hd.TrangThaiThanhToan <> 'Đã thanh toán' AND hd.TrangThai <> 'Đã TT')
      AND {$bldCond['sql']}
");
$stmtPastDebt->execute(array_merge([$curKyMM], $bldCond['params']));
$pastDebtData = $stmtPastDebt->fetch(PDO::FETCH_ASSOC);
$tongNoTonDong = (float)($pastDebtData['Total'] ?? 0);
$soHDPastTon   = (int)($pastDebtData['Cnt'] ?? 0);

// 4. TRUY VẤN DANH SÁCH HÓA ĐƠN CÔNG NỢ THEO BỘ LỌC
$whereClauses = ["(hd.TrangThaiThanhToan <> 'Đã thanh toán' AND hd.TrangThai <> 'Đã TT')"];
$params = [];

if ($selectedKy !== 'all') {
    $whereClauses[] = "hd.KyThanhToan = ?";
    $params[] = $selectedKy;
}

if ($searchKeyword !== '') {
    $whereClauses[] = "(ch.SoPhong LIKE ? OR kt.HoTen LIKE ? OR kt.SoDienThoai LIKE ?)";
    $kwParam = '%' . $searchKeyword . '%';
    $params[] = $kwParam;
    $params[] = $kwParam;
    $params[] = $kwParam;
}

if ($bldCond['sql'] !== '1=1') {
    $whereClauses[] = $bldCond['sql'];
    foreach ($bldCond['params'] as $bp) {
        $params[] = $bp;
    }
}

$whereSql = implode(' AND ', $whereClauses);

// Phân trang
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM HoaDon hd
    JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
    WHERE $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$querySql = "
    SELECT 
        hd.MaHoaDon, hd.KyThanhToan, hd.NgayTao, hd.TrangThaiThanhToan, hd.TrangThai,
        hd.TienThue, hd.TienDien, hd.TienNuoc, hd.TienDichVu, hd.TongTien,
        DATEDIFF(CURRENT_DATE(), hd.NgayTao) AS SoNgayQuaHan,
        ch.SoPhong, ch.DiaChi,
        kt.HoTen AS TenKhachThue, kt.SoDienThoai
    FROM HoaDon hd
    JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
    WHERE $whereSql
    ORDER BY STR_TO_DATE(CONCAT('01/', hd.KyThanhToan), '%d/%m/%Y') DESC, hd.TongTien DESC
    LIMIT $perPage OFFSET $offset
";
$dataStmt = $pdo->prepare($querySql);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ========================================================================
     HEADER & BỘ LỌC KỲ THANH TOÁN
     ======================================================================== -->
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div>
        <h1 class="page-title" style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; color: #0f172a; margin: 0;">
            Báo Cáo Công Nợ
        </h1>
    </div>
    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
        <a href="<?= url('/admin/bao-cao/export-excel.php?type=cong_no&ky=' . urlencode($selectedKy)) ?>" 
           class="btn btn-outline" 
           style="border-color: #ef4444; color: #dc2626; background: #fef2f2; font-weight: 600;" 
           title="Xuất danh sách công nợ ra file Excel">
            <?= svgIcon('download', '', 16) ?> <span>Xuất Excel Công Nợ</span>
        </a>
        <a href="<?= url('/admin/bao-cao/doanh-thu.php') ?>" 
           class="btn btn-outline" 
           style="font-weight: 600;">
            <?= svgIcon('chart', '', 16) ?> <span>Báo Cáo Doanh Thu</span>
        </a>
    </div>
</div>

<!-- ========================================================================
     KPI CARDS THEO KỲ THANH TOÁN ĐANG CHỌN
     ======================================================================== -->
<div class="detail-grid" style="margin-bottom: 1.5rem;">
    <!-- CARD 1: CÔNG NỢ KỲ CHỌN -->
    <div class="detail-item" style="border-top: 3px solid #ef4444; background: #fff;">
        <div class="detail-label">
            <span>CÔNG NỢ <?= ($selectedKy === 'all') ? 'TẤT CẢ CÁC KỲ' : 'THÁNG ' . e($selectedKy) ?></span>
            <?= svgIcon('trend-down', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #dc2626;">
            <?= formatMoney($kySummary['TongTienNo']) ?>
        </div>
        <div style="font-size: 0.825rem; color: #b91c1c; margin-top: 0.35rem;">
            <strong><?= $kySummary['SoHDChuaTT'] ?></strong> hóa đơn chưa thanh toán
        </div>
    </div>

    <!-- CARD 2: TIỀN ĐÃ THU CỦA KỲ -->
    <div class="detail-item" style="border-top: 3px solid #10b981; background: #fff;">
        <div class="detail-label">
            <span>TIỀN ĐÃ THU THỰC TẾ</span>
            <?= svgIcon('trend-up', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #059669;">
            <?= formatMoney($kySummary['TongTienThu']) ?>
        </div>
        <div style="font-size: 0.825rem; color: #047857; margin-top: 0.35rem;">
            <?= ($kySummary['TongHD'] - $kySummary['SoHDChuaTT']) ?> / <?= $kySummary['TongHD'] ?> hóa đơn đã thanh toán
        </div>
    </div>

    <!-- CARD 3: TỶ LỆ THU HỒI -->
    <div class="detail-item" style="border-top: 3px solid #0284c7; background: #fff;">
        <div class="detail-label">
            <span>TỶ LỆ THU HỒI KỲ NÀY</span>
            <?= svgIcon('pie', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #0284c7;">
            <?= $kySummary['TyLeThuHoi'] ?>%
        </div>
        <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
            Tổng phát sinh: <strong><?= formatMoney($kySummary['TongPhatSinh']) ?></strong>
        </div>
    </div>

    <!-- CARD 4: NỢ TỒN ĐỌNG CÁC KỲ TRƯỚC -->
    <div class="detail-item" style="border-top: 3px solid #f59e0b; background: #fff;">
        <div class="detail-label">
            <span>NỢ CŨ TỒN ĐỌNG KỲ TRƯỚC</span>
            <?= svgIcon('warning', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #d97706;">
            <?= formatMoney($tongNoTonDong) ?>
        </div>
        <div style="font-size: 0.825rem; color: #b45309; margin-top: 0.35rem;">
            <strong><?= $soHDPastTon ?></strong> hóa đơn quá hạn chưa thu hồi
        </div>
    </div>
</div>

<!-- ========================================================================
     BỘ LỌC THEO THÁNG & TÌM KIẾM
     ======================================================================== -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
        <!-- BỘ LỌC CHỌN KỲ THANH TOÁN -->
        <form method="GET" action="" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin: 0;">
            <label style="font-weight: 700; color: #1e293b; font-size: 0.9rem; margin: 0; white-space: nowrap;">
                <?= svgIcon('calendar', '', 16) ?> Chọn Kỳ Báo Cáo:
            </label>
            <select name="ky" class="form-control" style="max-width: 260px; font-weight: 600;" onchange="this.form.submit()">
                <option value="<?= e($curKyMM) ?>" <?= ($selectedKy === $curKyMM) ? 'selected' : '' ?>>
                    Tháng <?= e($curKyMM) ?> (Hiện tại - <?= formatMoney($kySummary['TongTienNo']) ?> nợ)
                </option>
                <?php foreach ($allKyList as $k): ?>
                    <?php if ($k['KyThanhToan'] !== $curKyMM): ?>
                        <option value="<?= e($k['KyThanhToan']) ?>" <?= ($selectedKy === $k['KyThanhToan']) ? 'selected' : '' ?>>
                            Tháng <?= e($k['KyThanhToan']) ?> (<?= $k['SoHDChuaTT'] ?> nợ - <?= formatMoney((float)$k['TongTienNo']) ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
                <option value="all" <?= ($selectedKy === 'all') ? 'selected' : '' ?>>
                    -- Tất cả các kỳ có nợ --
                </option>
            </select>

            <!-- TÌM KIẾM TÊN / PHÒNG -->
            <input type="text" 
                   name="q" 
                   value="<?= e($searchKeyword) ?>" 
                   placeholder="Tìm số phòng, khách thuê, SĐT..." 
                   class="form-control" 
                   style="max-width: 260px;">
            <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                <?= svgIcon('search', '', 16) ?> Lọc Dữ Liệu
            </button>
            <?php if ($searchKeyword !== '' || $selectedKy !== $curKyMM): ?>
                <a href="<?= url('/admin/bao-cao/cong-no.php') ?>" class="btn btn-outline" style="font-size: 0.85rem;">
                    Đặt lại
                </a>
            <?php endif; ?>
        </form>

        <div style="font-size: 0.85rem; color: #64748b; font-weight: 500;">
            Hiển thị <strong><?= count($rows) ?></strong> / <strong><?= $totalRows ?></strong> hóa đơn nợ
        </div>
    </div>
</div>

<!-- ========================================================================
     BẢNG CHI TIẾT CÔNG NỢ CỦA KỲ ĐƯỢC CHỌN
     ======================================================================== -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.25rem;">
        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b;">
            Danh Sách Hóa Đơn Chưa Thanh Toán - <?= ($selectedKy === 'all') ? 'Toàn Bộ Các Kỳ' : 'Kỳ ' . e($selectedKy) ?>
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rows)): ?>
            <div class="empty-state" style="padding: 3rem 1rem; text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem; color: #10b981;">&#10003;</div>
                <h4 style="color: #1e293b; margin-bottom: 0.25rem; font-weight: 700;">Không có công nợ nào!</h4>
                <p style="color: #64748b; font-size: 0.9rem;">
                    <?= ($selectedKy === 'all') ? 'Tất cả các hóa đơn đã được thanh toán đầy đủ.' : 'Toàn bộ hóa đơn của kỳ ' . e($selectedKy) . ' đã được thanh toán hoàn tất.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr style="background: #f1f5f9; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em;">
                            <th>Mã HĐ</th>
                            <th>Kỳ Thu</th>
                            <th>Phòng</th>
                            <th>Khách Thuê</th>
                            <th>Tiền Thuê</th>
                            <th>Điện & Nước</th>
                            <th>Dịch Vụ</th>
                            <th>Tổng Tiền Nợ</th>
                            <th>Số Ngày Quá Hạn</th>
                            <th class="text-center">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php 
                                $soNgay = (int)$row['SoNgayQuaHan'];
                                $isOverdue = $soNgay > 10;
                            ?>
                            <tr style="vertical-align: middle;">
                                <td>
                                    <strong style="color: #0284c7;">
                                        #<?= e((string)$row['MaHoaDon']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 700; border: 1px solid #cbd5e1;">
                                        <?= e($row['KyThanhToan']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.5rem; border-radius: 4px;">
                                        Phòng <?= e($row['SoPhong']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b;"><?= e($row['TenKhachThue']) ?></div>
                                    <small style="color: #64748b;">
                                        <?= svgIcon('phone', '', 12) ?> <?= e($row['SoDienThoai']) ?>
                                    </small>
                                </td>
                                <td><?= formatMoney((float)$row['TienThue']) ?></td>
                                <td>
                                    <span style="font-size: 0.825rem; color: #475569;">
                                        Đ: <?= formatMoney((float)$row['TienDien']) ?><br>
                                        N: <?= formatMoney((float)$row['TienNuoc']) ?>
                                    </span>
                                </td>
                                <td><?= formatMoney((float)$row['TienDichVu']) ?></td>
                                <td>
                                    <strong style="color: #dc2626; font-size: 0.95rem;">
                                        <?= formatMoney((float)$row['TongTien']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($soNgay > 0): ?>
                                        <span class="badge <?= $isOverdue ? 'badge-danger' : 'badge-warning' ?>">
                                            <?= $soNgay ?> ngày
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Mới lập</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= url('/admin/hoa-don/detail.php?id=' . $row['MaHoaDon']) ?>" 
                                       class="btn btn-sm btn-outline" 
                                       style="padding: 0.25rem 0.5rem; font-size: 0.8rem; font-weight: 600;" 
                                       title="Xem chi tiết và xác nhận thanh toán">
                                        <?= svgIcon('eye', '', 14) ?> Xem HĐ
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

<!-- ========================================================================
     PHÂN TRANG
     ======================================================================== -->
<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.35rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?ky=<?= urlencode($selectedKy) ?>&q=<?= urlencode($searchKeyword) ?>&page=<?= $i ?>" 
               class="btn btn-sm <?= ($i === $page) ? 'btn-primary' : 'btn-outline' ?>" 
               style="min-width: 36px; text-align: center;">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>