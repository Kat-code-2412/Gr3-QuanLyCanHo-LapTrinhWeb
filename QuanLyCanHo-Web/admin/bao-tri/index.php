<?php

declare(strict_types=1);

$title = 'Quản lý Yêu cầu Bảo trì';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';

// Params tìm kiếm & lọc
$keyword = trim($_GET['keyword'] ?? '');
$trangThaiFilter = trim($_GET['trang_thai'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($keyword !== '') {
    $whereClauses[] = '(kt.HoTen LIKE :k OR ch.SoPhong LIKE :k OR bt.NoiDung LIKE :k)';
    $params[':k'] = '%' . $keyword . '%';
}

if ($trangThaiFilter !== '') {
    $whereClauses[] = 'bt.TrangThai = :tt';
    $params[':tt'] = $trangThaiFilter;
}

$whereSql = (!empty($whereClauses)) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Đếm tổng số bản ghi
$countSql = "SELECT COUNT(*) 
            FROM YeuCauBaoTri bt
            JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
            JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
            $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Query lấy danh sách bảo trì JOIN CanHo và KhachThue
$sql = "SELECT bt.*, ch.SoPhong, kt.HoTen AS TenKhach, kt.SoDienThoai
        FROM YeuCauBaoTri bt
        JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
        $whereSql
        ORDER BY bt.MaBaoTri DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$baseUrl = (currentUserRole() === 'Admin') ? '/admin/bao-tri' : '/user/bao-tri';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Quản Lý Bảo Trì & Sửa Chữa</h1>
        <p class="page-subtitle">Theo dõi và cập nhật tiến độ xử lý sự cố căn hộ</p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary">
            <span>+</span> Tạo Yêu Cầu Bảo Trì
        </a>
    </div>
</div>

<!-- Form Tìm kiếm & Lọc trạng thái -->
<div class="filter-card">
    <form method="GET" action="" class="filter-form">
        <div class="filter-group" style="flex: 2;">
            <label for="keyword">Tìm kiếm (Tên khách, Số phòng, Nội dung)</label>
            <input type="text" 
                   id="keyword" 
                   name="keyword" 
                   class="form-control" 
                   placeholder="Nhập tên khách, số phòng (VD: 203) hoặc nội dung..." 
                   value="<?= e($keyword) ?>">
        </div>
        <div class="filter-group">
            <label for="trang_thai">Trạng thái bảo trì</label>
            <select id="trang_thai" name="trang_thai" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Đã tiếp nhận" <?= ($trangThaiFilter === 'Đã tiếp nhận') ? 'selected' : '' ?>>Đã tiếp nhận</option>
                <option value="Đang xử lý" <?= ($trangThaiFilter === 'Đang xử lý') ? 'selected' : '' ?>>Đang xử lý</option>
                <option value="Hoàn thành" <?= ($trangThaiFilter === 'Hoàn thành') ? 'selected' : '' ?>>Hoàn thành</option>
            </select>
        </div>
        <div class="filter-group" style="flex: 0 0 auto;">
            <button type="submit" class="btn btn-primary">🔍 Lọc</button>
            <?php if ($keyword !== '' || $trangThaiFilter !== ''): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Bỏ lọc</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bảng danh sách yêu cầu bảo trì -->
<div class="card">
    <div class="card-header">
        <h3>Danh Sách Yêu Cầu (<?= $totalRows ?> yêu cầu)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <p>Không có yêu cầu bảo trì nào phù hợp điều kiện lọc.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã BT</th>
                            <th>Phòng</th>
                            <th>Khách thuê</th>
                            <th>Nội dung sự cố</th>
                            <th>Ngày tiếp nhận</th>
                            <th>Ngày hoàn thành</th>
                            <th>Chi phí</th>
                            <th>Trạng thái</th>
                            <th>Ghi chú</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $item): ?>
                            <tr>
                                <td><strong>#<?= e((string)$item['MaBaoTri']) ?></strong></td>
                                <td>
                                    <span style="font-weight: 600; color: var(--primary-color);">
                                        Phòng <?= e($item['SoPhong']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= (currentUserRole() === 'Admin' ? '/admin' : '/user') ?>/khach-thue/detail.php?id=<?= $item['MaKhach'] ?>" style="font-weight: 500;">
                                        <?= e($item['TenKhach']) ?>
                                    </a>
                                    <br>
                                    <small style="color: var(--text-muted);"><?= e($item['SoDienThoai']) ?></small>
                                </td>
                                <td style="max-width: 220px; white-space: normal;">
                                    <?= e($item['NoiDung']) ?>
                                </td>
                                <td><?= formatDateTime($item['NgayTiepNhan']) ?></td>
                                <td><?= formatDateTime($item['NgayHoanThanh']) ?></td>
                                <td><strong><?= formatMoney($item['ChiPhi']) ?></strong></td>
                                <td><?= renderStatusBadge($item['TrangThai']) ?></td>
                                <td style="max-width: 150px; white-space: normal;">
                                    <?= e($item['GhiChu'] ?? '-') ?>
                                </td>
                                <td class="text-center">
                                    <div class="actions-cell" style="justify-content: center;">
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= $item['MaBaoTri'] ?>" class="btn btn-sm btn-outline" title="Xem chi tiết">
                                            👁️ Xem
                                        </a>
                                        <a href="<?= $baseUrl ?>/edit.php?id=<?= $item['MaBaoTri'] ?>" class="btn btn-sm btn-secondary" title="Cập nhật">
                                            ✏️ Cập nhật
                                        </a>
                                        <a href="<?= $baseUrl ?>/delete.php?id=<?= $item['MaBaoTri'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa yêu cầu bảo trì #<?= $item['MaBaoTri'] ?>?');" 
                                           title="Xóa">
                                            🗑️ Xóa
                                        </a>
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
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?keyword=<?= urlencode($keyword) ?>&trang_thai=<?= urlencode($trangThaiFilter) ?>&page=<?= $i ?>" class="<?= ($i === $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
