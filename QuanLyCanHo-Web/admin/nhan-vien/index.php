<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../src/Repositories/NhanVienRepository.php';

$currentUserId = (int)($_SESSION['MaNV'] ?? 0);

// Xử lý đổi nhanh trạng thái tài khoản nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = trim((string)($_POST['trang_thai'] ?? ''));

    if ($id === $currentUserId && $newStatus === 'Nghỉ việc') {
        flash('error', 'Bạn không thể tự chuyển tài khoản của chính mình sang "Nghỉ việc".');
    } elseif ($id > 0 && in_array($newStatus, ['Đang làm việc', 'Nghỉ việc'], true)) {
        $stmtUp = $pdo->prepare('UPDATE NhanVien SET TrangThai = :st WHERE MaNV = :id');
        $stmtUp->execute(['st' => $newStatus, 'id' => $id]);
        flash('success', 'Đã đổi trạng thái tài khoản #' . $id . ' sang "' . $newStatus . '".');
    }
    redirect('index.php');
}

$repo = new NhanVienRepository($pdo);
$keyword = trim((string)($_GET['q'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$result = $repo->all(
    $keyword,
    $roleFilter !== '' ? $roleFilter : null,
    $statusFilter !== '' ? $statusFilter : null,
    $page,
    $perPage
);

$totalPages = max(1, (int)ceil($result['total'] / $result['perPage']));
$currentUserId = (int)($_SESSION['MaNV'] ?? 0);
$title = 'Quản lý nhân viên';
$flashes = pullFlashes();

// Thống kê số lượng theo trạng thái
$cntTotal = (int)$pdo->query('SELECT COUNT(*) FROM NhanVien')->fetchColumn();
$cntActive = (int)$pdo->query("SELECT COUNT(*) FROM NhanVien WHERE TrangThai = 'Đang làm việc'")->fetchColumn();
$cntInactive = (int)$pdo->query("SELECT COUNT(*) FROM NhanVien WHERE TrangThai = 'Nghỉ việc'")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="page-header">
    <div>
        <h1>Quản lý nhân viên</h1>
        <p class="muted">Danh sách tài khoản hệ thống và phân quyền truy cập (Chỉ Admin).</p>
    </div>
    <a class="button" href="create.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -3px; margin-right: 4px;">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Thêm nhân viên mới
    </a>
</section>

<?php foreach ($flashes as $flash): ?>
    <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<!-- Nút lọc nhanh theo trạng thái -->
<div class="status-pills">
    <a class="pill <?= $statusFilter === '' ? 'active' : '' ?>" href="?<?= http_build_query(array_filter(['q' => $keyword, 'role' => $roleFilter])) ?>">
        Tất cả <span class="pill-badge"><?= $cntTotal ?></span>
    </a>
    <a class="pill <?= $statusFilter === 'Đang làm việc' ? 'active' : '' ?>" href="?<?= http_build_query(array_filter(['q' => $keyword, 'role' => $roleFilter, 'status' => 'Đang làm việc'])) ?>">
        Đang làm việc <span class="pill-badge"><?= $cntActive ?></span>
    </a>
    <a class="pill <?= $statusFilter === 'Nghỉ việc' ? 'active' : '' ?>" href="?<?= http_build_query(array_filter(['q' => $keyword, 'role' => $roleFilter, 'status' => 'Nghỉ việc'])) ?>">
        Nghỉ việc <span class="pill-badge"><?= $cntInactive ?></span>
    </a>
</div>

<!-- Bộ lọc tìm kiếm & trạng thái -->
<form class="filter-card" method="get">
    <div class="filter-row">
        <div class="filter-col flex-2">
            <label for="q" class="sr-only">Tìm kiếm</label>
            <input id="q" name="q" value="<?= e($keyword) ?>" maxlength="100" placeholder="Tìm theo họ tên, tài khoản, email, SĐT...">
        </div>
        <div class="filter-col">
            <select name="role" onchange="this.form.submit()">
                <option value="">-- Tất cả vai trò --</option>
                <option value="Admin" <?= $roleFilter === 'Admin' ? 'selected' : '' ?>>Admin (Chủ nhà)</option>
                <option value="NhanVien" <?= $roleFilter === 'NhanVien' ? 'selected' : '' ?>>NhanVien</option>
            </select>
        </div>
        <div class="filter-col">
            <select name="status" onchange="this.form.submit()">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Đang làm việc" <?= $statusFilter === 'Đang làm việc' ? 'selected' : '' ?>>Đang làm việc</option>
                <option value="Nghỉ việc" <?= $statusFilter === 'Nghỉ việc' ? 'selected' : '' ?>>Nghỉ việc</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="button">Lọc dữ liệu</button>
            <?php if ($keyword !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                <a class="button secondary" href="index.php">Xóa lọc</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="table-summary">
    Tổng số: <strong><?= (int)$result['total'] ?></strong> tài khoản
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width: 60px;">Mã</th>
                <th>Họ tên</th>
                <th>Tài khoản</th>
                <th>Vai trò</th>
                <th>Số điện thoại</th>
                <th>Email</th>
                <th>Trạng thái</th>
                <th style="text-align: right;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($result['items'] as $row): ?>
                <?php $isSelf = ($currentUserId === (int)$row['MaNV']); ?>
                <tr>
                    <td><strong>#<?= (int)$row['MaNV'] ?></strong></td>
                    <td>
                        <strong><?= e($row['HoTen']) ?></strong>
                        <?php if ($isSelf): ?>
                            <span class="badge-self" title="Tài khoản bạn đang đăng nhập">(Bạn)</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= e($row['TenDangNhap']) ?></code></td>
                    <td>
                        <?php if ($row['VaiTro'] === 'Admin'): ?>
                            <span class="badge badge-admin">Admin</span>
                        <?php else: ?>
                            <span class="badge badge-staff">Nhân viên</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($row['SoDienThoai'] ?: '-') ?></td>
                    <td><?= e($row['Email'] ?: '-') ?></td>
                    <td>
                        <?php if ($isSelf): ?>
                            <span class="badge badge-active" title="Tài khoản của bạn">Đang làm việc</span>
                        <?php else: ?>
                            <form method="post" style="display: inline-block;">
                                <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= (int)$row['MaNV'] ?>">
                                <select name="trang_thai" style="padding: 3px 6px; font-size: 12px; width: auto; font-weight: 600; border-radius: 6px; border: 1px solid <?= $row['TrangThai'] === 'Đang làm việc' ? '#bbf7d0' : '#fecaca' ?>; background: <?= $row['TrangThai'] === 'Đang làm việc' ? '#f0fdf4' : '#fef2f2' ?>; color: <?= $row['TrangThai'] === 'Đang làm việc' ? '#166534' : '#991b1b' ?>;" onchange="this.form.submit()">
                                    <option value="Đang làm việc" <?= $row['TrangThai'] === 'Đang làm việc' ? 'selected' : '' ?>>Đang làm việc</option>
                                    <option value="Nghỉ việc" <?= $row['TrangThai'] === 'Nghỉ việc' ? 'selected' : '' ?>>Nghỉ việc</option>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <a class="btn-action btn-edit" href="edit.php?id=<?= (int)$row['MaNV'] ?>" title="Chỉnh sửa">
                            Sửa
                        </a>
                        <?php if ($isSelf): ?>
                            <span class="btn-action btn-disabled" title="Không thể xóa tài khoản của chính mình">Xóa</span>
                        <?php else: ?>
                            <a class="btn-action btn-danger" href="delete.php?id=<?= (int)$row['MaNV'] ?>" title="Xóa tài khoản">
                                Xóa
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($result['items'])): ?>
                <tr>
                    <td colspan="8" class="empty-cell">
                        <p>Không tìm thấy nhân viên nào phù hợp với điều kiện tìm kiếm.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $queryParams = [];
        if ($keyword !== '') $queryParams['q'] = $keyword;
        if ($roleFilter !== '') $queryParams['role'] = $roleFilter;
        if ($statusFilter !== '') $queryParams['status'] = $statusFilter;
        ?>

        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query($queryParams + ['page' => $page - 1]) ?>">&laquo; Trước</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= http_build_query($queryParams + ['page' => $i]) ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query($queryParams + ['page' => $page + 1]) ?>">Sau &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
