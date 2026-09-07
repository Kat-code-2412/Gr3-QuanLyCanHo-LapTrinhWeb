<?php

declare(strict_types=1);

$title = 'Danh sách Khách thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';

// Tìm kiếm
$keyword = trim($_GET['keyword'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereClause = '';
$params = [];

if ($keyword !== '') {
    $whereClause = 'WHERE HoTen LIKE :k OR CCCD LIKE :k OR SoDienThoai LIKE :k';
    $params[':k'] = '%' . $keyword . '%';
}

// Đếm tổng số bản ghi
$countSql = "SELECT COUNT(*) FROM KhachThue $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Lấy danh sách khách thuê
$sql = "SELECT * FROM KhachThue $whereClause ORDER BY MaKhach DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

$baseUrl = (currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Quản Lý Khách Thuê</h1>
        <p class="page-subtitle">Danh sách tất cả khách thuê trong hệ thống</p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary">
            <span>+</span> Thêm Khách Thuê
        </a>
    </div>
</div>

<!-- Thanh tìm kiếm -->
<div class="filter-card">
    <form method="GET" action="" class="filter-form">
        <div class="filter-group" style="flex: 2;">
            <label for="keyword">Tìm kiếm khách thuê</label>
            <input type="text" 
                   id="keyword" 
                   name="keyword" 
                   class="form-control" 
                   placeholder="Nhập Họ tên, CCCD hoặc Số điện thoại..." 
                   value="<?= e($keyword) ?>">
        </div>
        <div class="filter-group" style="flex: 0 0 auto;">
            <button type="submit" class="btn btn-primary">🔍 Tìm kiếm</button>
            <?php if ($keyword !== ''): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Bỏ lọc</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bảng danh sách khách thuê -->
<div class="card">
    <div class="card-header">
        <h3>Danh Sách Khách Thuê (<?= $totalRows ?> khách)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tenants)): ?>
            <div class="empty-state">
                <p>Không tìm thấy khách thuê nào phù hợp.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Họ và Tên</th>
                            <th>CCCD</th>
                            <th>Ngày sinh</th>
                            <th>Giới tính</th>
                            <th>Số điện thoại</th>
                            <th>Email</th>
                            <th>Địa chỉ thường trú</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $kt): ?>
                            <tr>
                                <td><strong>#<?= e((string)$kt['MaKhach']) ?></strong></td>
                                <td>
                                    <a href="<?= $baseUrl ?>/detail.php?id=<?= $kt['MaKhach'] ?>" style="font-weight: 600;">
                                        <?= e($kt['HoTen']) ?>
                                    </a>
                                </td>
                                <td><code><?= e($kt['CCCD']) ?></code></td>
                                <td><?= formatDate($kt['NgaySinh']) ?></td>
                                <td><?= e($kt['GioiTinh']) ?></td>
                                <td><?= e($kt['SoDienThoai']) ?></td>
                                <td><?= e($kt['Email'] ?? '-') ?></td>
                                <td><?= e($kt['DiaChiThuongTru'] ?? '-') ?></td>
                                <td class="text-center">
                                    <div class="actions-cell" style="justify-content: center;">
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= $kt['MaKhach'] ?>" class="btn btn-sm btn-outline" title="Xem chi tiết">
                                            👁️ Xem
                                        </a>
                                        <a href="<?= $baseUrl ?>/edit.php?id=<?= $kt['MaKhach'] ?>" class="btn btn-sm btn-secondary" title="Chỉnh sửa">
                                            ✏️ Sửa
                                        </a>
                                        <a href="<?= $baseUrl ?>/delete.php?id=<?= $kt['MaKhach'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa khách thuê: <?= e(addslashes($kt['HoTen'])) ?>?');" 
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
            <a href="?keyword=<?= urlencode($keyword) ?>&page=<?= $i ?>" class="<?= ($i === $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
