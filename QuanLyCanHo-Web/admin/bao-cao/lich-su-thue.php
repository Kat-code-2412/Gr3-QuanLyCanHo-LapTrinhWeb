<?php

declare(strict_types=1);

$isExport = ($_GET['export'] ?? '') === 'csv';
if ($isExport) {
    ob_start();
}

$title = 'Báo cáo Lịch sử thuê - Quản lý Căn dịch vụ';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';

function validDate(?string $d): ?string
{
    if (!$d) return null;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : null;
}

$soPhong = trim($_GET['so_phong'] ?? '');
$tenKhach = trim($_GET['ten_khach'] ?? '');
$validStatuses = ['Đang hiệu lực', 'Đã thanh lý', 'Hết hạn'];
$trangThai = in_array($_GET['trang_thai'] ?? '', $validStatuses, true) ? $_GET['trang_thai'] : '';
$tuNgay = validDate($_GET['tu_ngay'] ?? null);
$denNgay = validDate($_GET['den_ngay'] ?? null);

$whereClauses = [];
$params = [];

if ($soPhong !== '') { $whereClauses[] = 'SoPhong LIKE ?'; $params[] = '%' . $soPhong . '%'; }
if ($tenKhach !== '') { $whereClauses[] = 'TenKhachThue LIKE ?'; $params[] = '%' . $tenKhach . '%'; }
if ($trangThai !== '') { $whereClauses[] = 'TrangThaiHopDong = ?'; $params[] = $trangThai; }
if ($tuNgay !== null) { $whereClauses[] = 'NgayBatDau >= ?'; $params[] = $tuNgay; }
if ($denNgay !== null) { $whereClauses[] = 'NgayBatDau <= ?'; $params[] = $denNgay; }

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Xuất CSV - lấy toàn bộ kết quả khớp filter, không giới hạn phân trang
if ($isExport) {
    $stmt = $pdo->prepare("SELECT * FROM View_LichSuThueCanHo $whereSql ORDER BY NgayBatDau DESC");
    $stmt->execute($params);
    $exportRows = $stmt->fetchAll();

    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lich-su-thue.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Phòng', 'Loại', 'Khách thuê', 'SĐT', 'Ngày bắt đầu', 'Ngày kết thúc', 'Giá thuê thỏa thuận', 'Trạng thái HĐ']);
    foreach ($exportRows as $r) {
        fputcsv($out, [$r['SoPhong'], $r['TenLoai'], $r['TenKhachThue'], $r['SoDienThoai'], $r['NgayBatDau'], $r['NgayKetThuc'], $r['GiaThueThoa'], $r['TrangThaiHopDong']]);
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

$qs = http_build_query(array_filter(['so_phong' => $soPhong, 'ten_khach' => $tenKhach, 'trang_thai' => $trangThai, 'tu_ngay' => $tuNgay, 'den_ngay' => $denNgay]));
$qs = $qs !== '' ? $qs . '&' : '';
?>

<div class="page-header">
    <div><h1 class="page-title">Báo cáo Lịch sử thuê</h1></div>
    <div><a href="?<?= $qs ?>export=csv" class="btn btn-outline">⬇️ Xuất CSV</a></div>
</div>

<div class="filter-card">
    <form method="GET" action="" class="filter-form">
        <div class="filter-group">
            <label for="so_phong">Số phòng</label>
            <input type="text" id="so_phong" name="so_phong" class="form-control" value="<?= e($soPhong) ?>">
        </div>
        <div class="filter-group">
            <label for="ten_khach">Tên khách</label>
            <input type="text" id="ten_khach" name="ten_khach" class="form-control" value="<?= e($tenKhach) ?>">
        </div>
        <div class="filter-group">
            <label for="trang_thai">Trạng thái HĐ</label>
            <select id="trang_thai" name="trang_thai" class="form-control">
                <option value="">-- Tất cả --</option>
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
        <div class="filter-group" style="flex: 0 0 auto;">
            <button type="submit" class="btn btn-primary">🔍 Lọc</button>
            <a href="?" class="btn btn-outline">Bỏ lọc</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Kết quả (<?= $totalRows ?> hợp đồng)</h3></div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><p>Không có hợp đồng nào phù hợp điều kiện lọc.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Phòng</th><th>Loại</th><th>Khách thuê</th><th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th><th>Giá thuê</th><th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><span style="font-weight: 600; color: var(--primary-color);">Phòng <?= e($row['SoPhong']) ?></span></td>
                                <td><?= e($row['TenLoai']) ?></td>
                                <td><?= e($row['TenKhachThue']) ?><br><small style="color: var(--text-muted);"><?= e($row['SoDienThoai']) ?></small></td>
                                <td><?= formatDate($row['NgayBatDau']) ?></td>
                                <td><?= formatDate($row['NgayKetThuc']) ?></td>
                                <td><strong><?= formatMoney($row['GiaThueThoa']) ?></strong></td>
                                <td><?= renderStatusBadge($row['TrangThaiHopDong']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= $qs ?>page=<?= $i ?>" class="<?= ($i === $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>