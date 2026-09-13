<?php

declare(strict_types=1);

$title = 'Quản lý Hợp đồng Thuê Căn Hộ';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';

$keyword = trim($_GET['keyword'] ?? '');
$trangThaiFilter = trim($_GET['trang_thai'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($keyword !== '') {
    $whereClauses[] = '(kt.HoTen LIKE ? OR kt.CCCD LIKE ? OR kt.SoDienThoai LIKE ? OR ch.SoPhong LIKE ? OR hp.MaHopDong LIKE ?)';
    $k = '%' . $keyword . '%';
    $params = [$k, $k, $k, $k, $k];
}

if ($trangThaiFilter !== '') {
    $whereClauses[] = 'hp.TrangThai = ?';
    $params[] = $trangThaiFilter;
}

// Giới hạn hợp đồng theo tòa nhà nhân viên quản lý
$bldCond = buildStaffBuildingCondition('ch.DiaChi');
if ($bldCond['sql'] !== '1=1') {
    $whereClauses[] = $bldCond['sql'];
    foreach ($bldCond['params'] as $bp) {
        $params[] = $bp;
    }
}

$whereSql = (!empty($whereClauses)) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Đếm tổng số hợp đồng
$countSql = "SELECT COUNT(*) 
             FROM HopDong hp
             JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
             JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
             $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Lấy danh sách hợp đồng JOIN CanHo, LoaiCanHo, KhachThue, NhanVien
$sql = "SELECT hp.*, ch.SoPhong, ch.DienTich, ch.DiaChi, 
               COALESCE(NULLIF(hp.GiaDien, 0), ch.GiaDien) AS GiaDien, 
               COALESCE(NULLIF(hp.GiaNuoc, 0), ch.GiaNuoc) AS GiaNuoc, 
               kt.HoTen AS TenKhach, kt.SoDienThoai, kt.CCCD, nv.HoTen AS TenNhanVien
        FROM HopDong hp
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        LEFT JOIN NhanVien nv ON hp.MaNV = nv.MaNV
        $whereSql
        ORDER BY hp.MaHopDong DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

$role = currentUserRole();
$baseUrl = url(($role === 'Admin') ? '/admin/hop-dong' : '/user/hop-dong');
$khachThueUrl = url(($role === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue');

// Giữ lại tham số phân trang & bộ lọc cho các nút thao tác
$currentQueryArgs = [];
if ($page > 1) {
    $currentQueryArgs['page'] = $page;
}
if ($keyword !== '') {
    $currentQueryArgs['keyword'] = $keyword;
}
if ($trangThaiFilter !== '') {
    $currentQueryArgs['trang_thai'] = $trangThaiFilter;
}
$linkSuffix = !empty($currentQueryArgs) ? '&' . http_build_query($currentQueryArgs) : '';
?>

<style>
.contract-filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    margin-bottom: 1.5rem;
}
.contract-filter-card .form-control {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.875rem;
    height: 42px;
    box-sizing: border-box;
    transition: all 0.2s ease;
}
.contract-filter-card .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    outline: none;
}
.contract-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}
.contract-card-header {
    padding: 1.15rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
}
.contract-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    text-align: left;
}
.contract-table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.95rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.contract-table td {
    padding: 1rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.contract-table tbody tr {
    transition: background-color 0.15s ease;
}
.contract-table tbody tr:hover {
    background-color: #f8fafc;
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
.btn-action-edit {
    background: #f8fafc;
    color: #334155;
    border-color: #cbd5e1;
}
.btn-action-edit:hover {
    background: #334155;
    color: #ffffff;
    border-color: #334155;
    box-shadow: 0 2px 8px rgba(51, 65, 85, 0.25);
}
.btn-action-danger {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
.btn-action-danger:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
}
.contract-id-pill {
    display: inline-block;
    padding: 0.25rem 0.55rem;
    background: #f1f5f9;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.85rem;
    color: #334155;
}
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Quản Lý Hợp Đồng Thuê Căn Hộ</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 0.9rem; padding: 0.65rem 1.25rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
            <?= svgIcon('file-plus', '', 18) ?>
            <span>Tạo Hợp Đồng Mới</span>
        </a>
    </div>
</div>

<!-- Bộ lọc danh sách hợp đồng -->
<div class="contract-filter-card">
    <form method="GET" action="" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: flex-end;">
        <div class="filter-group" style="flex: 2; min-width: 260px;">
            <label for="keyword" style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem; display: block;">
                Tìm kiếm Hợp đồng...
            </label>
            <div style="position: relative;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex;">
                    <?= svgIcon('search', '', 16) ?>
                </span>
                <input type="text" 
                       id="keyword" 
                       name="keyword" 
                       class="form-control" 
                       style="padding-left: 2.35rem;"
                       placeholder="Tên khách, Số phòng, Địa chỉ, CCCD, SĐT, Mã HĐ..." 
                       value="<?= e($keyword) ?>">
            </div>
        </div>
        <div class="filter-group" style="flex: 1; min-width: 190px;">
            <label for="trang_thai" style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem; display: block;">
                Trạng thái Hợp đồng
            </label>
            <select id="trang_thai" name="trang_thai" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Đang hiệu lực" <?= ($trangThaiFilter === 'Đang hiệu lực') ? 'selected' : '' ?>>Đang hiệu lực</option>
                <option value="Sắp hết hạn" <?= ($trangThaiFilter === 'Sắp hết hạn') ? 'selected' : '' ?>>Sắp hết hạn</option>
                <option value="Đã thanh lý" <?= ($trangThaiFilter === 'Đã thanh lý') ? 'selected' : '' ?>>Đã thanh lý</option>
            </select>
        </div>
        <div class="filter-group" style="flex: 0 0 auto; display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.65rem 1.25rem; border-radius: 8px;">
                <?= svgIcon('filter', '', 16) ?>
                <span>Lọc</span>
            </button>
            <?php if ($keyword !== '' || $trangThaiFilter !== ''): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.65rem 1rem; border-radius: 8px;">
                    <?= svgIcon('rotate-ccw', '', 14) ?>
                    <span>Đặt lại</span>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bảng danh sách hợp đồng -->
<div class="contract-table-card">
    <div class="contract-card-header">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <div style="width: 32px; height: 32px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                <?= svgIcon('file-text', '', 18) ?>
            </div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0;">
                Danh Sách Hợp Đồng Thuê
            </h3>
        </div>
        <span class="badge badge-info" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; font-weight: 600;">
            <?= $totalRows ?> hợp đồng
        </span>
    </div>
    <div style="padding: 0;">
        <?php if (empty($contracts)): ?>
            <div class="empty-state" style="text-align: center; padding: 3rem 1.5rem; color: #64748b;">
                <div style="display: flex; justify-content: center; margin-bottom: 0.5rem; color: #94a3b8;">
                    <?= svgIcon('file-text', '', 40) ?>
                </div>
                <p style="margin: 0; font-size: 0.95rem;">Không tìm thấy hợp đồng nào phù hợp điều kiện.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="contract-table">
                    <thead>
                        <tr>
                            <th>MÃ HĐ</th>
                            <th>KHÁCH THUÊ</th>
                            <th>ĐỊA CHỈ & PHÒNG</th>
                            <th>THỜI HẠN THUÊ</th>
                            <th>GIÁ THUÊ & CỌC</th>
                            <th>ĐƠN GIÁ ĐIỆN / NƯỚC</th>
                            <th>FILE HỢP ĐỒNG</th>
                            <th>TRẠNG THÁI</th>
                            <th style="text-align: center;">THAO TÁC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $item): ?>
                            <tr>
                                <td>
                                    <span class="contract-id-pill">#<?= e((string)$item['MaHopDong']) ?></span>
                                </td>
                                <td>
                                    <div>
                                        <a href="<?= $khachThueUrl ?>/detail.php?id=<?= $item['MaKhach'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none; font-size: 0.95rem; display: inline-block;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#0f172a'">
                                            <?= e($item['TenKhach']) ?>
                                        </a>
                                        <div style="color: #64748b; font-size: 0.8rem; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.2rem;">
                                            <?= svgIcon('phone', '', 12) ?> <?= e($item['SoDienThoai']) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 700; color: #2563eb; font-size: 0.95rem;">
                                            <?= svgIcon('building', '', 14) ?> Phòng <?= e($item['SoPhong'] ?? ('#' . $item['MaCanHo'])) ?>
                                        </span>
                                        <div style="color: #64748b; font-size: 0.8rem; margin-top: 0.15rem; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($item['DiaChi'] ?: ('Căn hộ #' . $item['MaCanHo'])) ?>">
                                            <?= e($item['DiaChi'] ?: ('Mã căn: #' . $item['MaCanHo'])) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; white-space: nowrap;">
                                        <div style="color: #334155; font-weight: 600;">
                                            <?= formatDate($item['NgayBatDau']) ?>
                                        </div>
                                        <div style="color: #64748b; font-size: 0.78rem; display: flex; align-items: center; gap: 0.3rem; margin-top: 0.1rem;">
                                            <span>&rarr; đến</span>
                                            <strong style="color: <?= (strtotime($item['NgayKetThuc']) < time() + 30*86400) ? '#e11d48' : '#475569' ?>;"><?= formatDate($item['NgayKetThuc']) ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="white-space: nowrap;">
                                        <div style="font-size: 0.875rem;">
                                            <span style="color: #64748b; font-size: 0.78rem;">Giá:</span>
                                            <strong style="color: #0f172a; font-weight: 700;"><?= formatMoney($item['GiaThueThoaThuan']) ?></strong><span style="color: #64748b; font-size: 0.78rem;">/th</span>
                                        </div>
                                        <div style="font-size: 0.85rem; margin-top: 0.2rem;">
                                            <span style="color: #64748b; font-size: 0.78rem;">Cọc:</span>
                                            <span style="color: #059669; font-weight: 600;"><?= formatMoney($item['TienCoc']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="white-space: nowrap; font-size: 0.82rem; color: #475569;">
                                        <div>Điện: <strong style="color: #0f172a;"><?= formatMoney(normalizeServiceFee($item['GiaDien'] ?? 3800, 'dien')) ?>/kWh</strong></div>
                                        <div style="margin-top: 0.15rem;">Nước: <strong style="color: #0284c7;"><?= formatMoney(normalizeServiceFee($item['GiaNuoc'] ?? 100000, 'nuoc')) ?>/th</strong></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($item['FileHopDong'])): ?>
                                        <a href="<?= url('/' . ltrim($item['FileHopDong'], '/')) ?>" target="_blank" class="btn-action btn-action-view" style="font-size: 0.78rem; padding: 0.3rem 0.6rem;">
                                            <?= svgIcon('file-text', '', 13) ?>
                                            <span>Xem file</span>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.82rem; font-style: italic;">Chưa tải lên</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= renderStatusBadge($item['TrangThai']) ?></td>
                                <td style="text-align: center;">
                                    <div class="actions-cell" style="display: flex; gap: 0.35rem; justify-content: center; align-items: center; white-space: nowrap;">
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= $item['MaHopDong'] ?>" 
                                           class="btn-action btn-action-view" 
                                           title="Xem chi tiết hợp đồng">
                                            <?= svgIcon('eye', '', 14) ?>
                                            <span>Xem</span>
                                        </a>
                                        <a href="<?= $baseUrl ?>/edit.php?id=<?= $item['MaHopDong'] ?>" 
                                           class="btn-action btn-action-edit" 
                                           title="Chỉnh sửa hợp đồng">
                                            <?= svgIcon('edit', '', 14) ?>
                                            <span>Sửa</span>
                                        </a>
                                        <?php if (in_array($item['TrangThai'], ['Đang hiệu lực', 'Sắp hết hạn'], true)): ?>
                                            <a href="<?= $baseUrl ?>/thanh-ly.php?id=<?= $item['MaHopDong'] ?><?= $linkSuffix ?>" 
                                               class="btn-action btn-action-danger" 
                                               onclick="return confirm('Bạn có chắc chắn muốn Thanh Lý hợp đồng #<?= $item['MaHopDong'] ?>? Phòng sẽ chuyển về trạng thái Trống.');" 
                                               title="Thanh lý hợp đồng">
                                                <?= svgIcon('door', '', 14) ?>
                                                <span>Thanh lý</span>
                                            </a>
                                        <?php endif; ?>
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

<!-- Phân trang -->
<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.3rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?keyword=<?= urlencode($keyword) ?>&trang_thai=<?= urlencode($trangThaiFilter) ?>&page=<?= $i ?>" class="<?= ($i === $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
