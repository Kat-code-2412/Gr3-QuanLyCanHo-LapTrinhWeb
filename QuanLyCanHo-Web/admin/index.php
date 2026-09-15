<?php

declare(strict_types=1);

$title = 'Dashboard - Hệ Thống Căn Hộ Dịch Vụ';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../config/database.php';

// 1. TỔNG QUAN KPI VẬN HÀNH (Dữ liệu động 100% từ Database)
$countCanHo        = (int)$pdo->query('SELECT COUNT(*) FROM CanHo')->fetchColumn();
$countPhongDangThue = (int)$pdo->query("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Đang thuê'")->fetchColumn();
$countPhongTrong    = (int)$pdo->query("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Trống'")->fetchColumn();
$countPhongBaoTri   = (int)$pdo->query("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Bảo trì'")->fetchColumn();
$countKhachThue     = (int)$pdo->query('SELECT COUNT(*) FROM KhachThue')->fetchColumn();
$countHopDongActive = (int)$pdo->query("SELECT COUNT(*) FROM HopDong WHERE TrangThai = 'Đang hiệu lực'")->fetchColumn();

// Tỷ lệ lấp đầy
$occupancyRate = ($countCanHo > 0) ? round(($countPhongDangThue / $countCanHo) * 100, 1) : 0;

// Công nợ theo tháng: tính tháng nào theo tháng đó (Tháng hiện tại + nợ cũ quá hạn)
$curKyMM = date('m/Y');
$stmtCurDebt = $pdo->prepare("
    SELECT COUNT(*) AS Cnt, COALESCE(SUM(TongTien), 0) AS Total 
    FROM HoaDon 
    WHERE KyThanhToan = ? AND (TrangThaiThanhToan <> 'Đã thanh toán' AND TrangThai <> 'Đã TT')
");
$stmtCurDebt->execute([$curKyMM]);
$curDebt = $stmtCurDebt->fetch(PDO::FETCH_ASSOC);
$congNoThangNay    = (float)($curDebt['Total'] ?? 0);
$soPhongNoThangNay = (int)($curDebt['Cnt'] ?? 0);

// Công nợ quá hạn tồn đọng từ các tháng trước (STR_TO_DATE < kỳ hiện tại)
$stmtPastDebt = $pdo->prepare("
    SELECT COUNT(*) AS Cnt, COALESCE(SUM(TongTien), 0) AS Total 
    FROM HoaDon 
    WHERE STR_TO_DATE(CONCAT('01/', KyThanhToan), '%d/%m/%Y') < STR_TO_DATE(CONCAT('01/', ?), '%d/%m/%Y')
      AND (TrangThaiThanhToan <> 'Đã thanh toán' AND TrangThai <> 'Đã TT')
");
$stmtPastDebt->execute([$curKyMM]);
$pastDebt = $stmtPastDebt->fetch(PDO::FETCH_ASSOC);
$congNoTonDong  = (float)($pastDebt['Total'] ?? 0);
$soPhongTonDong = (int)($pastDebt['Cnt'] ?? 0);

// 2. DOANH THU THỰC THU THÁNG HIỆN TẠI
$curKyMM = date('m/Y');
$stmtCurRev = $pdo->prepare("
    SELECT 
        COALESCE(SUM(TongTien), 0) AS TongThucThu,
        COALESCE(SUM(TienThue), 0) AS TienPhongThucThu,
        COUNT(*) AS SoPhongDaThu
    FROM HoaDon 
    WHERE (TrangThaiThanhToan = 'Đã thanh toán' OR TrangThai = 'Đã TT') 
      AND KyThanhToan = ?
");
$stmtCurRev->execute([$curKyMM]);
$curRevData = $stmtCurRev->fetch(PDO::FETCH_ASSOC);

$doanhThuThangNay = (float)($curRevData['TongThucThu'] ?? 0);
$doanhThuTienPhong = (float)($curRevData['TienPhongThucThu'] ?? 0);
$soPhongDaThu      = (int)($curRevData['SoPhongDaThu'] ?? 0);

// 3. DOANH THU THỰC THU TOÀN BỘ 12 THÁNG CỦA NĂM ĐƯỢC CHỌN
$systemCurrentYear = (int)date('Y');
$selectedYear = filter_input(INPUT_GET, 'nam', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 2000, 'max_range' => 2100],
]);
if ($selectedYear === false || $selectedYear === null) {
    $selectedYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 2000, 'max_range' => 2100],
    ]);
}
if ($selectedYear === false || $selectedYear === null) {
    $selectedYear = $systemCurrentYear;
}
$curYear = $selectedYear;

// Lấy danh sách tất cả các năm có dữ liệu hoặc xung quanh năm hiện tại
$availableYearsMap = [];

// 1. Quét các năm có hóa đơn
try {
    $stmtYearsHd = $pdo->query("
        SELECT DISTINCT RIGHT(KyThanhToan, 4) AS Nam 
        FROM HoaDon 
        WHERE KyThanhToan IS NOT NULL AND KyThanhToan <> ''
    ");
    $hdYears = $stmtYearsHd->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($hdYears as $yStr) {
        $yInt = (int)$yStr;
        if ($yInt >= 2000 && $yInt <= 2100) {
            $availableYearsMap[$yInt] = true;
        }
    }
} catch (Throwable $e) {}

// 2. Quét các năm trong hợp đồng
try {
    $stmtYearsHp = $pdo->query("
        SELECT DISTINCT YEAR(NgayBatDau) AS Nam FROM HopDong WHERE NgayBatDau IS NOT NULL
        UNION 
        SELECT DISTINCT YEAR(NgayKetThuc) AS Nam FROM HopDong WHERE NgayKetThuc IS NOT NULL
    ");
    $hpYears = $stmtYearsHp->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($hpYears as $yStr) {
        $yInt = (int)$yStr;
        if ($yInt >= 2000 && $yInt <= 2100) {
            $availableYearsMap[$yInt] = true;
        }
    }
} catch (Throwable $e) {}

// 3. Đảm bảo dải năm rộng từ 2015 đến 2035 (và mở rộng theo năm hiện tại)
$minYear = 2015;
$maxYear = max(2035, $systemCurrentYear + 5);
for ($y = $minYear; $y <= $maxYear; $y++) {
    $availableYearsMap[$y] = true;
}
$availableYearsMap[$curYear] = true;
$availableYears = array_keys($availableYearsMap);
rsort($availableYears);

$stmtYearRev = $pdo->prepare("
    SELECT 
        hd.KyThanhToan,
        COUNT(*) AS SoHoaDon,
        COALESCE(SUM(hd.TongTien), 0) AS TongDoanhThu
    FROM HoaDon hd
    WHERE (hd.TrangThaiThanhToan = 'Đã thanh toán' OR hd.TrangThai = 'Đã TT')
      AND RIGHT(hd.KyThanhToan, 4) = :year
    GROUP BY hd.KyThanhToan
    ORDER BY STR_TO_DATE(CONCAT('01/', hd.KyThanhToan), '%d/%m/%Y') ASC
");
$stmtYearRev->execute(['year' => (string)$curYear]);
$yearRevRows = $stmtYearRev->fetchAll(PDO::FETCH_ASSOC);

$all12MonthsRev = [];
for ($m = 1; $m <= 12; $m++) {
    $k = sprintf('%02d/%04d', $m, $curYear);
    $all12MonthsRev[$k] = 0.0;
}
$tongDoanhThuNamHienTai = 0.0;
foreach ($yearRevRows as $r) {
    if (isset($all12MonthsRev[$r['KyThanhToan']])) {
        $all12MonthsRev[$r['KyThanhToan']] = (float)$r['TongDoanhThu'];
        $tongDoanhThuNamHienTai += (float)$r['TongDoanhThu'];
    }
}

$chartRevLabels = [];
$chartRevValues = [];
$chartRevColors = [];
$chartKyMap     = [];

foreach ($all12MonthsRev as $k => $val) {
    $mNum = substr($k, 0, 2);
    $chartRevLabels[] = 'Tháng ' . $mNum;
    $chartRevValues[] = $val;
    $chartKyMap[]     = $k;
    if ($k === $curKyMM) {
        $chartRevColors[] = '#0284c7'; // Nổi bật tháng hiện tại
    } elseif ($val > 0) {
        $chartRevColors[] = '#38bdf8'; // Tháng có doanh thu
    } else {
        $chartRevColors[] = '#e2e8f0'; // Tháng chưa có doanh thu
    }
}
?>

<!-- ========================================================================
     HEADER & CÁC NÚT TÁC NGHIỆP NHANH
     ======================================================================== -->
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div>
        <h1 class="page-title" style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; color: #0f172a;">
            Dashboard
        </h1>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;">
            Tổng quan hiệu suất vận hành chuỗi căn hộ và doanh thu
        </p>
    </div>
    <div style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
        <!-- BỘ CHỌN NĂM DASHBOARD DẠNG POPUP YEAR-PICKER THÔNG MINH -->
        <div style="position: relative; display: inline-block;">
            <button type="button" 
                    id="btnOpenYearPicker"
                    onclick="toggleDashboardYearPicker(event)" 
                    style="display: inline-flex; align-items: center; gap: 0.5rem; background: #ffffff; border: 1.5px solid #0284c7; border-radius: 8px; padding: 0.45rem 0.85rem; font-size: 0.9rem; font-weight: 700; color: #0284c7; cursor: pointer; box-shadow: 0 1px 3px rgba(2, 132, 199, 0.12); transition: all 0.2s;">
                <?= svgIcon('calendar', '', 18) ?>
                <span>Năm: <strong style="color: #0f172a; font-size: 0.95rem;"><?= $curYear ?></strong> <?= ($curYear === $systemCurrentYear) ? '<span style="color:#0284c7;font-size:0.75rem;">(Hiện tại)</span>' : '' ?></span>
                <span style="font-size: 0.75rem; color: #64748b; margin-left: 2px;">▼</span>
            </button>

            <!-- BẢNG CHỌN NĂM TRỰC QUAN (POPOVER) -->
            <div id="dashboardYearPickerPopover" 
                 style="display: none; position: absolute; top: calc(100% + 8px); right: 0; z-index: 1050; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 14px; padding: 1.15rem; width: 330px; box-shadow: 0 15px 35px -5px rgba(15, 23, 42, 0.25);">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; padding-bottom: 0.65rem; border-bottom: 1px solid #f1f5f9;">
                    <div style="font-weight: 800; font-size: 0.9rem; color: #0f172a; display: flex; align-items: center; gap: 0.4rem;">
                        <span style="color: #0284c7;"><?= svgIcon('calendar', '', 16) ?></span>
                        <span>Chọn Năm Xem Doanh Thu</span>
                    </div>
                    <button type="button" 
                            onclick="toggleDashboardYearPicker(event, false)" 
                            style="border: none; background: #f1f5f9; width: 26px; height: 26px; border-radius: 6px; color: #64748b; cursor: pointer; font-size: 1rem; line-height: 1; display: flex; align-items: center; justify-content: center;">
                        &times;
                    </button>
                </div>

                <!-- Nhập nhanh năm bất kỳ -->
                <form method="GET" action="<?= url('/admin/index.php') ?>" style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem;">
                    <input type="number" 
                           name="nam" 
                           min="2000" 
                           max="2099" 
                           value="<?= $curYear ?>" 
                           placeholder="Nhập năm cần xem..."
                           style="flex: 1; padding: 0.4rem 0.65rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; font-weight: 700; color: #0f172a; outline: none;"
                           onfocus="this.style.borderColor='#0284c7'"
                           onblur="this.style.borderColor='#cbd5e1'">
                    <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.85rem; font-size: 0.85rem; border-radius: 6px;">
                        Xem ngay
                    </button>
                </form>

                <!-- Dải năm nhanh nhiều lựa chọn từ 2015 đến 2035 -->
                <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.45rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    Danh Sách Các Năm (<?= count($availableYears) ?> năm):
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.4rem; max-height: 220px; overflow-y: auto; padding-right: 2px;">
                    <?php foreach ($availableYears as $y): 
                        $isCur = ($y === $curYear);
                        $isSystem = ($y === $systemCurrentYear);
                    ?>
                        <a href="<?= url('/admin/index.php?nam=' . $y) ?>" 
                           style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0.45rem 0.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: <?= $isCur ? '800' : '600' ?>; text-decoration: none; text-align: center; transition: all 0.15s; <?= $isCur ? 'background: #0284c7; color: #ffffff; box-shadow: 0 3px 8px rgba(2,132,199,0.35);' : ($isSystem ? 'background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc;' : 'background: #f8fafc; color: #334155; border: 1px solid #e2e8f0;') ?>"
                           onmouseover="<?= $isCur ? '' : "this.style.background='#bae6fd';this.style.borderColor='#38bdf8';this.style.color='#0369a1';" ?>"
                           onmouseout="<?= $isCur ? '' : ($isSystem ? "this.style.background='#e0f2fe';this.style.borderColor='#7dd3fc';this.style.color='#0369a1';" : "this.style.background='#f8fafc';this.style.borderColor='#e2e8f0';this.style.color='#334155';") ?>">
                            <span><?= $y ?></span>
                            <?php if ($isSystem): ?>
                                <span style="font-size: 0.65rem; opacity: <?= $isCur ? '0.9' : '0.8' ?>;">Hiện tại</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <a href="<?= url('/admin/bao-cao/doanh-thu.php?nam=' . $curYear) ?>" 
           class="btn btn-primary">
            <?= svgIcon('chart', '', 16) ?> <span>Báo Cáo Doanh Thu 12 Tháng</span>
        </a>
        <a href="<?= url('/admin/bao-cao/export-excel.php?type=doanh_thu&nam=' . $curYear) ?>" 
           class="btn btn-excel" 
           title="Xuất báo cáo Excel tổng hợp cả năm <?= $curYear ?>">
            <?= svgIcon('download', '', 16) ?> <span>Xuất Excel Năm <?= $curYear ?></span>
        </a>
    </div>
</div>

<!-- ========================================================================
     SECTION: TỔNG QUAN KPI THỐNG KÊ (GRID 4 CARD CAO CẤP)
     ======================================================================== -->
<div class="detail-grid">
    <!-- CARD 1: CĂN HỘ -->
    <div class="detail-item" style="border-top: 3px solid #2563eb;">
        <div class="detail-label">
            <span>TỔNG CĂN HỘ VẬN HÀNH</span>
            <?= svgIcon('building', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #1e293b;">
            <?= $countCanHo ?> <span style="font-size: 1rem; font-weight: 600; color: #64748b;">căn</span>
        </div>
        <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
            <strong style="color: #10b981;"><?= $countPhongDangThue ?></strong> đang thuê &bull; 
            <strong style="color: #3b82f6;"><?= $countPhongTrong ?></strong> trống &bull; 
            <strong style="color: #f59e0b;"><?= $countPhongBaoTri ?></strong> bảo trì
        </div>
    </div>

    <!-- CARD 2: TỶ LỆ LẤP ĐẦY -->
    <div class="detail-item" style="border-top: 3px solid #10b981;">
        <div class="detail-label">
            <span>TỶ LỆ LẤP ĐẦY PHÒNG</span>
            <?= svgIcon('trend-up', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #059669;">
            <?= $occupancyRate ?>%
        </div>
        <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
            <strong><?= $countHopDongActive ?></strong> hợp đồng thuê đang có hiệu lực
        </div>
    </div>

    <!-- CARD 3: DOANH THU NĂM (CLICKABLE) -->
    <div class="detail-item" 
         style="border-top: 3px solid #0284c7; cursor: pointer;" 
         onclick="window.location.href='<?= url('/admin/bao-cao/doanh-thu.php?nam=' . $curYear) ?>'" 
         title="Bấm để xem bóc tách doanh thu chi tiết 12 tháng năm <?= $curYear ?>">
        <div class="detail-label">
            <span>DOANH THU NĂM <?= $curYear ?></span>
            <?= svgIcon('invoice', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #0284c7;">
            <?= formatMoney($tongDoanhThuNamHienTai) ?>
        </div>
        <div style="font-size: 0.825rem; color: #64748b; margin-top: 0.35rem;">
            <?php if ($curYear === $systemCurrentYear): ?>
                Tháng <?= date('m/Y') ?>: <strong style="color: #0369a1;"><?= formatMoney($doanhThuThangNay) ?></strong> (<?= $soPhongDaThu ?> phòng)
            <?php else: ?>
                Tổng cả năm <?= $curYear ?>: <strong style="color: #0369a1;"><?= formatMoney($tongDoanhThuNamHienTai) ?></strong>
            <?php endif; ?>
        </div>
        <div style="font-size: 0.75rem; color: #0284c7; font-weight: 700; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.25rem;">
            <span>Xem chi tiết năm <?= $curYear ?></span> &rarr;
        </div>
    </div>

    <!-- CARD 4: CÔNG NỢ THEO THÁNG (CLICKABLE) -->
    <div class="detail-item" 
         style="border-top: 3px solid #ef4444; cursor: pointer;" 
         onclick="window.location.href='<?= url('/admin/bao-cao/cong-no.php?ky=' . urlencode($curKyMM)) ?>'" 
         title="Bấm để xem danh sách công nợ chi tiết tháng <?= $curKyMM ?>">
        <div class="detail-label">
            <span>CÔNG NỢ THÁNG <?= $curKyMM ?></span>
            <?= svgIcon('trend-down', '', 18) ?>
        </div>
        <div class="detail-value" style="color: #dc2626;">
            <?= formatMoney($congNoThangNay) ?>
        </div>
        <div style="font-size: 0.825rem; color: #b91c1c; margin-top: 0.35rem;">
            <strong><?= $soPhongNoThangNay ?></strong> phòng chưa thu kỳ này &bull; Nợ cũ tồn: <strong><?= formatMoney($congNoTonDong) ?></strong>
        </div>
        <div style="font-size: 0.75rem; color: #dc2626; font-weight: 700; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.25rem;">
            <span>Xem công nợ tháng <?= $curKyMM ?></span> &rarr;
        </div>
    </div>
</div>

<!-- ========================================================================
     SECTION: BIỂU ĐỒ DOANH THU NĂM (12 THÁNG) & TỶ LỆ PHÒNG
     ======================================================================== -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- BIỂU ĐỒ DOANH THU 12 THÁNG CỦA NĂM -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h3 style="display: flex; align-items: center; gap: 0.5rem; margin: 0; font-size: 1.05rem;">
                    <?= svgIcon('chart', '', 18) ?>
                    <span>Biểu Đồ Doanh Thu Thực Thu Năm <?= $curYear ?> (12 Tháng)</span>
                </h3>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.2rem;">
                    Bấm vào cột tháng bất kỳ để xem bảng chi tiết từng hóa đơn của tháng đó
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                <!-- Dải nút chuyển năm trước / năm sau & chọn nhanh -->
                <div style="display: inline-flex; align-items: center; gap: 0.25rem; background: #f1f5f9; padding: 0.2rem 0.35rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <!-- Lùi 1 năm -->
                    <a href="<?= url('/admin/index.php?nam=' . ($curYear - 1)) ?>" 
                       style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #ffffff; color: #0284c7; text-decoration: none; font-weight: 800; font-size: 1.1rem; line-height: 1; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s;"
                       title="Xem năm trước (<?= $curYear - 1 ?>)"
                       onmouseover="this.style.background='#0284c7';this.style.color='#fff';"
                       onmouseout="this.style.background='#fff';this.style.color='#0284c7';">
                        &lsaquo;
                    </a>

                    <!-- Bấm icon lịch để bật popup chọn năm luôn -->
                    <button type="button" 
                            onclick="toggleDashboardYearPicker(event, true)" 
                            style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.55rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; color: #0284c7; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;"
                            title="Bấm để chọn năm bất kỳ trong danh sách">
                        <?= svgIcon('calendar', '', 15) ?>
                        <span style="color: #0f172a;"><?= $curYear ?></span>
                        <span style="font-size: 0.65rem; color: #64748b;">▼</span>
                    </button>

                    <!-- Tiến 1 năm -->
                    <a href="<?= url('/admin/index.php?nam=' . ($curYear + 1)) ?>" 
                       style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #ffffff; color: #0284c7; text-decoration: none; font-weight: 800; font-size: 1.1rem; line-height: 1; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s;"
                       title="Xem năm sau (<?= $curYear + 1 ?>)"
                       onmouseover="this.style.background='#0284c7';this.style.color='#fff';"
                       onmouseout="this.style.background='#fff';this.style.color='#0284c7';">
                        &rsaquo;
                    </a>
                </div>

                <span style="font-size: 0.85rem; color: #475569; font-weight: 600;">
                    Tổng: <span style="color: #0284c7; font-weight: 700;"><?= formatMoney($tongDoanhThuNamHienTai) ?></span>
                </span>
                <a href="<?= url('/admin/bao-cao/doanh-thu.php?nam=' . $curYear) ?>" class="btn btn-sm btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.55rem; color: #0284c7; border-color: #cbd5e1;">
                    Xem chi tiết &rarr;
                </a>
            </div>
        </div>
        <div class="card-body">
            <div style="position: relative; height: 280px; width: 100%;">
                <canvas id="chartRevenueYear"></canvas>
            </div>

            <!-- Dải nút chọn nhanh 12 tháng -->
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 1rem; padding-top: 0.85rem; border-top: 1px dashed var(--border-color, #e2e8f0); align-items: center;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-right: 0.25rem;">Tháng:</span>
                <?php foreach ($all12MonthsRev as $k => $v): 
                    $mNum = substr($k, 0, 2);
                    $hasRev = ($v > 0);
                    $isCur = ($k === $curKyMM);
                ?>
                    <a href="<?= url('/admin/bao-cao/doanh-thu.php?ky=' . urlencode($k)) ?>" 
                       style="display: inline-flex; align-items: center; gap: 0.25rem; text-decoration: none; padding: 0.3rem 0.55rem; font-size: 0.78rem; font-weight: <?= $isCur ? '700' : '600' ?>; border-radius: 6px; transition: all 0.2s; border: 1px solid <?= $isCur ? '#0284c7' : ($hasRev ? '#cbd5e1' : '#e2e8f0') ?>; <?= $isCur ? 'background: #0284c7; color: #fff;' : ($hasRev ? 'background: #f8fafc; color: #1e293b;' : 'background: #fafafa; color: #94a3b8;') ?>"
                       onmouseover="<?= $isCur ? '' : "this.style.background='#0284c7';this.style.color='#fff';this.style.borderColor='#0284c7';" ?>"
                       onmouseout="<?= $isCur ? '' : ($hasRev ? "this.style.background='#f8fafc';this.style.color='#1e293b';this.style.borderColor='#cbd5e1';" : "this.style.background='#fafafa';this.style.color='#94a3b8';this.style.borderColor='#e2e8f0';") ?>">
                        T.<?= $mNum ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- TỶ LỆ TRẠNG THÁI PHÒNG (DONUT CHART) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 style="display: flex; align-items: center; gap: 0.5rem; margin: 0; font-size: 1.05rem;">
                <?= svgIcon('door', '', 18) ?>
                <span>Tình Trạng Lấp Đầy Phòng</span>
            </h3>
            <span class="badge badge-success" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;"><?= $occupancyRate ?>% Đầy</span>
        </div>
        <div class="card-body">
            <div style="position: relative; height: 210px; width: 100%;">
                <canvas id="chartOccupancy"></canvas>
            </div>
            <div style="display: flex; justify-content: space-around; margin-top: 1.25rem; font-size: 0.825rem; text-align: center;">
                <div>
                    <span style="color: #10b981; font-weight: 700;">●</span> Đang thuê: <strong><?= $countPhongDangThue ?></strong>
                </div>
                <div>
                    <span style="color: #3b82f6; font-weight: 700;">●</span> Trống: <strong><?= $countPhongTrong ?></strong>
                </div>
                <div>
                    <span style="color: #f59e0b; font-weight: 700;">●</span> Bảo trì: <strong><?= $countPhongBaoTri ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================
     JAVASCRIPT CHARTS (CHART.JS)
     ======================================================================== -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Biểu đồ Doanh thu 12 Tháng của năm
    const ctxRev = document.getElementById('chartRevenueYear');
    if (ctxRev) {
        const revLabels = <?= json_encode($chartRevLabels, JSON_UNESCAPED_UNICODE) ?>;
        const revKyMap  = <?= json_encode($chartKyMap, JSON_UNESCAPED_UNICODE) ?>;
        const revColors = <?= json_encode($chartRevColors, JSON_UNESCAPED_UNICODE) ?>;

        new Chart(ctxRev, {
            type: 'bar',
            data: {
                labels: revLabels,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: <?= json_encode($chartRevValues) ?>,
                    backgroundColor: revColors,
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
                            label: function(c) {
                                return ' Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(c.raw) + ' đ';
                            },
                            afterLabel: function(c) {
                                return (c.raw > 0) ? ' (Nhấn vào cột để xem chi tiết tháng)' : ' (Chưa phát sinh doanh thu)';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: function(v) { return (v / 1000000).toLocaleString('vi-VN') + ' tr'; }
                        }
                    }
                },
                onClick: function(evt, elements) {
                    if (elements && elements.length > 0) {
                        const idx = elements[0].index;
                        const ky = revKyMap[idx]; // "09/2026"
                        window.location.href = '<?= url('/admin/bao-cao/doanh-thu.php?ky=') ?>' + encodeURIComponent(ky);
                    }
                },
                onHover: function(evt, elements) {
                    evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                }
            }
        });
    }

    // 2. Biểu đồ Tỷ lệ phòng
    const ctxOcc = document.getElementById('chartOccupancy');
    if (ctxOcc) {
        new Chart(ctxOcc, {
            type: 'doughnut',
            data: {
                labels: ['Đang thuê', 'Trống', 'Bảo trì'],
                datasets: [{
                    data: [<?= (int)$countPhongDangThue ?>, <?= (int)$countPhongTrong ?>, <?= (int)$countPhongBaoTri ?>],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }
});

// Điều khiển bật/tắt bảng chọn năm (Year Picker Popover)
function toggleDashboardYearPicker(e, forceState) {
    if (e) e.stopPropagation();
    const pop = document.getElementById('dashboardYearPickerPopover');
    if (!pop) return;
    if (typeof forceState === 'boolean') {
        pop.style.display = forceState ? 'block' : 'none';
    } else {
        pop.style.display = (pop.style.display === 'block') ? 'none' : 'block';
    }
}

// Bấm ra ngoài thì tự đóng popup
document.addEventListener('click', function(e) {
    const pop = document.getElementById('dashboardYearPickerPopover');
    const btn = document.getElementById('btnOpenYearPicker');
    if (pop && pop.style.display === 'block') {
        if (!pop.contains(e.target) && (!btn || !btn.contains(e.target))) {
            pop.style.display = 'none';
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>