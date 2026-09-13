<?php

declare(strict_types=1);

$title = 'Quản lý Yêu cầu Bảo trì & Sự cố';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$isAdmin = (currentUserRole() === 'Admin');

// Params tìm kiếm & lọc
$keyword = trim($_GET['keyword'] ?? '');
$trangThaiFilter = trim($_GET['trang_thai'] ?? '');
$uuTienFilter = trim($_GET['muc_do_uu_tien'] ?? '');
$loaiSuCoFilter = trim($_GET['loai_su_co'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($keyword !== '') {
    $whereClauses[] = '(kt.HoTen LIKE ? OR ch.SoPhong LIKE ? OR ch.DiaChi LIKE ? OR bt.NoiDung LIKE ?)';
    $k = '%' . $keyword . '%';
    $params[] = $k;
    $params[] = $k;
    $params[] = $k;
    $params[] = $k;
}

if ($trangThaiFilter !== '') {
    $whereClauses[] = 'bt.TrangThai = ?';
    $params[] = $trangThaiFilter;
}

if ($uuTienFilter !== '') {
    $whereClauses[] = 'bt.MucDoUuTien = ?';
    $params[] = $uuTienFilter;
}

if ($loaiSuCoFilter !== '') {
    $whereClauses[] = 'bt.LoaiSuCo = ?';
    $params[] = $loaiSuCoFilter;
}

// Giới hạn sự cố theo tòa nhà nhân viên quản lý
$bldCond = buildStaffBuildingCondition('ch.DiaChi');
if ($bldCond['sql'] !== '1=1') {
    $whereClauses[] = $bldCond['sql'];
    foreach ($bldCond['params'] as $bp) {
        $params[] = $bp;
    }
}

$whereSql = (!empty($whereClauses)) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Đếm tổng số bản ghi
$countSql = "SELECT COUNT(*) 
            FROM YeuCauBaoTri bt
            JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
            LEFT JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
            $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Query lấy danh sách bảo trì JOIN CanHo và KhachThue
$sql = "SELECT bt.*, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach, kt.SoDienThoai
        FROM YeuCauBaoTri bt
        JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
        LEFT JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
        $whereSql
        ORDER BY 
            CASE bt.MucDoUuTien 
                WHEN 'Khẩn cấp' THEN 1 
                WHEN 'Cao' THEN 2 
                WHEN 'Trung bình' THEN 3 
                ELSE 4 
            END ASC,
            bt.MaBaoTri DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$baseUrl = url($isAdmin ? '/admin/bao-tri' : '/user/bao-tri');

// Helper render Priority Badge
function renderPriorityBadge(?string $priority): string {
    return match ($priority) {
        'Khẩn cấp' => '<span class="badge" style="background:#ef4444;color:#fff;font-weight:600;padding:4px 8px;border-radius:6px;font-size:0.75rem;">Khẩn cấp</span>',
        'Cao' => '<span class="badge" style="background:#f97316;color:#fff;font-weight:600;padding:4px 8px;border-radius:6px;font-size:0.75rem;">Cao</span>',
        'Trung bình' => '<span class="badge" style="background:#2563eb;color:#fff;font-weight:500;padding:4px 8px;border-radius:6px;font-size:0.75rem;">Trung bình</span>',
        'Thấp' => '<span class="badge" style="background:#64748b;color:#fff;font-weight:500;padding:4px 8px;border-radius:6px;font-size:0.75rem;">Thấp</span>',
        default => '<span class="badge" style="background:#94a3b8;color:#fff;font-weight:500;padding:4px 8px;border-radius:6px;font-size:0.75rem;">' . e($priority ?? 'Bình thường') . '</span>',
    };
}
?>

<style>
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
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

<div class="page-header">
    <div>
        <h1 class="page-title" style="margin: 0;">Quản Lý Bảo Trì & Sự Cố</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/create.php" class="btn btn-primary">
            <span>+</span> Báo Sự Cố Mới
        </a>
    </div>
</div>

<!-- Form Tìm kiếm & Lọc -->
<div class="filter-card">
    <form method="GET" action="" class="filter-form" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
        <div class="filter-group" style="flex:2;min-width:200px;">
            <label for="keyword">Tìm kiếm</label>
            <input type="text" 
                   id="keyword" 
                   name="keyword" 
                   class="form-control" 
                   placeholder="Tên khách, số phòng, nội dung..." 
                   value="<?= e($keyword) ?>">
        </div>
        <div class="filter-group" style="flex:1;min-width:180px;">
            <label for="trang_thai">Trạng thái</label>
            <select id="trang_thai" name="trang_thai" class="form-control" onchange="this.form.submit()">
                <option value="">-- Tất cả --</option>
                <option value="Đã tiếp nhận" <?= ($trangThaiFilter === 'Đã tiếp nhận') ? 'selected' : '' ?>>Đã tiếp nhận</option>
                <option value="Đang xử lý" <?= ($trangThaiFilter === 'Đang xử lý') ? 'selected' : '' ?>>Đang xử lý</option>
                <option value="Hoàn thành" <?= ($trangThaiFilter === 'Hoàn thành') ? 'selected' : '' ?>>Hoàn thành</option>
            </select>
        </div>
        <div class="filter-group" style="flex:0 0 auto;">
            <button type="submit" class="btn btn-primary"><?= svgIcon('filter', '', 14) ?> Lọc</button>
            <?php if ($keyword !== '' || $trangThaiFilter !== ''): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Xóa lọc</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bảng danh sách yêu cầu bảo trì -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;">Danh Sách Yêu Cầu & Sự Cố (<?= $totalRows ?> sự cố)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($requests)): ?>
            <div class="empty-state" style="padding:2rem;text-align:center;">
                <p class="text-muted">Không có yêu cầu bảo trì nào phù hợp điều kiện lọc.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th>Mã BT</th>
                            <th>Địa chỉ / Phòng</th>
                            <th>Khách thuê</th>
                            <th>Nội dung sự cố</th>
                            <th>Người chịu CP</th>
                            <th>Chi phí</th>
                            <th>Trạng thái</th>
                            <th>Ngày tiếp nhận</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $item): ?>
                            <tr>
                                <td><strong>#<?= e((string)$item['MaBaoTri']) ?></strong></td>
                                <td style="max-width: 280px; min-width: 170px;">
                                    <div style="font-weight: 500; font-size: 0.85rem; color: #334155; line-height: 1.35; margin-bottom: 0.35rem;">
                                        <?= e($item['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?>
                                    </div>
                                    <span style="font-weight: 700; color: #1d4ed8; font-size: 0.88rem; background: #eff6ff; padding: 2px 8px; border-radius: 4px; border: 1px solid #bfdbfe; display: inline-block;">
                                        Phòng <?= e(formatSoPhong($item['SoPhong'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['MaKhach']) && !empty($item['TenKhach'])): ?>
                                        <a href="<?= url(($isAdmin ? '/admin' : '/user') . '/khach-thue/detail.php?id=' . $item['MaKhach']) ?>" style="font-weight: 600; text-decoration:none;">
                                            <?= e($item['TenKhach']) ?>
                                        </a>
                                        <br>
                                        <small style="color: var(--text-muted);"><?= e($item['SoDienThoai']) ?></small>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">(Quản lý báo / Trống)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 260px; white-space: normal; line-height: 1.4;">
                                    <?= e($item['NoiDung']) ?>
                                </td>
                                <td>
                                    <small style="color: #475569; font-weight: 500;">
                                        <?= e($item['NguoiChiuChiPhi'] ?? 'Chủ nhà') ?>
                                    </small>
                                </td>
                                <td><strong><?= formatMoney($item['ChiPhi']) ?></strong></td>
                                <td><?= renderStatusBadge($item['TrangThai']) ?></td>
                                <td><small><?= formatDateTime($item['NgayTiepNhan']) ?></small></td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <div class="actions-cell" style="display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 105px;">
                                        <div style="display: flex; gap: 4px; justify-content: center; width: 100%;">
                                            <a href="<?= $baseUrl ?>/detail.php?id=<?= $item['MaBaoTri'] ?>" class="btn-action btn-action-view" title="Xem chi tiết">
                                                <?= svgIcon('eye', '', 13) ?>
                                                <span>Xem</span>
                                            </a>
                                            <a href="<?= $baseUrl ?>/edit.php?id=<?= $item['MaBaoTri'] ?>" class="btn-action btn-action-edit" title="Cập nhật">
                                                <?= svgIcon('edit', '', 13) ?>
                                                <span>Sửa</span>
                                            </a>
                                        </div>
                                        <?php if ($isAdmin): ?>
                                            <a href="<?= $baseUrl ?>/delete.php?id=<?= $item['MaBaoTri'] ?>" 
                                               class="btn-action btn-action-danger" 
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa yêu cầu bảo trì #<?= $item['MaBaoTri'] ?>?');" 
                                               title="Xóa">
                                                <?= svgIcon('trash', '', 13) ?>
                                                <span>Xóa</span>
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
    <div class="pagination" style="margin-top:1.5rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?keyword=<?= urlencode($keyword) ?>&trang_thai=<?= urlencode($trangThaiFilter) ?>&muc_do_uu_tien=<?= urlencode($uuTienFilter) ?>&loai_su_co=<?= urlencode($loaiSuCoFilter) ?>&page=<?= $i ?>" class="<?= ($i === $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
