<?php

declare(strict_types=1);

$title = 'Chi tiết hóa đơn';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Không tìm thấy hóa đơn cần xem.');
    redirect($baseUrl . '/index.php');
}

$sql = "SELECT hd.*, hp.MaHopDong, hp.MaCanHo, ch.SoPhong, COALESCE(ch.DiaChi, CONCAT('Phòng ', ch.SoPhong)) AS DiaChi,
              kt.HoTen AS TenKhach, kt.SoDienThoai, kt.CCCD
        FROM HoaDon hd
        JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        WHERE hd.MaHoaDon = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('error', 'Hóa đơn không tồn tại hoặc đã bị xóa.');
    redirect($baseUrl . '/index.php');
}

if (!isStaffAssignedBuilding((string)($invoice['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền xem hóa đơn thuộc tòa nhà này.');
    redirect($baseUrl . '/index.php');
}

$paymentStmt = $pdo->prepare('SELECT * FROM LichSuThanhToan WHERE MaHoaDon = ? ORDER BY NgayThanhToan DESC');
$paymentStmt->execute([$id]);
$paymentHistory = $paymentStmt->fetchAll();
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.invoice-detail-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #1e293b;
}
.invoice-top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}
.invoice-main-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 0.35rem 0;
    letter-spacing: -0.025em;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.invoice-sub-meta {
    margin: 0;
    font-size: 0.88rem;
    color: #64748b;
    font-weight: 500;
}
.invoice-top-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}
.invoice-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.95rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
    cursor: pointer;
}
.invoice-btn-action.btn-back {
    background: #ffffff;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.invoice-btn-action.btn-back:hover {
    background: #f1f5f9;
    color: #1e293b;
}
.invoice-btn-action.btn-print {
    background: #0f172a;
    color: #ffffff;
    border: 1px solid #0f172a;
}
.invoice-btn-action.btn-print:hover {
    background: #1e293b;
}
.invoice-btn-action.btn-edit {
    background: #ffffff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}
.invoice-btn-action.btn-edit:hover {
    background: #eff6ff;
    border-color: #93c5fd;
}
.invoice-btn-action.btn-pay {
    background: #2563eb;
    color: #ffffff;
    border: 1px solid #2563eb;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}
.invoice-btn-action.btn-pay:hover {
    background: #1d4ed8;
}
.invoice-btn-action.btn-del {
    background: #ffffff;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.invoice-btn-action.btn-del:hover {
    background: #fef2f2;
    border-color: #fca5a5;
}

/* 2-Column Info Grid */
.invoice-grid-2col {
    display: grid;
    grid-template-columns: 1.25fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 860px) {
    .invoice-grid-2col {
        grid-template-columns: 1fr;
    }
}

.modern-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.modern-card-header {
    padding: 0.9rem 1.25rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modern-card-header h3 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.modern-card-body {
    padding: 1.25rem;
}

/* Info field rows */
.modern-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.65rem 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.9rem;
}
.modern-info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.modern-info-row:first-child {
    padding-top: 0;
}
.info-field-label {
    color: #64748b;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}
.info-field-val {
    font-weight: 600;
    color: #0f172a;
    text-align: right;
    max-width: 65%;
    word-break: break-word;
}

/* Big Hero KPI for Total */
.kpi-total-box {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-radius: 10px;
    padding: 1.15rem 1.25rem;
    color: #ffffff;
    margin-bottom: 1.15rem;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.22);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.kpi-total-box .kpi-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    margin-bottom: 0.25rem;
}
.kpi-total-box .kpi-number {
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
}

/* Fee Breakdown Table */
.fee-table {
    width: 100%;
    border-collapse: collapse;
}
.fee-table th {
    padding: 0.75rem 1.25rem;
    background: #f8fafc;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}
.fee-table td {
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.92rem;
    color: #1e293b;
    vertical-align: middle;
}
.fee-table tr:hover td {
    background-color: #fbfcfe;
}
.fee-table tr.total-highlight-row {
    background: #f8fafc;
}
.fee-table tr.total-highlight-row td {
    border-top: 2px solid #e2e8f0;
    border-bottom: none;
    padding: 1.1rem 1.25rem;
}

.room-badge {
    background: #e0e7ff;
    color: #3730a3;
    font-weight: 700;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    font-size: 0.88rem;
    display: inline-block;
}
</style>

<div class="invoice-detail-page">
    <!-- TOP BAR -->
    <div class="invoice-top-bar">
        <div class="invoice-title-box">
            <h1 class="invoice-main-title">
                Chi tiết hóa đơn #<?= (int)$invoice['MaHoaDon'] ?>
                <?= renderStatusBadge((string)$invoice['TrangThai']) ?>
            </h1>
            <p class="invoice-sub-meta">
                Kỳ thanh toán: <strong style="color: #1e293b;">Kỳ <?= e($invoice['KyThanhToan']) ?></strong> &bull; 
                Phòng <strong style="color: #1e293b;"><?= e(formatSoPhong($invoice['SoPhong'])) ?></strong> &bull; 
                Khách thuê: <strong style="color: #1e293b;"><?= e($invoice['TenKhach']) ?></strong>
            </p>
        </div>
        <div class="invoice-top-actions">
            <a href="<?= $baseUrl ?>/index.php" class="invoice-btn-action btn-back">
                <?= svgIcon('arrow-left', '', 14) ?> Quay lại
            </a>
            <a href="<?= url('/admin/phieu-in/hoa-don.php?id=' . (int)$invoice['MaHoaDon']) ?>" target="_blank" class="invoice-btn-action btn-print">
                <?= svgIcon('printer', '', 14) ?> In hóa đơn
            </a>
            <a href="<?= $baseUrl ?>/edit.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="invoice-btn-action btn-edit">
                <?= svgIcon('edit', '', 14) ?> Chỉnh sửa
            </a>
            <?php if ((string)$invoice['TrangThai'] !== 'Đã TT'): ?>
                <a href="<?= $baseUrl ?>/thanh-toan.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="invoice-btn-action btn-pay">
                    <?= svgIcon('payment', '', 14) ?> Thu tiền
                </a>
            <?php endif; ?>
            <a href="<?= $baseUrl ?>/delete.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="invoice-btn-action btn-del" onclick="return confirm('Bạn có chắc chắn muốn xóa hóa đơn #<?= (int)$invoice['MaHoaDon'] ?> này? Hành động này không thể hoàn tác!');">
                <?= svgIcon('trash', '', 14) ?> Xóa
            </a>
        </div>
    </div>

    <!-- 2-COLUMN INFO SECTION -->
    <div class="invoice-grid-2col">
        <!-- Cột 1: Thông tin khách thuê & căn hộ -->
        <div class="modern-card" style="margin-bottom: 0;">
            <div class="modern-card-header">
                <h3><?= svgIcon('user', '', 16) ?> Khách thuê & Căn hộ</h3>
                <span class="room-badge"><?= e(formatSoPhong($invoice['SoPhong'])) ?></span>
            </div>
            <div class="modern-card-body">
                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('user', '', 14) ?> Họ tên khách</span>
                    <span class="info-field-val" style="font-size: 1rem; color: #1d4ed8;"><?= e($invoice['TenKhach']) ?></span>
                </div>
                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('phone', '', 14) ?> Số điện thoại</span>
                    <span class="info-field-val"><?= e($invoice['SoDienThoai'] ?: 'Chưa cập nhật') ?></span>
                </div>
                <?php if (!empty($invoice['CCCD'])): ?>
                    <div class="modern-info-row">
                        <span class="info-field-label"><?= svgIcon('id-card', '', 14) ?> CMND / CCCD</span>
                        <span class="info-field-val"><?= e($invoice['CCCD']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('door', '', 14) ?> Phòng thuê</span>
                    <span class="info-field-val">Phòng <?= e(formatSoPhong($invoice['SoPhong'])) ?></span>
                </div>
                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('map-pin', '', 14) ?> Địa chỉ căn hộ</span>
                    <span class="info-field-val" style="font-weight: 500; font-size: 0.88rem;"><?= e($invoice['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?></span>
                </div>
                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('contract', '', 14) ?> Hợp đồng thuê</span>
                    <span class="info-field-val">#<?= e((string)$invoice['MaHopDong']) ?></span>
                </div>
            </div>
        </div>

        <!-- Cột 2: Kỳ & Trạng thái thanh toán -->
        <div class="modern-card" style="margin-bottom: 0;">
            <div class="modern-card-header">
                <h3><?= svgIcon('payment', '', 16) ?> Tình trạng thanh toán</h3>
                <?= renderStatusBadge((string)$invoice['TrangThai']) ?>
            </div>
            <div class="modern-card-body">
                <div class="kpi-total-box">
                    <div>
                        <div class="kpi-label">Tổng cộng hóa đơn</div>
                        <div class="kpi-number"><?= formatMoney($invoice['TongTien']) ?></div>
                    </div>
                    <div>
                        <?= svgIcon('invoice', '', 36) ?>
                    </div>
                </div>

                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('calendar', '', 14) ?> Kỳ thanh toán</span>
                    <span class="info-field-val" style="font-size: 1.05rem; font-weight: 700; color: #2563eb;">Kỳ <?= e($invoice['KyThanhToan']) ?></span>
                </div>
                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('history', '', 14) ?> Ngày lập hóa đơn</span>
                    <span class="info-field-val"><?= formatDateTime((string)$invoice['NgayTao']) ?></span>
                </div>
                <div class="modern-info-row">
                    <span class="info-field-label"><?= svgIcon('check', '', 14) ?> Ngày thanh toán</span>
                    <span class="info-field-val">
                        <?php if (!empty($invoice['NgayThanhToan']) && !str_starts_with((string)$invoice['NgayThanhToan'], '0000')): ?>
                            <span style="color: #16a34a; font-weight: 700;"><?= formatDateTime((string)$invoice['NgayThanhToan']) ?></span>
                        <?php else: ?>
                            <span style="color: #ea580c; font-weight: 600;">Chưa thanh toán</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- CHI TIẾT CÁC KHOẢN TIỀN -->
    <div class="modern-card">
        <div class="modern-card-header">
            <h3><?= svgIcon('chart', '', 16) ?> Chi tiết các khoản phí trong kỳ</h3>
            <span style="font-size: 0.82rem; color: #64748b; font-weight: 500;">Đơn vị: Việt Nam Đồng (VNĐ)</span>
        </div>
        <div style="overflow-x: auto;">
            <table class="fee-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">STT</th>
                        <th>Khoản mục chi phí</th>
                        <th>Diễn giải & Mô tả</th>
                        <th style="text-align: right; width: 220px;">Số tiền (VNĐ)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center; color: #64748b; font-weight: 600;">1</td>
                        <td>
                            <strong style="color: #0f172a; display: flex; align-items: center; gap: 0.4rem;">
                                <?= svgIcon('home', '', 15) ?> Tiền thuê phòng
                            </strong>
                        </td>
                        <td style="color: #64748b; font-size: 0.88rem;">Phí thuê phòng định kỳ theo hợp đồng (Phòng <?= e(formatSoPhong($invoice['SoPhong'])) ?>)</td>
                        <td style="text-align: right; font-weight: 700; font-size: 1rem; color: #0f172a;"><?= formatMoney($invoice['TienThue']) ?></td>
                    </tr>
                    <tr>
                        <td style="text-align: center; color: #64748b; font-weight: 600;">2</td>
                        <td>
                            <strong style="color: #0f172a; display: flex; align-items: center; gap: 0.4rem;">
                                <?= svgIcon('electric', '', 15) ?> Tiền điện sinh hoạt
                            </strong>
                        </td>
                        <td style="color: #64748b; font-size: 0.88rem;">Sản lượng điện tiêu thụ trong kỳ theo chỉ số công tơ</td>
                        <td style="text-align: right; font-weight: 700; font-size: 1rem; color: #0f172a;"><?= formatMoney($invoice['TienDien']) ?></td>
                    </tr>
                    <tr>
                        <td style="text-align: center; color: #64748b; font-weight: 600;">3</td>
                        <td>
                            <strong style="color: #0f172a; display: flex; align-items: center; gap: 0.4rem;">
                                <?= svgIcon('water', '', 15) ?> Tiền nước sinh hoạt
                            </strong>
                        </td>
                        <td style="color: #64748b; font-size: 0.88rem;">Chi phí nước sinh hoạt theo chỉ số hoặc định mức khoán</td>
                        <td style="text-align: right; font-weight: 700; font-size: 1rem; color: #0f172a;"><?= formatMoney($invoice['TienNuoc']) ?></td>
                    </tr>
                    <tr>
                        <td style="text-align: center; color: #64748b; font-weight: 600;">4</td>
                        <td>
                            <strong style="color: #0f172a; display: flex; align-items: center; gap: 0.4rem;">
                                <?= svgIcon('sparkles', '', 15) ?> Phí dịch vụ & tiện ích
                            </strong>
                        </td>
                        <td style="color: #64748b; font-size: 0.88rem;">Giữ xe, internet wifi, vệ sinh hành lang, thu gom rác...</td>
                        <td style="text-align: right; font-weight: 700; font-size: 1rem; color: #0f172a;"><?= formatMoney($invoice['TienDichVu']) ?></td>
                    </tr>
                    <tr class="total-highlight-row">
                        <td colspan="3" style="text-align: right; font-weight: 800; font-size: 1rem; color: #0f172a; text-transform: uppercase; letter-spacing: 0.02em;">
                            Tổng cộng tiền phải thanh toán:
                        </td>
                        <td style="text-align: right; font-weight: 800; font-size: 1.35rem; color: #1d4ed8; white-space: nowrap;">
                            <?= formatMoney($invoice['TongTien']) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LỊCH SỬ GIAO DỊCH THANH TOÁN -->
    <div class="modern-card">
        <div class="modern-card-header">
            <h3><?= svgIcon('history', '', 16) ?> Lịch sử thanh toán</h3>
            <?php if (!empty($paymentHistory)): ?>
                <span class="badge badge-success"><?= count($paymentHistory) ?> giao dịch</span>
            <?php endif; ?>
        </div>
        <div class="modern-card-body" style="padding: 0;">
            <?php if (empty($paymentHistory)): ?>
                <div style="padding: 2.5rem 1rem; text-align: center; color: #64748b;">
                    <div style="margin-bottom: 0.5rem; color: #94a3b8;"><?= svgIcon('payment', '', 36) ?></div>
                    <p style="margin: 0 0 0.75rem 0; font-weight: 500;">Chưa ghi nhận giao dịch thanh toán nào cho hóa đơn này.</p>
                    <?php if ((string)$invoice['TrangThai'] !== 'Đã TT'): ?>
                        <a href="<?= $baseUrl ?>/thanh-toan.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn btn-primary btn-sm">
                            <?= svgIcon('payment', '', 13) ?> Thu tiền ngay
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="fee-table">
                        <thead>
                            <tr>
                                <th>Thời gian giao dịch</th>
                                <th style="text-align: right;">Số tiền thanh toán</th>
                                <th style="text-align: center;">Hình thức</th>
                                <th style="text-align: center;">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentHistory as $item): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= formatDateTime((string)$item['NgayThanhToan']) ?></td>
                                    <td style="text-align: right; font-weight: 700; color: #16a34a; font-size: 1.05rem; white-space: nowrap;">
                                        <?= formatMoney($item['SoTien']) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 600; padding: 0.35rem 0.65rem; border: 1px solid #cbd5e1;">
                                            <?= e($item['HinhThuc']) === 'Chuyển khoản' ? '💳 Chuyển khoản' : '💵 Tiền mặt' ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-success">Thành công</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
