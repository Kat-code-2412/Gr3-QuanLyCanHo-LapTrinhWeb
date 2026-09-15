<?php

declare(strict_types=1);

$title = 'Báo cáo Doanh thu Chi tiết - Quản lý Căn hộ dịch vụ';
require_once __DIR__ . '/../../includes/header.php';
requirePermission('BAOCAO_VIEW');

$pdo = require __DIR__ . '/../../config/database.php';

// 1. Xác định năm xem báo cáo
$currentYear = (int)date('Y');
$year = filter_input(INPUT_GET, 'nam', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 2000, 'max_range' => 2100],
]);
if ($year === false || $year === null) {
    $year = $currentYear;
}

// Lấy danh sách tất cả các năm có dữ liệu hóa đơn đã thanh toán theo phân quyền
$bldCond = buildStaffBuildingCondition('ch.DiaChi');
$stmtYears = $pdo->prepare("
    SELECT DISTINCT RIGHT(hd.KyThanhToan, 4) AS Nam 
    FROM HoaDon hd
    JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    WHERE (hd.TrangThaiThanhToan = 'Đã thanh toán' OR hd.TrangThai = 'Đã TT')
      AND hd.KyThanhToan IS NOT NULL AND hd.KyThanhToan <> ''
      AND {$bldCond['sql']}
    ORDER BY Nam DESC
");
$stmtYears->execute($bldCond['params']);
$yearsRaw = $stmtYears->fetchAll(PDO::FETCH_COLUMN) ?: [];

$availableYearsMap = [];
foreach ($yearsRaw as $yVal) {
    $yInt = (int)$yVal;
    if ($yInt >= 2000 && $yInt <= 2100) {
        $availableYearsMap[$yInt] = true;
    }
}
// Đảm bảo dải năm rộng từ 2015 đến 2035 luôn sẵn sàng chọn
$minYear = 2015;
$maxYear = max(2035, $currentYear + 5);
for ($y = $minYear; $y <= $maxYear; $y++) {
    $availableYearsMap[$y] = true;
}
$availableYearsMap[$year] = true;
$years = array_map('strval', array_keys($availableYearsMap));
rsort($years);

// 2. Lấy dữ liệu tổng hợp từng tháng trong năm đã chọn từ Database
$stmtMonths = $pdo->prepare("
    SELECT 
        hd.KyThanhToan,
        COUNT(*) AS SoHoaDon,
        COALESCE(SUM(hd.TienThue), 0) AS TongTienThue,
        COALESCE(SUM(hd.TienDien), 0) AS TongTienDien,
        COALESCE(SUM(hd.TienNuoc), 0) AS TongTienNuoc,
        COALESCE(SUM(hd.TienDichVu), 0) AS TongTienDichVu,
        COALESCE(SUM(hd.TongTien), 0) AS TongDoanhThu
    FROM HoaDon hd
    JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    WHERE (hd.TrangThaiThanhToan = 'Đã thanh toán' OR hd.TrangThai = 'Đã TT')
      AND RIGHT(hd.KyThanhToan, 4) = ?
      AND {$bldCond['sql']}
    GROUP BY hd.KyThanhToan
    ORDER BY STR_TO_DATE(CONCAT('01/', hd.KyThanhToan), '%d/%m/%Y') ASC
");
$stmtMonths->execute(array_merge([(string)$year], $bldCond['params']));
$dbMonthsData = $stmtMonths->fetchAll(PDO::FETCH_ASSOC);

// Tạo danh sách ĐẦY ĐỦ 12 THÁNG (Tháng 01 -> Tháng 12) cho năm đã chọn
$all12Months = [];
for ($m = 1; $m <= 12; $m++) {
    $kyKey = sprintf('%02d/%04d', $m, $year);
    $all12Months[$kyKey] = [
        'KyThanhToan'   => $kyKey,
        'Thang'         => $m,
        'SoHoaDon'      => 0,
        'TongTienThue'   => 0.0,
        'TongTienDien'   => 0.0,
        'TongTienNuoc'   => 0.0,
        'TongTienDichVu' => 0.0,
        'TongDoanhThu'  => 0.0,
    ];
}

foreach ($dbMonthsData as $row) {
    $kyKey = $row['KyThanhToan'];
    if (isset($all12Months[$kyKey])) {
        $all12Months[$kyKey]['SoHoaDon']      = (int)$row['SoHoaDon'];
        $all12Months[$kyKey]['TongTienThue']   = (float)$row['TongTienThue'];
        $all12Months[$kyKey]['TongTienDien']   = (float)$row['TongTienDien'];
        $all12Months[$kyKey]['TongTienNuoc']   = (float)$row['TongTienNuoc'];
        $all12Months[$kyKey]['TongTienDichVu'] = (float)$row['TongTienDichVu'];
        $all12Months[$kyKey]['TongDoanhThu']  = (float)$row['TongDoanhThu'];
    }
}

// Tổng doanh thu cả năm
$tongDoanhThuNam = 0.0;
$tongTienThueNam = 0.0;
$tongTienDienNam = 0.0;
$tongTienNuocNam = 0.0;
$tongTienDVNam   = 0.0;
$tongHoaDonNam   = 0;

foreach ($all12Months as $m) {
    $tongDoanhThuNam += $m['TongDoanhThu'];
    $tongTienThueNam += $m['TongTienThue'];
    $tongTienDienNam += $m['TongTienDien'];
    $tongTienNuocNam += $m['TongTienNuoc'];
    $tongTienDVNam   += $m['TongTienDichVu'];
    $tongHoaDonNam   += $m['SoHoaDon'];
}

// 3. Xác định kỳ tháng đang chọn xem chi tiết ($selectedKy)
$reqKy = trim((string)($_GET['ky'] ?? ''));

$selectedKy = '';
if ($reqKy !== '' && isset($all12Months[$reqKy])) {
    $selectedKy = $reqKy;
} else {
    // Ưu tiên tháng hiện tại (09/2026) nếu năm chọn trùng năm hiện tại
    $currentKy = date('m/Y');
    if (isset($all12Months[$currentKy])) {
        $selectedKy = $currentKy;
    } else {
        $selectedKy = sprintf('01/%04d', $year);
    }
}

// 4. Lấy dữ liệu chi tiết của tháng được chọn
$selectedMonthSummary = $all12Months[$selectedKy] ?? null;
$invoices = [];

if ($selectedKy !== '') {
    $stmtInvoices = $pdo->prepare("
        SELECT 
            hd.MaHoaDon, hd.KyThanhToan, hd.NgayTao, hd.NgayThanhToan,
            ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach, kt.SoDienThoai,
            hd.TienThue, hd.TienDien, hd.TienNuoc, hd.TienDichVu, hd.TongTien,
            hd.TrangThaiThanhToan, hd.TrangThai
        FROM HoaDon hd
        JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        WHERE (hd.TrangThaiThanhToan = 'Đã thanh toán' OR hd.TrangThai = 'Đã TT')
          AND hd.KyThanhToan = ?
          AND {$bldCond['sql']}
        ORDER BY hd.NgayThanhToan DESC, hd.MaHoaDon DESC
    ");
    $stmtInvoices->execute(array_merge([$selectedKy], $bldCond['params']));
    $invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);
}

// Chuẩn bị dữ liệu cho biểu đồ Chart.js (đủ 12 tháng)
$chartLabels = [];
$chartValues = [];
$chartColors = [];
$chartKyMap  = [];
foreach ($all12Months as $m) {
    $chartLabels[] = 'Tháng ' . sprintf('%02d', $m['Thang']);
    $chartValues[] = (float)$m['TongDoanhThu'];
    $chartKyMap[]  = $m['KyThanhToan'];
    if ($m['KyThanhToan'] === $selectedKy) {
        $chartColors[] = '#0284c7'; // Nổi bật tháng đang xem
    } elseif ((float)$m['TongDoanhThu'] > 0) {
        $chartColors[] = '#38bdf8'; // Tháng có doanh thu
    } else {
        $chartColors[] = '#cbd5e1'; // Tháng chưa có doanh thu
    }
}
?>

<style>
/* Style giao diện Báo cáo Doanh thu Chuyên nghiệp, Tinh tế */
.rev-dashboard-container {
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #1e293b;
}

/* Card tổng quan đầu trang */
.rev-overview-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.35rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.rev-overview-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
    padding-bottom: 1.15rem;
    border-bottom: 1px solid #f1f5f9;
}

.rev-year-form {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin: 0;
}

.rev-year-select {
    padding: 0.45rem 0.85rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.rev-year-select:focus {
    background: #fff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.rev-top-stats {
    display: flex;
    align-items: center;
    gap: 1.75rem;
    flex-wrap: wrap;
}

.rev-stat-item {
    display: flex;
    flex-direction: column;
}

.rev-stat-item .stat-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 2px;
}

.rev-stat-item .stat-num {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}

.rev-stat-item .stat-sub {
    font-size: 0.775rem;
    color: #94a3b8;
    margin-top: 2px;
}

/* Thanh timeline 12 tháng dạng Strip mỏng, tinh tế */
.rev-timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.15rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.rev-timeline-title {
    font-size: 0.825rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.rev-timeline-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 0.45rem;
}

@media (max-width: 1200px) {
    .rev-timeline-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
@media (max-width: 640px) {
    .rev-timeline-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.rev-month-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.6rem 0.35rem;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.18s ease;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    cursor: pointer;
    text-align: center;
    position: relative;
}

.rev-month-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.rev-month-btn .m-name {
    font-size: 0.8rem;
    font-weight: 700;
    line-height: 1.2;
}

.rev-month-btn .m-val {
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 3px;
    line-height: 1.2;
    white-space: nowrap;
}

/* 3 Trạng thái cho từng tháng: */
/* 1. Tháng chưa có doanh thu: Nhẹ nhàng, không gây rối mắt */
.rev-month-btn.is-empty {
    background: #f8fafc;
    border-color: #f1f5f9;
}
.rev-month-btn.is-empty .m-name {
    color: #64748b;
}
.rev-month-btn.is-empty .m-val {
    color: #cbd5e1;
}
.rev-month-btn.is-empty:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.rev-month-btn.is-empty:hover .m-val {
    color: #94a3b8;
}

/* 2. Tháng có doanh thu: Nổi bật nhẹ với màu xanh dịu */
.rev-month-btn.has-rev {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.rev-month-btn.has-rev .m-name {
    color: #15803d;
}
.rev-month-btn.has-rev .m-val {
    color: #16a34a;
    font-weight: 700;
}
.rev-month-btn.has-rev:hover {
    background: #dcfce7;
    border-color: #86efac;
}

/* 3. Tháng đang chọn xem (Active): Màu xanh Primary công nghệ sang trọng */
.rev-month-btn.is-active {
    background: #2563eb !important;
    border-color: #2563eb !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28) !important;
    transform: translateY(-2px);
}
.rev-month-btn.is-active .m-name {
    color: #ffffff !important;
}
.rev-month-btn.is-active .m-val {
    color: #eff6ff !important;
    font-weight: 700;
}

/* 4 Thẻ KPI Bóc Tách Cơ Cấu */
.rev-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.15rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
    .rev-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .rev-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.rev-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.35rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s ease;
}

.rev-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
}

.rev-kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.85rem;
}

.rev-kpi-label {
    font-size: 0.775rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.rev-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.rev-kpi-value {
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.rev-kpi-desc {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.45rem;
    line-height: 1.4;
}

.rev-kpi-bar-track {
    width: 100%;
    height: 5px;
    background: #f1f5f9;
    border-radius: 9999px;
    margin-top: 0.65rem;
    overflow: hidden;
}

.rev-kpi-bar-fill {
    height: 100%;
    border-radius: 9999px;
}
</style>

<div class="rev-dashboard-container">
    <!-- ========================================================================
         HEADER & NÚT XUẤT BÁO CÁO
         ======================================================================== -->
    <div class="page-header" style="margin-bottom: 1.25rem;">
        <div>
            <h1 class="page-title" style="display: flex; align-items: center; gap: 0.5rem; margin: 0; font-weight: 800;">
                <?= svgIcon('chart', '', 24) ?> Báo Cáo Doanh Thu Thực Thu
            </h1>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php if ($selectedKy !== '' && !empty($invoices)): ?>
                <a href="<?= url('/admin/bao-cao/export-excel.php?type=doanh_thu&ky=' . urlencode($selectedKy)) ?>" 
                   class="btn btn-outline" 
                   style="border-color: #16a34a; color: #15803d; background-color: #f0fdf4; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; border-radius: 8px;"
                   title="Tải về file Excel chi tiết từng hóa đơn của tháng <?= e($selectedKy) ?>">
                    <?= svgIcon('download', '', 15) ?> Xuất Excel Tháng <?= e($selectedKy) ?>
                </a>
            <?php endif; ?>
            <a href="<?= url('/admin/bao-cao/export-excel.php?type=doanh_thu&nam=' . $year) ?>" 
               class="btn btn-outline" 
               style="border-color: #0284c7; color: #0369a1; background-color: #f0f9ff; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; border-radius: 8px;"
               title="Tải về file Excel tổng hợp doanh thu 12 tháng năm <?= $year ?>">
                <?= svgIcon('download', '', 15) ?> Xuất Excel Cả Năm <?= $year ?>
            </a>
        </div>
    </div>

    <!-- ========================================================================
         BỘ LỌC NĂM & THANH 12 THÁNG TINH TẾ (EXECUTIVE FINANCIAL STRIP)
         ======================================================================== -->
    <div class="rev-overview-card">
        <!-- Hàng 1: Bộ chọn năm & Chỉ số tài chính cốt lõi -->
        <div class="rev-overview-top">
            <!-- Chọn Năm -->
            <form method="get" class="rev-year-form">
                <label for="nam" style="font-weight: 700; color: #1e293b; font-size: 0.9rem; margin: 0;">Năm báo cáo:</label>
                <select name="nam" id="nam" class="rev-year-select" onchange="this.form.submit()">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= e($y) ?>" <?= ((int)$y === $year) ? 'selected' : '' ?>>Năm <?= e($y) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedKy !== ''): ?>
                    <input type="hidden" name="ky" value="<?= e($selectedKy) ?>">
                <?php endif; ?>
            </form>

            <!-- Chỉ số tổng kết -->
            <div class="rev-top-stats">
                <div class="rev-stat-item">
                    <span class="stat-title">Doanh thu cả năm <?= $year ?></span>
                    <span class="stat-num" style="color: #0f172a;"><?= formatMoney($tongDoanhThuNam) ?></span>
                    <span class="stat-sub"><?= $tongHoaDonNam ?> hóa đơn đã thanh toán</span>
                </div>

                <div style="width: 1px; height: 36px; background: #e2e8f0;"></div>

                <div class="rev-stat-item">
                    <span class="stat-title">Thực thu Tháng <?= e($selectedKy) ?></span>
                    <span class="stat-num" style="color: #2563eb;">
                        <?= formatMoney($selectedMonthSummary ? (float)$selectedMonthSummary['TongDoanhThu'] : 0) ?>
                    </span>
                    <span class="stat-sub"><?= (int)($selectedMonthSummary['SoHoaDon'] ?? 0) ?> hóa đơn tháng này</span>
                </div>
            </div>
        </div>

        <!-- Hàng 2: Thanh 12 tháng gọn gàng, trực quan -->
        <div>
            <div class="rev-timeline-header">
                <span class="rev-timeline-title">
                    <?= svgIcon('calendar', '', 15) ?>
                    <span>Doanh Thu 12 Tháng Năm <?= $year ?></span>
                </span>
                <span style="font-size: 0.775rem; color: #64748b;">
                    Bấm vào tháng bất kỳ để xem bóc tách cơ cấu & hóa đơn chi tiết
                </span>
            </div>

            <div class="rev-timeline-grid">
                <?php foreach ($all12Months as $m): 
                    $kyVal = $m['KyThanhToan'];
                    $isSelected = ($kyVal === $selectedKy);
                    $hasRevenue = ((float)$m['TongDoanhThu'] > 0);
                    $stateClass = $isSelected ? 'is-active' : ($hasRevenue ? 'has-rev' : 'is-empty');
                    $valText = $hasRevenue ? formatMoney((float)$m['TongDoanhThu']) : '-';
                ?>
                    <a href="?nam=<?= $year ?>&ky=<?= urlencode($kyVal) ?>" 
                       class="rev-month-btn <?= $stateClass ?>"
                       title="Tháng <?= sprintf('%02d', $m['Thang']) ?>/<?= $year ?>: <?= $hasRevenue ? formatMoney((float)$m['TongDoanhThu']) . ' (' . $m['SoHoaDon'] . ' HĐ)' : 'Chưa có doanh thu' ?>">
                        <span class="m-name">Thg <?= sprintf('%02d', $m['Thang']) ?></span>
                        <span class="m-val"><?= $valText ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($selectedMonthSummary !== null): ?>
        <!-- ========================================================================
             4 THẺ KPI BÓC TÁCH CƠ CẤU DOANH THU THÁNG ĐƯỢC CHỌN
             ======================================================================== -->
        <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <?= svgIcon('invoice', '', 18) ?> 
                    <span>Bóc Tách Cơ Cấu Doanh Thu Tháng <?= e($selectedKy) ?></span>
                </h2>
                <span style="font-size: 0.8rem; font-weight: 600; color: #16a34a; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 3px 10px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px;">
                    <?= svgIcon('check', '', 13) ?> <?= (int)$selectedMonthSummary['SoHoaDon'] ?> hóa đơn đã thanh toán hoàn tất
                </span>
            </div>

            <?php 
                $tongThang = (float)$selectedMonthSummary['TongDoanhThu'];
                $pctThue = ($tongThang > 0) ? round(((float)$selectedMonthSummary['TongTienThue'] / $tongThang) * 100, 1) : 0;
                $pctDien = ($tongThang > 0) ? round(((float)$selectedMonthSummary['TongTienDien'] / $tongThang) * 100, 1) : 0;
                $tongNuocDV = (float)$selectedMonthSummary['TongTienNuoc'] + (float)$selectedMonthSummary['TongTienDichVu'];
                $pctNuocDV = ($tongThang > 0) ? round(($tongNuocDV / $tongThang) * 100, 1) : 0;
            ?>

            <div class="rev-kpi-grid">
                <!-- 1. TỔNG THỰC THU -->
                <div class="rev-kpi-card">
                    <div>
                        <div class="rev-kpi-header">
                            <span class="rev-kpi-label">Tổng Thực Thu Kỳ <?= e($selectedKy) ?></span>
                            <div class="rev-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                                <?= svgIcon('trend-up', '', 18) ?>
                            </div>
                        </div>
                        <div class="rev-kpi-value" style="color: #2563eb;">
                            <?= formatMoney($tongThang) ?>
                        </div>
                        <div class="rev-kpi-desc">
                            100% hóa đơn đã vào tiền thực tế
                        </div>
                    </div>
                    <div class="rev-kpi-bar-track">
                        <div class="rev-kpi-bar-fill" style="width: 100%; background: #2563eb;"></div>
                    </div>
                </div>

                <!-- 2. TIỀN PHÒNG -->
                <div class="rev-kpi-card">
                    <div>
                        <div class="rev-kpi-header">
                            <span class="rev-kpi-label">Tiền Thuê Căn Hộ</span>
                            <div class="rev-kpi-icon" style="background: #f0fdf4; color: #16a34a;">
                                <?= svgIcon('building', '', 18) ?>
                            </div>
                        </div>
                        <div class="rev-kpi-value" style="color: #15803d;">
                            <?= formatMoney((float)$selectedMonthSummary['TongTienThue']) ?>
                        </div>
                        <div class="rev-kpi-desc">
                            Chiếm <strong><?= $pctThue ?>%</strong> trên tổng doanh thu
                        </div>
                    </div>
                    <div class="rev-kpi-bar-track">
                        <div class="rev-kpi-bar-fill" style="width: <?= min(100, $pctThue) ?>%; background: #16a34a;"></div>
                    </div>
                </div>

                <!-- 3. TIỀN ĐIỆN -->
                <div class="rev-kpi-card">
                    <div>
                        <div class="rev-kpi-header">
                            <span class="rev-kpi-label">Tiền Điện Sinh Hoạt</span>
                            <div class="rev-kpi-icon" style="background: #fffbeb; color: #d97706;">
                                <?= svgIcon('electric', '', 18) ?>
                            </div>
                        </div>
                        <div class="rev-kpi-value" style="color: #b45309;">
                            <?= formatMoney((float)$selectedMonthSummary['TongTienDien']) ?>
                        </div>
                        <div class="rev-kpi-desc">
                            Chiếm <strong><?= $pctDien ?>%</strong> • Theo công tơ điện
                        </div>
                    </div>
                    <div class="rev-kpi-bar-track">
                        <div class="rev-kpi-bar-fill" style="width: <?= min(100, $pctDien) ?>%; background: #d97706;"></div>
                    </div>
                </div>

                <!-- 4. TIỀN NƯỚC & DỊCH VỤ -->
                <div class="rev-kpi-card">
                    <div>
                        <div class="rev-kpi-header">
                            <span class="rev-kpi-label">Nước & Các Dịch Vụ Khác</span>
                            <div class="rev-kpi-icon" style="background: #faf5ff; color: #9333ea;">
                                <?= svgIcon('water', '', 18) ?>
                            </div>
                        </div>
                        <div class="rev-kpi-value" style="color: #7e22ce;">
                            <?= formatMoney($tongNuocDV) ?>
                        </div>
                        <div class="rev-kpi-desc">
                            Nước: <?= formatMoney((float)$selectedMonthSummary['TongTienNuoc']) ?> • DV: <?= formatMoney((float)$selectedMonthSummary['TongTienDichVu']) ?>
                        </div>
                    </div>
                    <div class="rev-kpi-bar-track">
                        <div class="rev-kpi-bar-fill" style="width: <?= min(100, $pctNuocDV) ?>%; background: #9333ea;"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<!-- ========================================================================
     BIỂU ĐỒ SO SÁNH DOANH THU CÁC THÁNG
     ======================================================================== -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="display: flex; align-items: center; gap: 0.5rem; margin: 0; font-size: 1.05rem;">
            <?= svgIcon('chart', '', 18) ?>
            <span>Biểu Đồ Doanh Thu Toàn Bộ Năm <?= $year ?></span>
        </h3>
        <span style="font-size: 0.8rem; color: #64748b;">Đơn vị: VNĐ</span>
    </div>
    <div class="card-body">
        <?php if (empty($monthsData)): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                Chưa có dữ liệu doanh thu trong năm <?= $year ?>.
            </p>
        <?php else: ?>
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="chartDoanhThuNam"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================
     BẢNG CHI TIẾT TỪNG HÓA ĐƠN CỦA THÁNG ĐÃ CHỌN
     ======================================================================== -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h3 style="display: flex; align-items: center; gap: 0.5rem; margin: 0; font-size: 1.05rem;">
                <?= svgIcon('invoice', '', 18) ?>
                <span>Danh Sách Hóa Đơn Đã Thu Tiền <?= $selectedKy !== '' ? ' - Tháng ' . e($selectedKy) : '' ?></span>
            </h3>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <!-- Tìm kiếm nhanh tại bảng -->
            <input type="text" id="tableFilterInput" class="form-control" placeholder="Tìm theo phòng, tên khách, SĐT..." style="width: 250px; font-size: 0.85rem;" onkeyup="filterInvoiceTable()">
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($invoices)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <?= svgIcon('info', '', 36) ?>
                <p style="margin-top: 0.75rem; font-size: 0.95rem;">
                    <?= $selectedKy !== '' ? 'Không có hóa đơn đã thanh toán nào trong tháng ' . e($selectedKy) : 'Vui lòng chọn một tháng để xem chi tiết.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="invoiceDetailTable" style="margin: 0; width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem; color: #475569;">
                            <th style="padding: 0.75rem 1rem; text-align: center; width: 50px;">STT</th>
                            <th style="padding: 0.75rem 1rem;">Mã HĐ</th>
                            <th style="padding: 0.75rem 1rem;">Căn Hộ / Phòng</th>
                            <th style="padding: 0.75rem 1rem;">Khách Thuê</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Tiền Phòng</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Tiền Điện</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Tiền Nước</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Dịch Vụ</th>
                            <th style="padding: 0.75rem 1rem; text-align: right; color: #0284c7; font-weight: 700;">Tổng Thực Thu</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Ngày TT</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Trạng Thái</th>
                            <th style="padding: 0.75rem 1rem; text-align: center; width: 90px;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stt = 1;
                        $subThue = 0.0;
                        $subDien = 0.0;
                        $subNuoc = 0.0;
                        $subDV   = 0.0;
                        $subTong = 0.0;

                        foreach ($invoices as $inv): 
                            $tienThue = (float)$inv['TienThue'];
                            $tienDien = (float)$inv['TienDien'];
                            $tienNuoc = (float)$inv['TienNuoc'];
                            $tienDV   = (float)$inv['TienDichVu'];
                            $tongTien = (float)$inv['TongTien'];

                            $subThue += $tienThue;
                            $subDien += $tienDien;
                            $subNuoc += $tienNuoc;
                            $subDV   += $tienDV;
                            $subTong += $tongTien;

                            $ngayTT = !empty($inv['NgayThanhToan']) ? date('d/m/Y H:i', strtotime($inv['NgayThanhToan'])) : '-';
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; font-size: 0.9rem;">
                                <td style="padding: 0.75rem 1rem; text-align: center; color: var(--text-muted); font-weight: 500;">
                                    <?= $stt++ ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; font-weight: 600;">
                                    <a href="<?= url('/admin/phieu-in/hoa-don.php?id=' . (int)$inv['MaHoaDon']) ?>" target="_blank" style="color: #0284c7; text-decoration: none;" title="Xem & In phiếu thu">
                                        #HD-<?= str_pad((string)$inv['MaHoaDon'], 4, '0', STR_PAD_LEFT) ?>
                                    </a>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <div style="font-weight: 700; color: #1e293b;">Phòng <?= e($inv['SoPhong']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($inv['DiaChi']) ?>">
                                        <?= e($inv['DiaChi']) ?>
                                    </div>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <div style="font-weight: 600; color: #334155;"><?= e($inv['TenKhach']) ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;"><?= e($inv['SoDienThoai']) ?></div>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 500;">
                                    <?= formatMoney($tienThue) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; color: #d97706; font-weight: 500;">
                                    <?= formatMoney($tienDien) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; color: #0284c7; font-weight: 500;">
                                    <?= formatMoney($tienNuoc) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; color: #7c3aed; font-weight: 500;">
                                    <?= formatMoney($tienDV) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #0284c7; font-size: 0.95rem;">
                                    <?= formatMoney($tongTien) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: center; font-size: 0.8rem; color: #475569;">
                                    <?= e($ngayTT) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <span class="badge badge-success" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">
                                        Đã thanh toán
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <a href="<?= url('/admin/phieu-in/hoa-don.php?id=' . (int)$inv['MaHoaDon']) ?>" target="_blank" class="btn btn-sm btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-color: #cbd5e1;" title="In phiếu thu / hóa đơn">
                                        <?= svgIcon('invoice', '', 13) ?> In
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background: #f8fafc; border-top: 2px solid #cbd5e1; font-weight: 700;">
                        <tr>
                            <td colspan="4" style="padding: 0.85rem 1rem; text-align: right; text-transform: uppercase; color: #1e293b;">
                                Tổng cộng tháng <?= e($selectedKy) ?>:
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; color: #10b981;">
                                <?= formatMoney($subThue) ?>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; color: #d97706;">
                                <?= formatMoney($subDien) ?>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; color: #0284c7;">
                                <?= formatMoney($subNuoc) ?>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; color: #7c3aed;">
                                <?= formatMoney($subDV) ?>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align: right; color: #0284c7; font-size: 1.05rem;">
                                <?= formatMoney($subTong) ?>
                            </td>
                            <td colspan="3" style="padding: 0.85rem 1rem; text-align: center; color: #16a34a; font-size: 0.85rem;">
                                (<?= count($invoices) ?> hóa đơn)
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</div> <!-- .rev-dashboard-container -->

<!-- ========================================================================
     JAVASCRIPT: BIỂU ĐỒ & BỘ LỌC BẢNG NHANH
     ======================================================================== -->
<script>
window.addEventListener('load', function () {
    // 1. Khởi tạo biểu đồ doanh thu theo 12 tháng của năm
    try {
        const canvasEl = document.getElementById('chartDoanhThuNam');
        if (canvasEl) {
            const ctx = canvasEl.getContext('2d');
            const chartLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
            const chartValues = <?= json_encode($chartValues) ?>;
            const chartColors = <?= json_encode($chartColors) ?>;
            const chartKyMap  = <?= json_encode($chartKyMap, JSON_UNESCAPED_UNICODE) ?>;
            const currentYear = <?= (int)$year ?>;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: chartValues,
                        backgroundColor: chartColors,
                        borderRadius: 6,
                        maxBarThickness: 45
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(context.raw) + ' đ';
                                },
                                afterLabel: function(context) {
                                    return (context.raw > 0) ? ' (Nhấn vào cột để xem chi tiết tháng)' : ' (Chưa phát sinh doanh thu)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(val) {
                                    return (val / 1000000).toLocaleString('vi-VN') + ' tr';
                                }
                            }
                        }
                    },
                    // Khi bấm vào cột tháng thì tự động chuyển sang xem chi tiết tháng đó!
                    onClick: function(evt, elements) {
                        if (elements && elements.length > 0) {
                            const index = elements[0].index;
                            const kyVal = chartKyMap[index]; // e.g. "09/2026"
                            window.location.href = '?nam=' + currentYear + '&ky=' + encodeURIComponent(kyVal);
                        }
                    },
                    onHover: function(evt, elements) {
                        evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                    }
                }
            });
        }
    } catch (e) {
        console.error('Lỗi khi vẽ biểu đồ doanh thu:', e);
    }
});

// 2. Tìm kiếm nhanh khách/phòng trong bảng hóa đơn
function filterInvoiceTable() {
    const input = document.getElementById('tableFilterInput');
    const filter = input.value.toLowerCase().trim();
    const table = document.getElementById('invoiceDetailTable');
    if (!table) return;

    const tr = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    for (let i = 0; i < tr.length; i++) {
        const text = tr[i].textContent || tr[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            tr[i].style.display = '';
        } else {
            tr[i].style.display = 'none';
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>