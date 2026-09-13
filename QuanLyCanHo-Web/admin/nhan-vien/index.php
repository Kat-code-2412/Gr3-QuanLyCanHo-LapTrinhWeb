<?php

declare(strict_types=1);

$title = 'Quản Lý Tài Khoản Nhân Viên';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url('/admin/nhan-vien');
$currentUserId = (int)($_SESSION['MaNV'] ?? 0);

// Xử lý đổi nhanh trạng thái tài khoản (Đang làm việc / Nghỉ việc)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verifyCsrf();
    $targetId = (int)($_POST['id'] ?? 0);
    $newStatus = trim((string)($_POST['trang_thai'] ?? ''));

    if ($targetId === $currentUserId && $newStatus === 'Nghỉ việc') {
        setFlash('error', 'Bạn không thể tự khóa tài khoản của chính mình khi đang đăng nhập!');
    } elseif ($targetId > 0 && in_array($newStatus, ['Đang làm việc', 'Nghỉ việc'], true)) {
        $stmtUp = $pdo->prepare('UPDATE NhanVien SET TrangThai = ? WHERE MaNV = ?');
        $stmtUp->execute([$newStatus, $targetId]);
        setFlash('success', 'Đã cập nhật trạng thái nhân viên #' . $targetId . ' sang "' . $newStatus . '".');
    }
    redirect('/admin/nhan-vien/index.php');
}

// Thống kê nhanh
$totalCount = (int)$pdo->query('SELECT COUNT(*) FROM NhanVien')->fetchColumn();
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM NhanVien WHERE TrangThai = 'Đang làm việc'")->fetchColumn();
$inactiveCount = (int)$pdo->query("SELECT COUNT(*) FROM NhanVien WHERE TrangThai = 'Nghỉ việc'")->fetchColumn();

// Lấy tham số tìm kiếm, lọc và phân trang
$keyword = trim((string)($_GET['keyword'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($keyword !== '') {
    $whereClauses[] = '(HoTen LIKE ? OR TenDangNhap LIKE ? OR Email LIKE ? OR SoDienThoai LIKE ?)';
    $kw = '%' . $keyword . '%';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

if ($roleFilter !== '') {
    $whereClauses[] = 'VaiTro = ?';
    $params[] = $roleFilter;
}

if ($statusFilter !== '') {
    $whereClauses[] = 'TrangThai = ?';
    $params[] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Đếm tổng số bản ghi phù hợp
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM NhanVien $whereSql");
$countStmt->execute($params);
$totalMatching = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalMatching / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Truy vấn danh sách nhân viên
$sql = "SELECT * FROM NhanVien $whereSql ORDER BY MaNV ASC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Lấy danh sách tòa nhà phân công của nhân viên
$buildingAssignments = [];
$bldStmt = $pdo->query('SELECT MaNV, DiaChi FROM nhanvien_toanha ORDER BY DiaChi ASC');
while ($bRow = $bldStmt->fetch(PDO::FETCH_ASSOC)) {
    $buildingAssignments[(int)$bRow['MaNV']][] = $bRow['DiaChi'];
}
?>

<style>
.emp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.emp-breadcrumb {
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
    margin-bottom: 0.35rem;
}
.emp-breadcrumb a {
    color: var(--text-muted, #64748b);
    text-decoration: none;
}
.emp-breadcrumb a:hover {
    color: var(--primary-color, #2563eb);
}
.emp-title {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.02em;
}

/* Stat cards */
.emp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.emp-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.emp-stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transform: translateY(-2px);
}
.emp-stat-label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 0.35rem;
}
.emp-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.2;
}
.emp-stat-unit {
    font-size: 0.85rem;
    font-weight: 500;
    color: #94a3b8;
    margin-left: 4px;
}
.emp-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Filter card */
.emp-filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    margin-bottom: 1.5rem;
}
.emp-filter-body {
    padding: 1.25rem 1.5rem;
}
.emp-filter-grid {
    display: grid;
    grid-template-columns: 2.2fr 1.2fr 1.2fr auto;
    gap: 1rem;
    align-items: flex-end;
}
@media (max-width: 992px) {
    .emp-filter-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 640px) {
    .emp-filter-grid {
        grid-template-columns: 1fr;
    }
}
.emp-filter-group label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.4rem;
}
.emp-filter-group .form-control {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 0.52rem 0.75rem;
    font-size: 0.88rem;
    background-color: #ffffff;
    transition: all 0.2s;
}
.emp-filter-group .form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.emp-filter-actions {
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
}

/* Table Card */
.emp-table-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    background: #ffffff;
}
.emp-table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.emp-table td {
    padding: 0.9rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.emp-table tbody tr:hover {
    background-color: #f8fafc;
}

/* Action Buttons */
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.38rem 0.65rem;
    border-radius: 6px;
    font-size: 0.8rem;
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

<div class="emp-header">
    <div>
        <div class="emp-breadcrumb">
            <a href="<?= url('/admin/dashboard.php') ?>">Hệ Thống</a> / <span>Quản Lý Tài Khoản Nhân Viên</span>
        </div>
        <h1 class="emp-title">Quản Lý Tài Khoản Nhân Viên</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 0.55rem 1.1rem; border-radius: 8px;">
            <?= svgIcon('plus', '', 15) ?>
            <span>Thêm nhân viên</span>
        </a>
    </div>
</div>

<!-- 3 THẺ THỐNG KÊ -->
<div class="emp-stats-grid">
    <div class="emp-stat-card">
        <div>
            <div class="emp-stat-label">Tổng số tài khoản</div>
            <div class="emp-stat-value" style="color: #1e293b;">
                <?= number_format($totalCount) ?>
                <span class="emp-stat-unit">tài khoản</span>
            </div>
        </div>
        <div class="emp-stat-icon" style="background: #eff6ff; color: #2563eb;">
            <?= svgIcon('users', '', 22) ?>
        </div>
    </div>

    <div class="emp-stat-card">
        <div>
            <div class="emp-stat-label">Đang làm việc</div>
            <div class="emp-stat-value" style="color: #16a34a;">
                <?= number_format($activeCount) ?>
                <span class="emp-stat-unit">tài khoản</span>
            </div>
        </div>
        <div class="emp-stat-icon" style="background: #f0fdf4; color: #16a34a;">
            <?= svgIcon('check', '', 22) ?>
        </div>
    </div>

    <div class="emp-stat-card">
        <div>
            <div class="emp-stat-label">Nghỉ việc / Khóa</div>
            <div class="emp-stat-value" style="color: #dc2626;">
                <?= number_format($inactiveCount) ?>
                <span class="emp-stat-unit">tài khoản</span>
            </div>
        </div>
        <div class="emp-stat-icon" style="background: #fef2f2; color: #dc2626;">
            <?= svgIcon('lock', '', 22) ?>
        </div>
    </div>
</div>

<!-- BỘ LỌC VÀ TÌM KIẾM -->
<div class="emp-filter-card">
    <div class="emp-filter-body">
        <form method="GET" action="" class="emp-filter-grid">
            <div class="emp-filter-group">
                <label for="keyword">Tìm kiếm</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex;">
                        <?= svgIcon('search', '', 15) ?>
                    </span>
                    <input type="text" 
                           id="keyword" 
                           name="keyword" 
                           class="form-control" 
                           placeholder="Họ tên, tài khoản, SĐT, email..." 
                           value="<?= e($keyword) ?>"
                           style="padding-left: 35px;">
                </div>
            </div>

            <div class="emp-filter-group">
                <label for="role">Vai trò</label>
                <select name="role" id="role" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Tất cả vai trò --</option>
                    <option value="NhanVien" <?= ($roleFilter === 'NhanVien') ? 'selected' : '' ?>>Nhân Viên</option>
                    <option value="Admin" <?= ($roleFilter === 'Admin') ? 'selected' : '' ?>>Chủ Nhà</option>
                </select>
            </div>

            <div class="emp-filter-group">
                <label for="status">Trạng thái</label>
                <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="Đang làm việc" <?= ($statusFilter === 'Đang làm việc') ? 'selected' : '' ?>>Đang làm việc</option>
                    <option value="Nghỉ việc" <?= ($statusFilter === 'Nghỉ việc') ? 'selected' : '' ?>>Nghỉ việc</option>
                </select>
            </div>

            <div class="emp-filter-actions">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 0.52rem 1.1rem; border-radius: 8px;">
                    <?= svgIcon('filter', '', 14) ?>
                    <span>Lọc dữ liệu</span>
                </button>
                <?php if ($keyword !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                    <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="font-weight: 600; padding: 0.52rem 1rem; border-radius: 8px;">Xóa lọc</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- BẢNG DANH SÁCH NHÂN VIÊN -->
<div class="emp-table-card">
    <div class="card-header" style="background: #ffffff; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #1e293b;">
            Danh Sách Tài Khoản
            <span style="font-size: 0.85rem; font-weight: 500; color: #64748b; margin-left: 0.4rem;">(<?= $totalMatching ?> tài khoản)</span>
        </h3>
    </div>
    <div class="table-responsive">
        <table class="table emp-table" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th style="width: 70px;">Mã NV</th>
                    <th style="min-width: 180px;">Họ và tên</th>
                    <th>Tên đăng nhập</th>
                    <th>Vai trò</th>
                    <th style="min-width: 220px;">Tòa nhà quản lý</th>
                    <th>Số điện thoại</th>
                    <th>Email</th>
                    <th>Trạng thái</th>
                    <th style="text-align: right; min-width: 170px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 3rem 1rem; color: #64748b;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem; color: #94a3b8;"><?= svgIcon('info', '', 36) ?></div>
                            <p style="margin: 0; font-weight: 500;">Không tìm thấy nhân viên nào phù hợp với điều kiện tìm kiếm.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $row): ?>
                        <?php $isSelf = ($currentUserId === (int)$row['MaNV']); ?>
                        <tr>
                            <td><strong style="color: #64748b;">#<?= (int)$row['MaNV'] ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: <?= ($row['VaiTro'] === 'Admin') ? '#e0e7ff' : '#f1f5f9' ?>; color: <?= ($row['VaiTro'] === 'Admin') ? '#4338ca' : '#475569' ?>; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; border: 1px solid <?= ($row['VaiTro'] === 'Admin') ? '#c7d2fe' : '#e2e8f0' ?>;">
                                        <?= e(getInitials($row['HoTen'])) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b; font-size: 0.92rem; white-space: nowrap;">
                                            <?= e($row['HoTen']) ?>
                                            <?php if ($isSelf): ?>
                                                <span style="display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 0.72rem; font-weight: 700; background-color: #dbeafe; color: #1d4ed8; margin-left: 4px;">Bạn</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.83rem; background: #f8fafc; color: #0284c7; border: 1px solid #e2e8f0; padding: 2px 7px; border-radius: 5px;">
                                    <?= e($row['TenDangNhap']) ?>
                                </code>
                            </td>
                            <td>
                                <?php if ($row['VaiTro'] === 'Admin'): ?>
                                    <span style="display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; white-space: nowrap;">
                                        Chủ Nhà
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; white-space: nowrap;">
                                        Nhân Viên
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['VaiTro'] === 'Admin'): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; white-space: nowrap;">
                                        <?= svgIcon('building', '', 12) ?>
                                        Toàn bộ hệ thống
                                    </span>
                                <?php else: ?>
                                    <?php $assigned = $buildingAssignments[(int)$row['MaNV']] ?? []; ?>
                                    <?php if (!empty($assigned)): ?>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <?php foreach ($assigned as $bld): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 6px; font-size: 0.76rem; font-weight: 500; background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; max-width: 260px;" title="<?= e($bld) ?>">
                                                    <span style="color: #3b82f6; flex-shrink: 0; display: flex;"><?= svgIcon('map-pin', '', 12) ?></span>
                                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($bld) ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; color: #dc2626; font-size: 0.78rem; font-weight: 500;">
                                            <?= svgIcon('alert-triangle', '', 12) ?>
                                            <em>Chưa phân công</em>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['SoDienThoai'])): ?>
                                    <span style="font-size: 0.88rem; color: #334155; font-weight: 500;">
                                        <?= e($row['SoDienThoai']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['Email'])): ?>
                                    <span style="font-size: 0.85rem; color: #475569;">
                                        <?= e($row['Email']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isSelf): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; white-space: nowrap;" title="Tài khoản bạn đang đăng nhập">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>
                                        Đang làm việc
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$row['MaNV'] ?>">
                                        <select name="trang_thai" 
                                                class="form-control" 
                                                style="padding: 0.3rem 0.65rem; font-size: 0.82rem; font-weight: 600; border-radius: 6px; cursor: pointer; border-color: <?= ($row['TrangThai'] === 'Đang làm việc') ? '#a7f3d0' : '#fecaca' ?>; background-color: <?= ($row['TrangThai'] === 'Đang làm việc') ? '#f0fdf4' : '#fef2f2' ?>; color: <?= ($row['TrangThai'] === 'Đang làm việc') ? '#166534' : '#991b1b' ?>;"
                                                onchange="this.form.submit()">
                                            <option value="Đang làm việc" <?= ($row['TrangThai'] === 'Đang làm việc') ? 'selected' : '' ?>>Đang làm việc</option>
                                            <option value="Nghỉ việc" <?= ($row['TrangThai'] === 'Nghỉ việc') ? 'selected' : '' ?>>Nghỉ việc</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                                    <?php if ($row['VaiTro'] !== 'Admin'): ?>
                                        <a href="<?= $baseUrl ?>/phan-quyen.php?id=<?= (int)$row['MaNV'] ?>" class="btn-action btn-action-view" title="Cấp quyền truy cập">
                                            <?= svgIcon('shield', '', 13) ?>
                                            <span>Phân quyền</span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= $baseUrl ?>/edit.php?id=<?= (int)$row['MaNV'] ?>" class="btn-action btn-action-edit" title="Chỉnh sửa thông tin">
                                        <?= svgIcon('edit', '', 13) ?>
                                        <span>Sửa</span>
                                    </a>
                                    <?php if ($isSelf): ?>
                                        <button type="button" class="btn-action" style="opacity: 0.4; cursor: not-allowed; background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0;" title="Không thể tự xóa tài khoản đang đăng nhập" disabled>
                                            <?= svgIcon('trash', '', 13) ?>
                                            <span>Xóa</span>
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= $baseUrl ?>/delete.php?id=<?= (int)$row['MaNV'] ?>" 
                                           class="btn-action btn-action-danger"
                                           title="Xóa tài khoản"
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa nhân viên &quot;<?= e($row['HoTen']) ?>&quot;?');">
                                            <?= svgIcon('trash', '', 13) ?>
                                            <span>Xóa</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PHÂN TRANG -->
<?php if ($totalPages > 1): ?>
    <div class="pagination" style="display: flex; justify-content: center; gap: 6px; margin-top: 1.5rem;">
        <?php
        $pageParams = [];
        if ($keyword !== '') $pageParams['keyword'] = $keyword;
        if ($roleFilter !== '') $pageParams['role'] = $roleFilter;
        if ($statusFilter !== '') $pageParams['status'] = $statusFilter;
        ?>

        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query($pageParams + ['page' => $page - 1]) ?>" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; font-weight: 600; font-size: 0.85rem;">&laquo; Trước</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= ($i === $page) ? 'active' : '' ?>" href="?<?= http_build_query($pageParams + ['page' => $i]) ?>" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid <?= ($i === $page) ? '#2563eb' : '#cbd5e1' ?>; background: <?= ($i === $page) ? '#2563eb' : '#ffffff' ?>; color: <?= ($i === $page) ? '#ffffff' : '#334155' ?>; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query($pageParams + ['page' => $page + 1]) ?>" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; font-weight: 600; font-size: 0.85rem;">Sau &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

