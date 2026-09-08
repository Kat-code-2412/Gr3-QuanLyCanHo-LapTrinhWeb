<?php

declare(strict_types=1);

$title = 'Quản Lý Khách Thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue');

// --- 1. THỐNG KÊ TỔNG QUAN (TỔNG SỐ PHÒNG, ĐANG THUÊ, SỐ PHÒNG ĐANG TRỐNG) ---
$totalRoomCount = (int)$pdo->query('SELECT COUNT(*) FROM CanHo')->fetchColumn();

$rentingCount = (int)$pdo->query("
    SELECT COUNT(DISTINCT MaCanHo) 
    FROM HopDong 
    WHERE TrangThai = 'Đang hiệu lực'
")->fetchColumn();

$vacantRoomCount = max(0, $totalRoomCount - $rentingCount);

// --- 2. LẤY THAM SỐ LỌC, TÌM KIẾM, PHÂN TRANG ---
$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50], true)) {
    $perPage = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// --- 3. XÂY DỰNG MỆNH ĐỀ WHERE ---
$whereConditions = [];
$params = [];

if ($keyword !== '') {
    $whereConditions[] = '(kt.MaKhach LIKE ? OR kt.HoTen LIKE ? OR kt.SoDienThoai LIKE ? OR kt.CCCD LIKE ? OR kt.Email LIKE ?)';
    $k = '%' . $keyword . '%';
    $params = [$k, $k, $k, $k, $k];
}

if ($statusFilter === 'Đang thuê') {
    $whereConditions[] = '(SELECT COUNT(*) FROM HopDong h1 WHERE h1.MaKhach = kt.MaKhach AND h1.TrangThai = "Đang hiệu lực") > 0';
} elseif ($statusFilter === 'Chưa thuê') {
    $whereConditions[] = '(SELECT COUNT(*) FROM HopDong h1 WHERE h1.MaKhach = kt.MaKhach AND h1.TrangThai = "Đang hiệu lực") = 0';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// --- 4. ĐẾM TỔNG SỐ DÒNG CHO PHÂN TRANG ---
$countSql = "SELECT COUNT(*) FROM KhachThue kt $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalMatching = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalMatching / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// --- 5. QUERY LẤY DANH SÁCH KHÁCH THUÊ ---
$sql = "SELECT kt.*, 
               ch.SoPhong, 
               active_hp.NgayBatDau AS NgayBatDauThue,
               (SELECT COUNT(*) FROM HopDong h1 WHERE h1.MaKhach = kt.MaKhach AND h1.TrangThai = 'Đang hiệu lực') AS ActiveCount
        FROM KhachThue kt
        LEFT JOIN HopDong active_hp ON kt.MaKhach = active_hp.MaKhach AND active_hp.TrangThai = 'Đang hiệu lực'
        LEFT JOIN CanHo ch ON active_hp.MaCanHo = ch.MaCanHo
        $whereClause
        ORDER BY kt.MaKhach DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Quản Lý Khách Thuê</h1>
    </div>
</div>

<!-- 3 THẺ THỐNG KÊ (TỔNG SỐ PHÒNG, ĐANG THUÊ, SỐ PHÒNG ĐANG TRỐNG) -->
<div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 1.5rem;">
    <div class="detail-item" style="border-left: 4px solid var(--primary-color);">
        <div class="detail-label">🏢 TỔNG SỐ PHÒNG</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--primary-color);">
            <?= $totalRoomCount ?> <span style="font-size: 1rem; font-weight: 600; color: #64748b;">phòng</span>
        </div>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--success-color);">
        <div class="detail-label">🏠 ĐANG THUÊ</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--success-color);">
            <?= $rentingCount ?> <span style="font-size: 1rem; font-weight: 600; color: #64748b;">phòng</span>
        </div>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--warning-color);">
        <div class="detail-label">🔑 SỐ PHÒNG ĐANG TRỐNG</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--warning-color);">
            <?= $vacantRoomCount ?> <span style="font-size: 1rem; font-weight: 600; color: #64748b;">phòng</span>
        </div>
    </div>
</div>

<!-- THANH TÌM KIẾM & BỘ LỌC -->
<div class="filter-card mb-3">
    <form method="GET" action="" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        <div class="filter-group" style="flex: 2; min-width: 250px;">
            <label for="keyword" style="font-weight: 600; font-size: 0.85rem;">🔍 Tìm kiếm khách thuê...</label>
            <input type="text" 
                   id="keyword" 
                   name="keyword" 
                   class="form-control" 
                   placeholder="Nhập Mã khách, Họ tên, SĐT, CCCD, Email..." 
                   value="<?= e($keyword) ?>">
        </div>

        <div class="filter-group" style="flex: 1; min-width: 160px;">
            <label for="status" style="font-weight: 600; font-size: 0.85rem;">Trạng thái</label>
            <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Đang thuê" <?= ($statusFilter === 'Đang thuê') ? 'selected' : '' ?>>Đang thuê</option>
                <option value="Chưa thuê" <?= ($statusFilter === 'Chưa thuê') ? 'selected' : '' ?>>Chưa thuê</option>
            </select>
        </div>

        <div class="filter-group" style="flex: 0 0 110px;">
            <label for="per_page" style="font-weight: 600; font-size: 0.85rem;">Hiển thị</label>
            <select name="per_page" id="per_page" class="form-control" onchange="this.form.submit()">
                <option value="10" <?= ($perPage === 10) ? 'selected' : '' ?>>10 / trang</option>
                <option value="20" <?= ($perPage === 20) ? 'selected' : '' ?>>20 / trang</option>
                <option value="50" <?= ($perPage === 50) ? 'selected' : '' ?>>50 / trang</option>
            </select>
        </div>

        <div class="filter-group" style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">Lọc</button>
            <?php if ($keyword !== '' || $statusFilter !== '' || $perPage !== 10): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Đặt lại</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- BẢNG DANH SÁCH KHÁCH THUÊ (CHỈ GIỮ LẠI NÚT XEM) -->
<div class="card">
    <div class="card-header">
        <h3>Danh Sách Khách Thuê (<?= $totalMatching ?> khách)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tenants)): ?>
            <div class="empty-state">
                <p>Không tìm thấy dữ liệu khách thuê phù hợp.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Họ và Tên</th>
                            <th>Số điện thoại</th>
                            <th>Email</th>
                            <th>CCCD</th>
                            <th>Giới tính</th>
                            <th>Ngày sinh</th>
                            <th>Phòng đang thuê</th>
                            <th>Ngày bắt đầu thuê</th>
                            <th>Trạng thái</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $kt): 
                            $tenantStatus = ((int)$kt['ActiveCount'] > 0) ? 'Đang thuê' : 'Chưa thuê';
                        ?>
                            <tr>
                                <td><strong>#<?= e((string)$kt['MaKhach']) ?></strong></td>
                                <td>
                                    <a href="<?= $baseUrl ?>/detail.php?id=<?= $kt['MaKhach'] ?>" style="font-weight: 600; color: var(--primary-color);">
                                        <?= e($kt['HoTen']) ?>
                                    </a>
                                </td>
                                <td><strong><?= e($kt['SoDienThoai']) ?></strong></td>
                                <td><?= e($kt['Email'] ?? '-') ?></td>
                                <td><code><?= e($kt['CCCD']) ?></code></td>
                                <td><?= e($kt['GioiTinh']) ?></td>
                                <td><?= formatDate($kt['NgaySinh']) ?></td>
                                <td>
                                    <?php if (!empty($kt['SoPhong'])): ?>
                                        <span style="font-weight: 700; color: var(--primary-color);">
                                            Phòng <?= e($kt['SoPhong']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.85rem;">Chưa thuê</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($kt['NgayBatDauThue']) ? formatDate($kt['NgayBatDauThue']) : '-' ?>
                                </td>
                                <td><?= renderStatusBadge($tenantStatus) ?></td>
                                <td class="text-center">
                                    <div class="actions-cell" style="justify-content: center; gap: 0.3rem;">
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= $kt['MaKhach'] ?>" class="btn btn-sm btn-outline" title="Xem chi tiết">
                                            👁️ Xem
                                        </a>
                                        <a href="<?= $baseUrl ?>/delete.php?id=<?= $kt['MaKhach'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa khách thuê này không?');" 
                                           title="Xóa khách thuê">
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

<!-- PHÂN TRANG -->
<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.3rem;">
        <?php 
        $queryParams = $_GET; 
        if ($page > 1): 
            $queryParams['page'] = $page - 1;
        ?>
            <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-sm btn-outline">&lt;</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): 
            $queryParams['page'] = $i;
        ?>
            <a href="?<?= http_build_query($queryParams) ?>" class="<?= ($i === $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): 
            $queryParams['page'] = $page + 1;
        ?>
            <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-sm btn-outline">&gt;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
