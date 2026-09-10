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
    $whereClauses[] = '(kt.HoTen LIKE ? OR kt.CCCD LIKE ? OR kt.SoDienThoai LIKE ? OR ch.MaCanHoHienThi LIKE ? OR hp.MaHopDong LIKE ?)';
    $k = '%' . $keyword . '%';
    $params = [$k, $k, $k, $k, $k];
}

if ($trangThaiFilter !== '') {
    $whereClauses[] = 'hp.TrangThai = ?';
    $params[] = $trangThaiFilter;
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

// Lấy danh sách hợp đồng JOIN CanHo, LoaiCanHo, KhachThue, NhanVien
$sql = "SELECT hp.*, ch.MaCanHoHienThi AS SoPhong, ch.Tang, ch.DienTich, kt.HoTen AS TenKhach, kt.SoDienThoai, kt.CCCD, nv.HoTen AS TenNhanVien
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
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Quản Lý Hợp Đồng Thuê Căn Hộ</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary" style="font-weight: 600; font-size: 0.95rem; padding: 0.65rem 1.25rem;">
            <span>+</span> Thêm Khách Thuê
        </a>
    </div>
</div>

<!-- Lọc hợp đồng (Không có bộ lọc giới tính) -->
<div class="filter-card mb-3">
    <form method="GET" action="" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        <div class="filter-group" style="flex: 2; min-width: 260px;">
            <label for="keyword" style="font-weight: 600; font-size: 0.85rem;">🔍 Tìm kiếm Hợp đồng...</label>
            <input type="text" 
                   id="keyword" 
                   name="keyword" 
                   class="form-control" 
                   placeholder="Tên khách, Số phòng, Địa chỉ, CCCD, SĐT, Mã HĐ..." 
                   value="<?= e($keyword) ?>">
        </div>
        <div class="filter-group" style="flex: 1; min-width: 180px;">
            <label for="trang_thai" style="font-weight: 600; font-size: 0.85rem;">Trạng thái Hợp đồng</label>
            <select id="trang_thai" name="trang_thai" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="Đang hiệu lực" <?= ($trangThaiFilter === 'Đang hiệu lực') ? 'selected' : '' ?>>Đang hiệu lực</option>
                <option value="Sắp hết hạn" <?= ($trangThaiFilter === 'Sắp hết hạn') ? 'selected' : '' ?>>Sắp hết hạn</option>
                <option value="Đã thanh lý" <?= ($trangThaiFilter === 'Đã thanh lý') ? 'selected' : '' ?>>Đã thanh lý</option>
                <option value="Chưa check-in" <?= ($trangThaiFilter === 'Chưa check-in') ? 'selected' : '' ?>>Chưa check-in</option>
            </select>
        </div>
        <div class="filter-group" style="flex: 0 0 auto; display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">Lọc</button>
            <?php if ($keyword !== '' || $trangThaiFilter !== ''): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Đặt lại</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bảng danh sách hợp đồng -->
<div class="card">
    <div class="card-header">
        <h3>Danh Sách Hợp Đồng Thuê (<?= $totalRows ?> hợp đồng)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($contracts)): ?>
            <div class="empty-state">
                <p>Không tìm thấy hợp đồng nào phù hợp điều kiện.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Khách thuê</th>
                            <th>Địa chỉ & Phòng</th>
                            <th>Thời hạn thuê</th>
                            <th>Giá thuê & Cọc</th>
                            <th>Đơn giá Điện / Nước</th>
                            <th>File Hợp Đồng</th>
                            <th>Trạng thái</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $item): ?>
                            <tr>
                                <td><strong>#<?= e((string)$item['MaHopDong']) ?></strong></td>
                                <td>
                                    <a href="<?= $khachThueUrl ?>/detail.php?id=<?= $item['MaKhach'] ?>" style="font-weight: 600; color: var(--primary-color);">
                                        <?= e($item['TenKhach']) ?>
                                    </a>
                                    <br>
                                    <small style="color: #64748b;">📞 <?= e($item['SoDienThoai']) ?></small>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: var(--primary-color); font-size: 0.95rem;">
                                        🏢 Phòng <?= e($item['SoPhong'] ?? ('#' . $item['MaCanHo'])) ?>
                                    </span>
                                    <br>
                                    <small style="color: #475569; font-size: 0.8rem;">Tầng <?= e((string)($item['Tang'] ?? '-')) ?></small>
                                </td>
                                <td>
                                    <?= formatDate($item['NgayBatDau']) ?> → <?= formatDate($item['NgayKetThuc']) ?>
                                </td>
                                <td>
                                    Giá: <strong><?= formatMoney($item['GiaThueThoaThuan']) ?></strong><br>
                                    Cọc: <span style="color: var(--success-color); font-weight: 600;"><?= formatMoney($item['TienCoc']) ?></span>
                                </td>
                                <td>
                                    <span style="color: #64748b;">Theo bảng giá hiện hành</span>
                                </td>
                                <td>
                                    <span style="color: #94a3b8; font-size: 0.85rem;">-</span>
                                </td>
                                <td><?= renderStatusBadge($item['TrangThai']) ?></td>
                                <td class="text-center">
                                    <div class="actions-cell" style="justify-content: center; gap: 0.3rem;">
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= $item['MaHopDong'] ?>" class="btn btn-sm btn-outline" title="Chi tiết hợp đồng">
                                            👁️ Xem
                                        </a>
                                        <a href="<?= $baseUrl ?>/edit.php?id=<?= $item['MaHopDong'] ?>" class="btn btn-sm btn-secondary" title="Sửa hợp đồng">
                                            ✏️ Sửa
                                        </a>
                                        <?php if ($item['TrangThai'] === 'Đang hiệu lực' || $item['TrangThai'] === 'Chưa check-in'): ?>
                                            <a href="<?= $baseUrl ?>/thanh-ly.php?id=<?= $item['MaHopDong'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Bạn có chắc chắn muốn Thanh Lý hợp đồng #<?= $item['MaHopDong'] ?>? Phòng sẽ chuyển về trạng thái Trống.');" 
                                               title="Thanh lý hợp đồng">
                                                🚪 Thanh lý
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
