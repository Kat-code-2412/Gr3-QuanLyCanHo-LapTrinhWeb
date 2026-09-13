<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/module2_helpers.php';
requirePermission('CANHO_MANAGE');

$isAdmin = (currentUserRole() === 'Admin');
$title = 'Hệ Thống Căn Hộ';

$keyword = trim((string)($_GET['keyword'] ?? ''));
$maLoai = (int)($_GET['MaLoai'] ?? 0);
$trangThai = (string)($_GET['TrangThai'] ?? '');
$diaChi = trim((string)($_GET['DiaChi'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['perPage'] ?? 10);
$perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($keyword !== '') {
    $where[] = '(c.SoPhong LIKE :kw1 OR CAST(c.MaCanHo AS CHAR) LIKE :kw2)';
    $params[':kw1'] = "%$keyword%";
    $params[':kw2'] = "%$keyword%";
}
if ($maLoai > 0) {
    $where[] = 'c.MaLoai = :loai';
    $params[':loai'] = $maLoai;
}
if (in_array($trangThai, ['Trống', 'Đang thuê', 'Bảo trì'], true)) {
    $where[] = 'c.TrangThai = :tt';
    $params[':tt'] = $trangThai;
}
if ($diaChi !== '') {
    $where[] = 'c.DiaChi = :dc';
    $params[':dc'] = $diaChi;
}

// Phân quyền theo tòa nhà (Chỉ hiển thị dữ liệu thuộc tòa nhà nhân viên phụ trách)
$staffAssigned = getStaffAssignedBuildings();
if ($staffAssigned !== null) {
    if (empty($staffAssigned)) {
        $where[] = '1=0';
    } else {
        $inPlaceholders = [];
        foreach ($staffAssigned as $idx => $assignedAddr) {
            $ph = ":assigned_bld_$idx";
            $inPlaceholders[] = $ph;
            $params[$ph] = $assignedAddr;
        }
        $where[] = 'c.DiaChi IN (' . implode(',', $inPlaceholders) . ')';
    }
    $buildings = $staffAssigned;
} else {
    $buildings = $pdo->query("SELECT DISTINCT DiaChi FROM CanHo WHERE DiaChi IS NOT NULL AND DiaChi <> '' ORDER BY DiaChi")->fetchAll(PDO::FETCH_COLUMN);
}

$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM CanHo c $ws");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT c.*, l.TenLoai, 
    (SELECT COUNT(*) FROM CanHo_Anh a WHERE a.MaCanHo = c.MaCanHo) AS SoAnh, 
    (SELECT a.DuongDan FROM CanHo_Anh a WHERE a.MaCanHo = c.MaCanHo AND a.LaAnhDaiDien = 1 ORDER BY a.MaAnh LIMIT 1) AS AnhDaiDien 
    FROM CanHo c 
    JOIN LoaiCanHo l ON l.MaLoai = c.MaLoai 
    $ws 
    ORDER BY c.MaCanHo DESC 
    LIMIT :limit OFFSET :offset");

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$types = $pdo->query('SELECT MaLoai, TenLoai FROM LoaiCanHo ORDER BY TenLoai')->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.canho-filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    margin-bottom: 1.5rem;
}
.canho-filter-card .form-control {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.875rem;
    height: 42px;
    box-sizing: border-box;
    transition: all 0.2s ease;
}
.canho-filter-card .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    outline: none;
}
.canho-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}
.canho-card-header {
    padding: 1.15rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
}
.canho-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    text-align: left;
}
.canho-table th {
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
.canho-table td {
    padding: 0.95rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.canho-table tbody tr {
    transition: background-color 0.15s ease;
}
.canho-table tbody tr:hover {
    background-color: #f8fafc;
}
.canho-thumb-wrap {
    position: relative;
    width: 72px;
    height: 52px;
    border-radius: 8px;
    overflow: hidden;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
}
.canho-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.canho-thumb-wrap:hover .canho-thumb-img {
    transform: scale(1.1);
}
.canho-thumb-badge {
    position: absolute;
    bottom: 2px;
    right: 2px;
    background: rgba(15, 23, 42, 0.8);
    color: #ffffff;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.1rem 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.2rem;
}
.canho-id-pill {
    display: inline-block;
    padding: 0.25rem 0.55rem;
    background: #f1f5f9;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.85rem;
    color: #334155;
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
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 class="page-title" style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">Hệ Thống Căn Hộ</h1>
    </div>
    <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
        <a href="create.php?mode=room" class="btn <?= $isAdmin ? '' : 'btn-primary' ?>" style="<?= $isAdmin ? 'background: #eff6ff; color: #1d4ed8; border: 1.5px solid #bfdbfe; font-weight: 700;' : 'font-weight: 700;' ?>">
            <?= svgIcon('door', '', 16) ?>
            <span>Thêm Mã Phòng</span>
        </a>
        <?php if ($isAdmin): ?>
            <a href="create.php" class="btn btn-primary">
                <?= svgIcon('plus', '', 16) ?>
                <span>Thêm Căn Hộ Mới</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Bộ lọc danh sách căn hộ -->
<div class="canho-filter-card">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        <div style="flex: 2; min-width: 220px;">
            <label style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem; display: block;">
                Tìm mã/số phòng
            </label>
            <div style="position: relative;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex;">
                    <?= svgIcon('search', '', 16) ?>
                </span>
                <input type="text" 
                       name="keyword" 
                       class="form-control" 
                       style="padding-left: 2.35rem;"
                       placeholder="Nhập số phòng cần tìm..." 
                       value="<?= e($keyword) ?>">
            </div>
        </div>

        <div style="flex: 1.5; min-width: 180px;">
            <label style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem; display: block;">
                Tòa nhà / Địa chỉ
            </label>
            <select name="DiaChi" class="form-control">
                <option value="">Tất cả tòa nhà</option>
                <?php foreach ($buildings as $b): ?>
                    <option value="<?= e($b) ?>" <?= ($diaChi === $b) ? 'selected' : '' ?>>
                        <?= e(explode('-', $b)[0] ?? $b) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem; display: block;">
                Loại căn
            </label>
            <select name="MaLoai" class="form-control">
                <option value="0">Tất cả</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t['MaLoai'] ?>" <?= ($maLoai === (int)$t['MaLoai']) ? 'selected' : '' ?>>
                        <?= e($t['TenLoai']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem; display: block;">
                Trạng thái
            </label>
            <select name="TrangThai" class="form-control">
                <option value="">Tất cả</option>
                <?php foreach (['Trống', 'Đang thuê', 'Bảo trì'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= ($trangThai === $s) ? 'selected' : '' ?>>
                        <?= e($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 0.7; min-width: 90px;">
            <label style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.4rem; display: block;">
                Số dòng
            </label>
            <select name="perPage" class="form-control">
                <?php foreach ([10, 20, 50, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= ($perPage === $n) ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 0 0 auto; display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.65rem 1.25rem; border-radius: 8px;">
                <?= svgIcon('filter', '', 16) ?>
                <span>Lọc</span>
            </button>
            <?php if ($keyword !== '' || $diaChi !== '' || $maLoai > 0 || $trangThai !== '' || $perPage !== 10): ?>
                <a href="index.php" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.65rem 1rem; border-radius: 8px;">
                    <?= svgIcon('rotate-ccw', '', 14) ?>
                    <span>Đặt lại</span>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bảng danh sách căn hộ -->
<div class="canho-table-card">
    <div class="canho-card-header">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <div style="width: 32px; height: 32px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                <?= svgIcon('building', '', 18) ?>
            </div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0;">
                Danh Sách Căn Hộ
            </h3>
        </div>
        <span class="badge badge-info" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; font-weight: 600;">
            <?= $total ?> căn hộ
        </span>
    </div>

    <div style="padding: 0;">
        <?php if (empty($items)): ?>
            <div class="empty-state" style="text-align: center; padding: 3rem 1.5rem; color: #64748b;">
                <div style="display: flex; justify-content: center; margin-bottom: 0.5rem; color: #94a3b8;">
                    <?= svgIcon('building', '', 40) ?>
                </div>
                <p style="margin: 0; font-size: 0.95rem;">Không tìm thấy căn hộ phù hợp với điều kiện tìm kiếm.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="canho-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ẢNH</th>
                            <th>MÃ</th>
                            <th>SỐ PHÒNG</th>
                            <th>TÒA NHÀ / ĐỊA CHỈ</th>
                            <th>LOẠI</th>
                            <th>DIỆN TÍCH</th>
                            <th>GIÁ THUÊ</th>
                            <th>TRẠNG THÁI</th>
                            <th style="text-align: center;">THAO TÁC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="canho-thumb-wrap">
                                        <?php if (!empty($item['AnhDaiDien'])): ?>
                                            <img class="canho-thumb-img" src="<?= e(url('/' . ltrim($item['AnhDaiDien'], '/'))) ?>" alt="Ảnh căn <?= e($item['SoPhong']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                                <?= svgIcon('building', '', 20) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['SoAnh']) && (int)$item['SoAnh'] > 1): ?>
                                            <div class="canho-thumb-badge" title="<?= (int)$item['SoAnh'] ?> ảnh">
                                                <?= svgIcon('camera', '', 10) ?> <?= (int)$item['SoAnh'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="canho-id-pill">#<?= (int)$item['MaCanHo'] ?></span>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: #2563eb; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                        Phòng <?= e($item['SoPhong']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; color: #334155; line-height: 1.45; max-width: 320px;">
                                        <?= e($item['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary" style="font-weight: 600; font-size: 0.8rem;">
                                        <?= e($item['TenLoai']) ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <strong style="color: #0f172a; font-size: 0.9rem;"><?= number_format((float)$item['DienTich'], 0) ?></strong> 
                                    <span style="color: #64748b; font-size: 0.8rem;">m²</span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <strong style="color: #0f172a; font-weight: 700; font-size: 0.95rem;"><?= formatMoney((float)$item['GiaThue']) ?></strong><span style="color: #64748b; font-size: 0.78rem;">/th</span>
                                </td>
                                <td>
                                    <?= renderStatusBadge($item['TrangThai']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="actions-cell" style="display: flex; gap: 0.35rem; justify-content: center; align-items: center; white-space: nowrap;">
                                        <a href="detail.php?id=<?= (int)$item['MaCanHo'] ?>" 
                                           class="btn-action btn-action-view" 
                                           title="Xem chi tiết căn hộ">
                                            <?= svgIcon('eye', '', 14) ?>
                                            <span>Xem</span>
                                        </a>
                                        <a href="edit.php?id=<?= (int)$item['MaCanHo'] ?>" 
                                           class="btn-action btn-action-edit" 
                                           title="Chỉnh sửa căn hộ">
                                            <?= svgIcon('edit', '', 14) ?>
                                            <span>Sửa</span>
                                        </a>
                                        <form method="POST" action="delete.php" style="display: inline; margin: 0;" onsubmit="return confirm('Bạn có chắc muốn xóa căn hộ này? Chỉ căn hộ chưa có hợp đồng hoặc yêu cầu bảo trì mới được xóa.');">
                                            <input type="hidden" name="csrf_token" value="<?= e(module2_csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$item['MaCanHo'] ?>">
                                            <button class="btn-action btn-action-danger" type="submit" title="Xóa căn hộ">
                                                <?= svgIcon('trash', '', 14) ?>
                                                <span>Xóa</span>
                                            </button>
                                        </form>
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

<?= module2_pagination($page, $totalPages, ['keyword' => $keyword, 'DiaChi' => $diaChi, 'MaLoai' => $maLoai, 'TrangThai' => $trangThai, 'perPage' => $perPage]) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>