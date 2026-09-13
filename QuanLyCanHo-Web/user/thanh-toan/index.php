<?php

declare(strict_types=1);

$title = 'Quản lý hóa đơn thanh toán';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$keyword = trim((string)($_GET['keyword'] ?? ''));
$kyThanhToan = trim((string)($_GET['ky_thanh_toan'] ?? ''));
$diaChi = trim((string)($_GET['dia_chi'] ?? ''));
$canHoId = (int)($_GET['can_ho'] ?? 0);
$trangThai = trim((string)($_GET['trang_thai'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Lấy danh sách các kỳ thanh toán hiện có để làm bộ lọc dropdown
$periodsStmt = $pdo->query("
    SELECT DISTINCT KyThanhToan 
    FROM HoaDon 
    WHERE KyThanhToan IS NOT NULL AND KyThanhToan != ''
    ORDER BY SUBSTRING_INDEX(KyThanhToan, '/', -1) DESC, SUBSTRING_INDEX(KyThanhToan, '/', 1) DESC
");
$allPeriods = $periodsStmt ? $periodsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Lấy danh sách địa chỉ nhà để làm bộ lọc dropdown theo phân quyền
$staffAssigned = getStaffAssignedBuildings();
if ($staffAssigned !== null) {
    $allAddresses = $staffAssigned;
} else {
    $addressesStmt = $pdo->query("
        SELECT DISTINCT DiaChi 
        FROM CanHo 
        WHERE DiaChi IS NOT NULL AND TRIM(DiaChi) != ''
        ORDER BY DiaChi ASC
    ");
    $allAddresses = $addressesStmt ? $addressesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
}

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = '(hd.KyThanhToan LIKE ? OR kt.HoTen LIKE ? OR ch.SoPhong LIKE ? OR ch.DiaChi LIKE ? OR hp.MaHopDong LIKE ?)';
    $like = '%' . $keyword . '%';
    $params = [$like, $like, $like, $like, $like];
}

if ($kyThanhToan !== '') {
    $where[] = 'hd.KyThanhToan = ?';
    $params[] = $kyThanhToan;
}

if ($diaChi !== '') {
    $where[] = 'ch.DiaChi = ?';
    $params[] = $diaChi;
} elseif ($canHoId > 0) {
    $where[] = 'ch.MaCanHo = ?';
    $params[] = $canHoId;
}

if ($trangThai !== '') {
    $where[] = 'hd.TrangThai = ?';
    $params[] = $trangThai;
}

// Phân quyền theo tòa nhà cho nhân viên
$bldCond = buildStaffBuildingCondition('ch.DiaChi');
if ($bldCond['sql'] !== '1=1') {
    $where[] = $bldCond['sql'];
    foreach ($bldCond['params'] as $bp) {
        $params[] = $bp;
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Thống kê tổng quan theo bộ lọc (hoặc kỳ đã chọn)
$statSql = "SELECT 
                COUNT(*) AS TotalCount,
                COALESCE(SUM(hd.TongTien), 0) AS TotalAmount,
                COALESCE(SUM(CASE WHEN hd.TrangThai = 'Đã TT' OR hd.TrangThaiThanhToan = 'Đã thanh toán' THEN 1 ELSE 0 END), 0) AS PaidCount,
                COALESCE(SUM(CASE WHEN hd.TrangThai = 'Đã TT' OR hd.TrangThaiThanhToan = 'Đã thanh toán' THEN hd.TongTien ELSE 0 END), 0) AS PaidAmount,
                COALESCE(SUM(CASE WHEN hd.TrangThai != 'Đã TT' AND (hd.TrangThaiThanhToan IS NULL OR hd.TrangThaiThanhToan != 'Đã thanh toán') THEN 1 ELSE 0 END), 0) AS UnpaidCount,
                COALESCE(SUM(CASE WHEN hd.TrangThai != 'Đã TT' AND (hd.TrangThaiThanhToan IS NULL OR hd.TrangThaiThanhToan != 'Đã thanh toán') THEN hd.TongTien ELSE 0 END), 0) AS UnpaidAmount
            FROM HoaDon hd
            JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
            JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
            JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
            $whereSql";
$statStmt = $pdo->prepare($statSql);
$statStmt->execute($params);
$kpi = $statStmt->fetch() ?: [
    'TotalCount' => 0, 'TotalAmount' => 0,
    'PaidCount' => 0, 'PaidAmount' => 0,
    'UnpaidCount' => 0, 'UnpaidAmount' => 0
];

$countSql = "SELECT COUNT(*)
             FROM HoaDon hd
             JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
             JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
             JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
             $whereSql";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT hd.MaHoaDon, hd.KyThanhToan, hd.TrangThai, hd.TongTien, hd.NgayTao, hd.NgayThanhToan, hd.TrangThaiThanhToan,
              hp.MaHopDong, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach
        FROM HoaDon hd
        JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        $whereSql
        ORDER BY hd.MaHoaDon DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Quản lý hóa đơn</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/tao-hang-thang.php" class="btn btn-primary">+ Tạo hóa đơn tháng</a>
    </div>
</div>

<!-- Thống kê nhanh theo kỳ / bộ lọc -->
<div class="stats-grid mb-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
    <div class="stat-card" style="background: #fff; padding: 1.1rem 1.25rem; border-radius: 8px; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6;">
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">
            Tổng hóa đơn <?= $kyThanhToan !== '' ? 'kỳ ' . e($kyThanhToan) : '' ?>
        </div>
        <div style="font-size: 1.35rem; font-weight: 700; color: #1e293b; margin-top: 0.35rem;">
            <?= (int)$kpi['TotalCount'] ?> <span style="font-size: 0.88rem; font-weight: 500; color: #64748b;">(<?= formatMoney($kpi['TotalAmount']) ?>)</span>
        </div>
    </div>
    <div class="stat-card" style="background: #fff; padding: 1.1rem 1.25rem; border-radius: 8px; border: 1px solid #e2e8f0; border-left: 4px solid #10b981;">
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Đã thanh toán</div>
        <div style="font-size: 1.35rem; font-weight: 700; color: #059669; margin-top: 0.35rem;">
            <?= (int)$kpi['PaidCount'] ?> <span style="font-size: 0.88rem; font-weight: 500; color: #059669;">(<?= formatMoney($kpi['PaidAmount']) ?>)</span>
        </div>
    </div>
    <div class="stat-card" style="background: #fff; padding: 1.1rem 1.25rem; border-radius: 8px; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444;">
        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Chưa thanh toán / Còn nợ</div>
        <div style="font-size: 1.35rem; font-weight: 700; color: #dc2626; margin-top: 0.35rem;">
            <?= (int)$kpi['UnpaidCount'] ?> <span style="font-size: 0.88rem; font-weight: 500; color: #dc2626;">(<?= formatMoney($kpi['UnpaidAmount']) ?>)</span>
        </div>
    </div>
</div>

<div class="filter-card mb-3">
    <form method="GET" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: end;">
        <div class="filter-group" style="flex: 2; min-width: 220px;">
            <label for="keyword" style="font-weight: 600; font-size: 0.85rem;">Tìm kiếm hóa đơn</label>
            <input type="text" id="keyword" name="keyword" class="form-control" value="<?= e($keyword) ?>" placeholder="Tên khách, phòng, địa chỉ, mã HĐ...">
        </div>

        <div class="filter-group" style="flex: 1.5; min-width: 220px;">
            <label for="dia_chi" style="font-weight: 600; font-size: 0.85rem;">Địa chỉ nhà</label>
            <select id="dia_chi" name="dia_chi" class="form-control">
                <option value="">Tất cả địa chỉ</option>
                <?php foreach ($allAddresses as $dc): ?>
                    <option value="<?= e($dc) ?>" <?= $diaChi === $dc ? 'selected' : '' ?>>
                        <?= e($dc) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="flex: 1; min-width: 150px;">
            <label for="ky_thanh_toan" style="font-weight: 600; font-size: 0.85rem;">Kỳ thanh toán</label>
            <select id="ky_thanh_toan" name="ky_thanh_toan" class="form-control">
                <option value="">Tất cả các kỳ</option>
                <?php foreach ($allPeriods as $p): ?>
                    <option value="<?= e($p) ?>" <?= $kyThanhToan === $p ? 'selected' : '' ?>>Kỳ <?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="flex: 1; min-width: 140px;">
            <label for="trang_thai" style="font-weight: 600; font-size: 0.85rem;">Trạng thái</label>
            <select id="trang_thai" name="trang_thai" class="form-control">
                <option value="">Tất cả</option>
                <option value="Chưa TT" <?= $trangThai === 'Chưa TT' ? 'selected' : '' ?>>Chưa TT</option>
                <option value="Đã TT" <?= $trangThai === 'Đã TT' ? 'selected' : '' ?>>Đã TT</option>
                <option value="Quá hạn" <?= $trangThai === 'Quá hạn' ? 'selected' : '' ?>>Quá hạn</option>
            </select>
        </div>

        <div class="filter-group" style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">Lọc</button>
            <?php if ($keyword !== '' || $trangThai !== '' || $kyThanhToan !== '' || $diaChi !== '' || $canHoId > 0): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Đặt lại</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
.table th {
    white-space: nowrap;
    vertical-align: middle;
}
.table td {
    vertical-align: middle;
}
.col-money {
    white-space: nowrap !important;
    font-weight: 700;
    color: #0f172a;
    font-size: 0.95rem;
}
.btn-act {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
    border: 1px solid transparent;
    cursor: pointer;
    line-height: 1.2;
    white-space: nowrap;
}
.btn-act-primary {
    background: #2563eb;
    color: #ffffff !important;
    border-color: #2563eb;
}
.btn-act-primary:hover {
    background: #1d4ed8;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}
.btn-act-view {
    background: #f8fafc;
    color: #475569 !important;
    border-color: #cbd5e1;
}
.btn-act-view:hover {
    background: #e2e8f0;
    color: #0f172a !important;
}
.btn-act-print {
    background: #eff6ff;
    color: #2563eb !important;
    border-color: #bfdbfe;
}
.btn-act-print:hover {
    background: #dbeafe;
    color: #1d4ed8 !important;
}
.btn-act-edit {
    background: #f0fdf4;
    color: #166534 !important;
    border-color: #bbf7d0;
}
.btn-act-edit:hover {
    background: #dcfce7;
    color: #14532d !important;
}
.btn-act-danger {
    background: #fef2f2;
    color: #dc2626 !important;
    border-color: #fecaca;
}
.btn-act-danger:hover {
    background: #fee2e2;
    color: #b91c1c !important;
}
.col-thao-tac {
    text-align: center !important;
    min-width: 165px !important;
    width: 170px !important;
}
.invoice-btn-group {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 4px !important;
    width: 155px !important;
    margin: 0 auto !important;
}
.invoice-btn-row {
    display: flex !important;
    gap: 4px !important;
    width: 100% !important;
}
.invoice-btn-row .btn-act {
    flex: 1 1 50% !important;
    justify-content: center !important;
    text-align: center !important;
    padding: 0.32rem 0.35rem !important;
    font-size: 0.78rem !important;
}
.btn-act-pay {
    width: 100% !important;
    justify-content: center !important;
    text-align: center !important;
    padding: 0.35rem 0.5rem !important;
    font-size: 0.8rem !important;
}
</style>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0;">Danh sách hóa đơn <?= $kyThanhToan !== '' ? '(Kỳ ' . e($kyThanhToan) . ')' : '' ?></h3>
        <span class="badge" style="background: #e2e8f0; color: #475569; font-weight: 600;"><?= $totalRows ?> hóa đơn</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($invoices)): ?>
            <div class="empty-state">
                <p>Không có hóa đơn nào phù hợp với bộ lọc.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Kỳ</th>
                            <th>Khách thuê</th>
                            <th>Phòng</th>
                            <th>Địa chỉ căn hộ</th>
                            <th style="white-space: nowrap;">Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th class="col-thao-tac" style="text-align: center !important;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><strong>#<?= e((string)$invoice['MaHoaDon']) ?></strong></td>
                                <td><span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 600;"><?= e($invoice['KyThanhToan']) ?></span></td>
                                <td style="font-weight: 500;"><?= e($invoice['TenKhach']) ?></td>
                                <td><span class="badge" style="background: #f1f5f9; color: #1e293b; font-weight: 700;"><?= e(formatSoPhong($invoice['SoPhong'])) ?></span></td>
                                <td><span style="font-size: 0.88rem; color: #475569;"><?= e($invoice['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?></span></td>
                                <td class="col-money"><?= formatMoney($invoice['TongTien']) ?></td>
                                <td><?= renderStatusBadge((string)$invoice['TrangThai']) ?></td>
                                <td style="white-space: nowrap; color: #64748b; font-size: 0.85rem;"><?= formatDateTime((string)$invoice['NgayTao']) ?></td>
                                <td class="col-thao-tac" style="text-align: center !important;">
                                    <div class="invoice-btn-group">
                                        <?php if ((string)$invoice['TrangThai'] !== 'Đã TT'): ?>
                                            <a href="<?= $baseUrl ?>/thanh-toan.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn-act btn-act-primary btn-act-pay" title="Thu tiền"><?= svgIcon('payment', '', 12) ?> Thu tiền</a>
                                        <?php endif; ?>
                                        <div class="invoice-btn-row">
                                            <a href="<?= $baseUrl ?>/detail.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn-act btn-act-view" title="Chi tiết hóa đơn"><?= svgIcon('eye', '', 12) ?> Chi tiết</a>
                                            <a href="<?= url('/admin/phieu-in/hoa-don.php?id=' . (int)$invoice['MaHoaDon']) ?>" class="btn-act btn-act-print" target="_blank" title="In phiếu A4"><?= svgIcon('printer', '', 12) ?> In</a>
                                        </div>
                                        <div class="invoice-btn-row">
                                            <a href="<?= $baseUrl ?>/edit.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn-act btn-act-edit" title="Chỉnh sửa"><?= svgIcon('edit', '', 12) ?> Sửa</a>
                                            <a href="<?= $baseUrl ?>/delete.php?id=<?= (int)$invoice['MaHoaDon'] ?><?= $kyThanhToan !== '' ? '&ky_thanh_toan=' . urlencode($kyThanhToan) : '' ?><?= $diaChi !== '' ? '&dia_chi=' . urlencode($diaChi) : '' ?>" class="btn-act btn-act-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa hóa đơn #<?= (int)$invoice['MaHoaDon'] ?> (Phòng <?= e(formatSoPhong($invoice['SoPhong'])) ?> - Kỳ <?= e($invoice['KyThanhToan']) ?>)?');" title="Xóa hóa đơn"><?= svgIcon('trash', '', 12) ?> Xóa</a>
                                        </div>
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

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.3rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?keyword=<?= urlencode($keyword) ?>&ky_thanh_toan=<?= urlencode($kyThanhToan) ?>&dia_chi=<?= urlencode($diaChi) ?>&trang_thai=<?= urlencode($trangThai) ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
