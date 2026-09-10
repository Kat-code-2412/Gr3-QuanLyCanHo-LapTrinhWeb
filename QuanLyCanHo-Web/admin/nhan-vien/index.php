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
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Quản Lý Tài Khoản Nhân Viên</h1>
        <p class="page-subtitle">Danh sách tài khoản hệ thống và phân quyền truy cập (Chỉ Admin)</p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary">
            + Thêm nhân viên
        </a>
    </div>
</div>

<!-- 3 THẺ THỐNG KÊ (ĐỒNG BỘ THEO DESIGN CHUNG) -->
<div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 1.5rem;">
    <div class="detail-item" style="border-left: 4px solid var(--primary-color);">
        <div class="detail-label">👥 TỔNG SỐ TÀI KHOẢN</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--primary-color);">
            <?= $totalCount ?> <span style="font-size: 1rem; font-weight: 500; color: #64748b;">tài khoản</span>
        </div>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--success-color);">
        <div class="detail-label">🟢 ĐANG LÀM VIỆC</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--success-color);">
            <?= $activeCount ?> <span style="font-size: 1rem; font-weight: 500; color: #64748b;">tài khoản</span>
        </div>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--danger-color);">
        <div class="detail-label">🔴 NGHỈ VIỆC / KHÓA</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--danger-color);">
            <?= $inactiveCount ?> <span style="font-size: 1rem; font-weight: 500; color: #64748b;">tài khoản</span>
        </div>
    </div>
</div>

<!-- BỘ LỌC VÀ TÌM KIẾM -->
<div class="filter-card mb-3">
    <form method="GET" action="" class="filter-form">
        <div class="filter-group" style="flex: 2; min-width: 250px;">
            <label for="keyword">🔍 Tìm kiếm nhân viên...</label>
            <input type="text" 
                   id="keyword" 
                   name="keyword" 
                   class="form-control" 
                   placeholder="Nhập Họ tên, Tên đăng nhập, Email, SĐT..." 
                   value="<?= e($keyword) ?>">
        </div>

        <div class="filter-group" style="flex: 1; min-width: 160px;">
            <label for="role">Vai trò</label>
            <select name="role" id="role" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả vai trò --</option>
                <option value="Admin" <?= ($roleFilter === 'Admin') ? 'selected' : '' ?>>Admin (Chủ nhà)</option>
                <option value="NhanVien" <?= ($roleFilter === 'NhanVien') ? 'selected' : '' ?>>Nhân viên (NhanVien)</option>
            </select>
        </div>

        <div class="filter-group" style="flex: 1; min-width: 160px;">
            <label for="status">Trạng thái</label>
            <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Đang làm việc" <?= ($statusFilter === 'Đang làm việc') ? 'selected' : '' ?>>Đang làm việc</option>
                <option value="Nghỉ việc" <?= ($statusFilter === 'Nghỉ việc') ? 'selected' : '' ?>>Nghỉ việc</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">Lọc dữ liệu</button>
            <?php if ($keyword !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Xóa lọc</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- BẢNG DANH SÁCH NHÂN VIÊN -->
<div class="card">
    <div class="card-header" style="background-color: #fafafa;">
        <h3 style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary);">
            📋 Danh Sách Tài Khoản (<?= $totalMatching ?>)
        </h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 70px;">Mã NV</th>
                    <th>Họ và tên</th>
                    <th>Tên đăng nhập</th>
                    <th>Vai trò</th>
                    <th>Số điện thoại</th>
                    <th>Email</th>
                    <th>Trạng thái</th>
                    <th style="text-align: right; width: 160px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 2.5rem 1rem; color: var(--text-muted);">
                            Không tìm thấy nhân viên nào phù hợp với điều kiện tìm kiếm.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $row): ?>
                        <?php $isSelf = ($currentUserId === (int)$row['MaNV']); ?>
                        <tr>
                            <td><strong>#<?= (int)$row['MaNV'] ?></strong></td>
                            <td>
                                <strong><?= e($row['HoTen']) ?></strong>
                                <?php if ($isSelf): ?>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; background-color: #dbeafe; color: #1d4ed8; margin-left: 6px;">(Bạn)</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($row['TenDangNhap']) ?></code></td>
                            <td>
                                <?php if ($row['VaiTro'] === 'Admin'): ?>
                                    <span class="badge badge-info">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Nhân viên</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($row['SoDienThoai'] ?: '-') ?></td>
                            <td><?= e($row['Email'] ?: '-') ?></td>
                            <td>
                                <?php if ($isSelf): ?>
                                    <span class="badge badge-success" title="Tài khoản bạn đang đăng nhập">Đang làm việc</span>
                                <?php else: ?>
                                    <form method="POST" action="" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$row['MaNV'] ?>">
                                        <select name="trang_thai" 
                                                class="form-control" 
                                                style="padding: 0.25rem 0.5rem; font-size: 0.8rem; width: auto; font-weight: 600; display: inline-block; border-color: <?= ($row['TrangThai'] === 'Đang làm việc') ? '#a7f3d0' : '#fecaca' ?>; background-color: <?= ($row['TrangThai'] === 'Đang làm việc') ? '#f0fdf4' : '#fef2f2' ?>; color: <?= ($row['TrangThai'] === 'Đang làm việc') ? '#166534' : '#991b1b' ?>;"
                                                onchange="this.form.submit()">
                                            <option value="Đang làm việc" <?= ($row['TrangThai'] === 'Đang làm việc') ? 'selected' : '' ?>>Đang làm việc</option>
                                            <option value="Nghỉ việc" <?= ($row['TrangThai'] === 'Nghỉ việc') ? 'selected' : '' ?>>Nghỉ việc</option>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.35rem;">
                                    <a href="<?= $baseUrl ?>/edit.php?id=<?= (int)$row['MaNV'] ?>" class="btn btn-sm btn-outline">
                                        Sửa
                                    </a>
                                    <?php if ($isSelf): ?>
                                        <button type="button" class="btn btn-sm btn-outline" style="opacity: 0.4; cursor: not-allowed;" title="Không thể xóa tài khoản của chính bạn" disabled>
                                            Xóa
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= $baseUrl ?>/delete.php?id=<?= (int)$row['MaNV'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa nhân viên &quot;<?= e($row['HoTen']) ?>&quot;?');">
                                            Xóa
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
    <div class="pagination">
        <?php
        $pageParams = [];
        if ($keyword !== '') $pageParams['keyword'] = $keyword;
        if ($roleFilter !== '') $pageParams['role'] = $roleFilter;
        if ($statusFilter !== '') $pageParams['status'] = $statusFilter;
        ?>

        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query($pageParams + ['page' => $page - 1]) ?>">&laquo; Trước</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= ($i === $page) ? 'active' : '' ?>" href="?<?= http_build_query($pageParams + ['page' => $i]) ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query($pageParams + ['page' => $page + 1]) ?>">Sau &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
