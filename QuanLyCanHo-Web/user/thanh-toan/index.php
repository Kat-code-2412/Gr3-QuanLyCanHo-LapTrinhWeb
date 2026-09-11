<?php

declare(strict_types=1);

$title = 'Quản lý hóa đơn thanh toán';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$keyword = trim((string)($_GET['keyword'] ?? ''));
$trangThai = trim((string)($_GET['trang_thai'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = '(hd.KyThanhToan LIKE ? OR kt.HoTen LIKE ? OR ch.MaCanHoHienThi LIKE ? OR hp.MaHopDong LIKE ?)';
    $like = '%' . $keyword . '%';
    $params = [$like, $like, $like, $like];
}

if ($trangThai !== '') {
    $paidTotalSql = '(SELECT COALESCE(SUM(lst_filter.SoTien), 0) FROM LichSuThanhToan lst_filter WHERE lst_filter.MaHoaDon = hd.MaHoaDon)';
    if ($trangThai === 'Đã TT') {
        $where[] = $paidTotalSql . ' >= hd.TongTien';
    } elseif ($trangThai === 'Chưa TT') {
        $where[] = $paidTotalSql . ' < hd.TongTien';
    } else {
        $where[] = 'hd.TrangThai = ?';
        $params[] = $trangThai;
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$countSql = "SELECT COUNT(*)
             FROM HoaDon hd
             JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
             JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
             JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
             $whereSql";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT hd.MaHoaDon, hd.KyThanhToan, hd.TrangThai, hd.TongTien, hd.NgayTao,
              COALESCE((SELECT SUM(lst.SoTien) FROM LichSuThanhToan lst WHERE lst.MaHoaDon = hd.MaHoaDon), 0) AS DaThanhToan,
              hp.MaHopDong, ch.MaCanHoHienThi AS SoPhong, kt.HoTen AS TenKhach
        FROM HoaDon hd
        JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        $whereSql
        ORDER BY hd.MaHoaDon DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $index => $value) {
    $stmt->bindValue($index + 1, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Quản lý hóa đơn</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/tao-hang-thang.php" class="btn btn-primary">+ Tạo hóa đơn tháng</a>
    </div>
</div>

<div class="filter-card mb-3">
    <form method="GET" class="filter-form" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: end;">
        <div class="filter-group" style="flex: 2; min-width: 260px;">
            <label for="keyword" style="font-weight: 600; font-size: 0.85rem;">Tìm kiếm hóa đơn</label>
            <input type="text" id="keyword" name="keyword" class="form-control" value="<?= e($keyword) ?>" placeholder="Kỳ, khách, phòng, mã hợp đồng...">
        </div>
        <div class="filter-group" style="flex: 1; min-width: 180px;">
            <label for="trang_thai" style="font-weight: 600; font-size: 0.85rem;">Trạng thái</label>
            <select id="trang_thai" name="trang_thai" class="form-control">
                <option value="">Tất cả</option>
                <option value="Chưa TT" <?= $trangThai === 'Chưa TT' ? 'selected' : '' ?>>Chưa TT</option>
                <option value="Đã TT" <?= $trangThai === 'Đã TT' ? 'selected' : '' ?>>Đã TT</option>
            </select>
        </div>
        <div class="filter-group" style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">Lọc</button>
            <?php if ($keyword !== '' || $trangThai !== ''): ?>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Đặt lại</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>Danh sách hóa đơn</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($invoices)): ?>
            <div class="empty-state">
                <p>Không có hóa đơn nào phù hợp.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Kỳ</th>
                            <th>Khách thuê</th>
                            <th>Phòng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php $daThanhToan = (float)$invoice['DaThanhToan']; ?>
                            <?php
                            $kyThanhToan = (string)$invoice['KyThanhToan'];
                            $kyDate = DateTime::createFromFormat('Y-m-d', $kyThanhToan . '-01')
                                ?: DateTime::createFromFormat('m/Y', $kyThanhToan);
                            $isOverdue = $daThanhToan < (float)$invoice['TongTien']
                                && $kyDate instanceof DateTime
                                && $kyDate < new DateTime('first day of this month');
                            ?>
                            <tr>
                                <td><strong>#<?= e((string)$invoice['MaHoaDon']) ?></strong></td>
                                <td><?= e($invoice['KyThanhToan']) ?></td>
                                <td><?= e($invoice['TenKhach']) ?></td>
                                <td><?= e($invoice['SoPhong']) ?></td>
                                <td><?= formatMoney($invoice['TongTien']) ?></td>
                                <td>
                                    <?php if ($daThanhToan >= (float)$invoice['TongTien']): ?>
                                        <span class="badge badge-success">✓ Đã thanh toán</span>
                                    <?php elseif ($isOverdue): ?>
                                        <span class="badge badge-danger">⚠ Quá hạn</span>
                                    <?php else: ?>
                                        <?= renderStatusBadge((string)$invoice['TrangThai']) ?>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;"><?= formatDate((string)$invoice['NgayTao']) ?></td>
                                <td class="text-center">
                                    <div class="actions-cell" style="justify-content: center; gap: 0.35rem;">
                                        <a href="<?= $baseUrl ?>/detail.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn btn-sm btn-outline">Chi tiết</a>
                                        <?php if ($daThanhToan < (float)$invoice['TongTien']): ?>
                                            <a href="<?= $baseUrl ?>/thanh-toan.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn btn-sm btn-primary">Thanh toán</a>
                                        <?php else: ?>
                                            <span class="text-muted">Đã hoàn tất</span>
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

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.3rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?keyword=<?= urlencode($keyword) ?>&trang_thai=<?= urlencode($trangThai) ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
