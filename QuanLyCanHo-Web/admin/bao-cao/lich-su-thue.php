<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';

// Mục này đã được gỡ bỏ theo yêu cầu, tự động chuyển về Báo cáo Doanh thu
redirect('/admin/bao-cao/doanh-thu.php');

function validDate(?string $d): ?string
{
    if (!$d) return null;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : null;
}

$soPhong = trim((string)($_GET['so_phong'] ?? ''));
$tenKhach = trim((string)($_GET['ten_khach'] ?? ''));
$validStatuses = ['Đang hiệu lực', 'Đã thanh lý', 'Hết hạn'];
$trangThai = in_array($_GET['trang_thai'] ?? '', $validStatuses, true) ? $_GET['trang_thai'] : '';
$tuNgay = validDate($_GET['tu_ngay'] ?? null);
$denNgay = validDate($_GET['den_ngay'] ?? null);

$whereClauses = [];
$params = [];

if ($soPhong !== '') {
    $whereClauses[] = 'SoPhong LIKE ?';
    $params[] = '%' . $soPhong . '%';
}
if ($tenKhach !== '') {
    $whereClauses[] = '(TenKhachThue LIKE ? OR SoDienThoai LIKE ?)';
    $params[] = '%' . $tenKhach . '%';
    $params[] = '%' . $tenKhach . '%';
}
if ($trangThai !== '') {
    $whereClauses[] = 'TrangThaiHopDong = ?';
    $params[] = $trangThai;
}
if ($tuNgay !== null) {
    $whereClauses[] = 'NgayBatDau >= ?';
    $params[] = $tuNgay;
}
if ($denNgay !== null) {
    $whereClauses[] = 'NgayBatDau <= ?';
    $params[] = $denNgay;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Xuất CSV - lấy toàn bộ kết quả khớp filter, không giới hạn phân trang
if ($isExport) {
    $stmt = $pdo->prepare("SELECT * FROM View_LichSuThueCanHo $whereSql ORDER BY NgayBatDau DESC");
    $stmt->execute($params);
    $exportRows = $stmt->fetchAll();

    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lich-su-thue-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Địa chỉ', 'Số phòng', 'Loại căn hộ', 'Khách thuê', 'Số điện thoại', 'Ngày bắt đầu', 'Ngày kết thúc', 'Giá thuê thỏa thuận', 'Trạng thái HĐ']);
    foreach ($exportRows as $r) {
        fputcsv($out, [
            $r['DiaChi'] ?? '',
            $r['SoPhong'] ?? '',
            $r['TenLoai'] ?? '',
            $r['TenKhachThue'] ?? '',
            $r['SoDienThoai'] ?? '',
            $r['NgayBatDau'] ?? '',
            $r['NgayKetThuc'] ?? '',
            $r['GiaThueThoa'] ?? '',
            $r['TrangThaiHopDong'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM View_LichSuThueCanHo $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT * FROM View_LichSuThueCanHo $whereSql ORDER BY NgayBatDau DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$qs = http_build_query(array_filter([
    'so_phong' => $soPhong,
    'ten_khach' => $tenKhach,
    'trang_thai' => $trangThai,
    'tu_ngay' => $tuNgay,
    'den_ngay' => $denNgay
]));
$qs = $qs !== '' ? $qs . '&' : '';

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.report-breadcrumb {
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
    margin-bottom: 0.35rem;
}
.report-breadcrumb a {
    color: var(--text-muted, #64748b);
    text-decoration: none;
}
.report-breadcrumb a:hover {
    color: var(--primary-color, #2563eb);
}
.report-title {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.02em;
}
.filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    margin-bottom: 1.5rem;
}
.filter-card-body {
    padding: 1.25rem 1.5rem;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 1rem;
    align-items: flex-end;
}
.filter-group label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.4rem;
}
.filter-group .form-control {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 0.5rem 0.75rem;
    font-size: 0.88rem;
    background-color: #ffffff;
    transition: all 0.2s;
}
.filter-group .form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.filter-actions {
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
}
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.42rem 0.75rem;
    border-radius: 8px;
    font-size: 0.815rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.18s ease;
    border: 1px solid transparent;
    cursor: pointer;
    line-height: 1.2;
}
.btn-action-view {
    background: #eff6ff;
    color: #2563eb;
    border-color: #bfdbfe;
}
.btn-action-view:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}
.table-rental th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #e2e8f0;
}
.table-rental td {
    padding: 0.9rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.table-rental tbody tr:hover {
    background-color: #f8fafc;
}
</style>

<div class="report-header">
    <div>
        <div class="report-breadcrumb">
            <a href="<?= url('/admin/dashboard.php') ?>">Trang chủ</a> / <span>Báo cáo</span> / <span>Lịch sử thuê</span>
        </div>
        <h1 class="report-title">Báo cáo Lịch sử thuê</h1>
    </div>
    <div>
        <a href="?<?= $qs ?>export=csv" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; border-radius: 8px; padding: 0.55rem 1rem;">
            <?= svgIcon('download', '', 15) ?>
            <span>Xuất file CSV</span>
        </a>
    </div>
</div>

<div class="filter-card">
    <div class="filter-card-body">
        <form method="GET" action="" class="filter-grid">
            <div class="filter-group">
                <label for="so_phong">Số phòng</label>
                <input type="text" id="so_phong" name="so_phong" class="form-control" placeholder="VD: P101..." value="<?= e($soPhong) ?>">
            </div>
            <div class="filter-group">
                <label for="ten_khach">Khách thuê</label>
                <input type="text" id="ten_khach" name="ten_khach" class="form-control" placeholder="Tên hoặc SĐT..." value="<?= e($tenKhach) ?>">
            </div>
            <div class="filter-group">
                <label for="trang_thai">Trạng thái hợp đồng</label>
                <select id="trang_thai" name="trang_thai" class="form-control">
                    <option value="">-- Tất cả trạng thái --</option>
                    <?php foreach ($validStatuses as $vs): ?>
                        <option value="<?= e($vs) ?>" <?= ($trangThai === $vs) ? 'selected' : '' ?>><?= e($vs) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="tu_ngay">Từ ngày bắt đầu</label>
                <input type="date" id="tu_ngay" name="tu_ngay" class="form-control" value="<?= e($tuNgay ?? '') ?>">
            </div>
            <div class="filter-group">
                <label for="den_ngay">Đến ngày bắt đầu</label>
                <input type="date" id="den_ngay" name="den_ngay" class="form-control" value="<?= e($denNgay ?? '') ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; border-radius: 8px; padding: 0.55rem 1.1rem;">
                    <?= svgIcon('filter', '', 14) ?>
                    <span>Lọc</span>
                </button>
                <a href="?" class="btn btn-outline" style="font-weight: 600; border-radius: 8px; padding: 0.55rem 1rem;">Bỏ lọc</a>
            </div>
        </form>
    </div>
</div>

<div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <div class="card-header" style="background: #ffffff; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #1e293b;">
            Danh sách hợp đồng thuê
            <span style="font-size: 0.85rem; font-weight: 500; color: #64748b; margin-left: 0.5rem;">(Tổng cộng <?= number_format($totalRows) ?> kết quả)</span>
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rows)): ?>
            <div style="text-align: center; padding: 3rem 1.5rem; color: #64748b;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem; color: #94a3b8;"><?= svgIcon('info', '', 40) ?></div>
                <h4 style="font-weight: 600; color: #334155; margin-bottom: 0.25rem;">Không tìm thấy dữ liệu</h4>
                <p style="margin: 0; font-size: 0.9rem;">Không có hợp đồng nào phù hợp với điều kiện tìm kiếm hiện tại.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-rental" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Địa chỉ / Phòng</th>
                            <th>Loại căn hộ</th>
                            <th style="min-width: 170px;">Khách thuê</th>
                            <th>Thời hạn thuê</th>
                            <th>Giá thuê</th>
                            <th>Trạng thái</th>
                            <th class="text-center" style="width: 100px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td style="max-width: 300px;">
                                    <div style="font-weight: 500; font-size: 0.85rem; color: #334155; line-height: 1.35; margin-bottom: 0.35rem;">
                                        <?= e($row['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?>
                                    </div>
                                    <span style="font-weight: 700; color: #1d4ed8; font-size: 0.85rem; background: #eff6ff; padding: 2px 8px; border-radius: 4px; border: 1px solid #bfdbfe; display: inline-block;">
                                        Phòng <?= e(formatSoPhong($row['SoPhong'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 500; color: #475569; font-size: 0.88rem;">
                                        <?= e($row['TenLoai']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.92rem;">
                                        <?= e($row['TenKhachThue']) ?>
                                    </div>
                                    <div style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                                        <?= e($row['SoDienThoai']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.88rem; color: #334155; font-weight: 500;">
                                        <?= formatDate($row['NgayBatDau']) ?>
                                        <span style="color: #94a3b8; margin: 0 2px;">&rarr;</span>
                                        <?= formatDate($row['NgayKetThuc']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: #0f172a; font-size: 0.92rem;">
                                        <?= formatMoney($row['GiaThueThoa']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= renderStatusBadge($row['TrangThaiHopDong']) ?>
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <a href="<?= url('/admin/hop-dong/detail.php?id=' . $row['MaHopDong']) ?>" class="btn-action btn-action-view" title="Xem chi tiết hợp đồng">
                                        <?= svgIcon('eye', '', 13) ?>
                                        <span>Chi tiết</span>
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

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="display: flex; justify-content: center; gap: 6px; margin-top: 1.5rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= $qs ?>page=<?= $i ?>" class="<?= ($i === $page) ? 'active' : '' ?>" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid <?= ($i === $page) ? '#2563eb' : '#cbd5e1' ?>; background: <?= ($i === $page) ? '#2563eb' : '#ffffff' ?>; color: <?= ($i === $page) ? '#ffffff' : '#334155' ?>; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>