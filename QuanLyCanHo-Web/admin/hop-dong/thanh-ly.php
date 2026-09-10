<?php

declare(strict_types=1);

$title = 'Thanh Lý Hợp Đồng Thuê Căn Hộ';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url('/admin/hop-dong');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã hợp đồng không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// 1. Lấy thông tin chi tiết hợp đồng, căn hộ, khách thuê
$stmt = $pdo->prepare('
    SELECT hp.*, 
           ch.SoPhong, ch.DienTich, ch.GiaThue AS GiaNiemYet,
           lch.TenLoai,
           kt.HoTen AS TenKhach, kt.SoDienThoai, kt.CCCD, kt.Email,
           nv.HoTen AS TenNhanVien
    FROM HopDong hp
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    JOIN LoaiCanHo lch ON ch.MaLoai = lch.MaLoai
    JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
    LEFT JOIN NhanVien nv ON hp.MaNV = nv.MaNV
    WHERE hp.MaHopDong = ?
');
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    setFlash('error', 'Không tìm thấy thông tin hợp đồng cần thanh lý.');
    redirect($baseUrl . '/index.php');
}

// 2. Kiểm tra danh sách hóa đơn và công nợ của hợp đồng
$stmtBills = $pdo->prepare('
    SELECT * FROM HoaDon 
    WHERE MaHopDong = ? 
    ORDER BY MaHoaDon DESC
');
$stmtBills->execute([$id]);
$allBills = $stmtBills->fetchAll();

$unpaidBills = array_filter($allBills, fn($b) => in_array($b['TrangThai'], ['Chưa TT', 'Quá hạn'], true));
$totalDebt = array_sum(array_map(fn($b) => (float)$b['TongTien'], $unpaidBills));
$hasDebt = count($unpaidBills) > 0;

$isCompleted = false;
$successMessage = '';
$refundAmount = (float)$contract['TienCoc'];
$returnDate = date('Y-m-d');
$handoverNotes = '';

// 3. Xử lý khi Admin bấm Xác nhận thanh lý (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_liquidation') {
    verifyCsrf();

    $returnDate = trim((string)($_POST['return_date'] ?? date('Y-m-d')));
    $refundAmount = (float)($_POST['refund_amount'] ?? $contract['TienCoc']);
    $handoverNotes = trim((string)($_POST['handover_notes'] ?? ''));
    $allowOverrideDebt = isset($_POST['confirm_debt_cleared']);

    if ($contract['TrangThai'] === 'Đã thanh lý') {
        setFlash('warning', 'Hợp đồng này đã được thanh lý từ trước.');
    } elseif ($hasDebt && !$allowOverrideDebt) {
        setFlash('error', 'Khách thuê vẫn còn hóa đơn chưa thanh toán. Vui lòng xử lý công nợ hoặc tích xác nhận trước khi thanh lý.');
    } else {
        try {
            $pdo->beginTransaction();

            $noteUpdate = ($contract['GhiChu'] ? $contract['GhiChu'] . "\n" : '') . 
                          '[Thanh lý ngày ' . date('d/m/Y', strtotime($returnDate)) . ']: ' . 
                          'Đã trả phòng và tất toán cọc ' . number_format($refundAmount, 0, ',', '.') . ' đ. ' . 
                          ($handoverNotes ? 'Ghi chú: ' . $handoverNotes : '');

            // A. Cập nhật Hợp đồng sang trạng thái "Đã thanh lý"
            $updateHd = $pdo->prepare("
                UPDATE HopDong 
                SET TrangThai = 'Đã thanh lý', GhiChu = :note 
                WHERE MaHopDong = :id
            ");
            $updateHd->execute(['note' => $noteUpdate, 'id' => $id]);

            // B. Trả Căn hộ về trạng thái "Trống"
            $updateCh = $pdo->prepare("
                UPDATE CanHo 
                SET TrangThai = 'Trống' 
                WHERE MaCanHo = :maCanHo
            ");
            $updateCh->execute(['maCanHo' => $contract['MaCanHo']]);

            $pdo->commit();

            // Cập nhật lại trạng thái hiển thị
            $contract['TrangThai'] = 'Đã thanh lý';
            $isCompleted = true;
            $successMessage = 'Hợp đồng #' . $id . ' đã được thanh lý thành công! Căn hộ phòng ' . $contract['SoPhong'] . ' đã được trả về trạng thái Trống.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', 'Lỗi khi thanh lý hợp đồng: ' . $e->getMessage());
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Thanh Lý Hợp Đồng Thuê #<?= (int)$contract['MaHopDong'] ?></h1>
        <p class="page-subtitle">Xác nhận trả phòng, kiểm tra công nợ và tất toán tiền cọc (Chỉ Admin)</p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">
            ← Quay lại danh sách hợp đồng
        </a>
    </div>
</div>

<?php if ($isCompleted): ?>
    <!-- ================= GIAO DIỆN KHI VỪA THANH LÝ THÀNH CÔNG ================= -->
    <div class="card" style="max-width: 850px; margin: 0 auto 2rem; border-top: 5px solid var(--success-color);">
        <div class="card-body" style="text-align: center; padding: 2.5rem 2rem;">
            <div style="font-size: 3.5rem; color: var(--success-color); line-height: 1; margin-bottom: 1rem;">
                ✓
            </div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #065f46; margin-bottom: 0.5rem;">
                BIÊN BẢN THANH LÝ HỢP ĐỒNG ĐÃ HOÀN TẤT
            </h2>
            <p style="color: var(--text-secondary); font-size: 1rem; max-width: 600px; margin: 0 auto 1.5rem;">
                <?= e($successMessage) ?>
            </p>

            <div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); text-align: left; margin-bottom: 2rem;">
                <div class="detail-item" style="border-left: 4px solid var(--primary-color);">
                    <div class="detail-label">CĂN HỘ BÀN GIAO</div>
                    <div class="detail-value" style="font-size: 1.25rem; font-weight: 700;">
                        Phòng <?= e($contract['SoPhong']) ?>
                    </div>
                    <span style="font-size: 0.85rem; color: var(--success-color); font-weight: 600;">➔ Đã chuyển: Trống</span>
                </div>

                <div class="detail-item" style="border-left: 4px solid var(--success-color);">
                    <div class="detail-label">KHÁCH THUÊ TRẢ PHÒNG</div>
                    <div class="detail-value" style="font-size: 1.15rem; font-weight: 700;">
                        <?= e($contract['TenKhach']) ?>
                    </div>
                    <span style="font-size: 0.85rem; color: var(--text-muted);"><?= e($contract['SoDienThoai']) ?></span>
                </div>

                <div class="detail-item" style="border-left: 4px solid var(--warning-color);">
                    <div class="detail-label">TIỀN CỌC TẤT TOÁN</div>
                    <div class="detail-value" style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color);">
                        <?= formatMoney($refundAmount) ?>
                    </div>
                    <span style="font-size: 0.85rem; color: var(--text-muted);">Ngày trả: <?= formatDate($returnDate) ?></span>
                </div>
            </div>

            <div style="display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-primary">
                    📋 Về danh sách hợp đồng
                </a>
                <a href="<?= $baseUrl ?>/detail.php?id=<?= (int)$contract['MaHopDong'] ?>" class="btn btn-outline">
                    👁️ Xem chi tiết hợp đồng
                </a>
                <a href="<?= url('/admin/can-ho/index.php') ?>" class="btn btn-outline">
                    🏢 Kiểm tra căn hộ
                </a>
            </div>
        </div>
    </div>

<?php elseif ($contract['TrangThai'] === 'Đã thanh lý'): ?>
    <!-- ================= GIAO DIỆN KHI HỢP ĐỒNG ĐÃ THANH LÝ TỪ TRƯỚC ================= -->
    <div class="card" style="max-width: 800px; margin: 0 auto; border-top: 4px solid var(--secondary-color);">
        <div class="card-header" style="background-color: #f8fafc;">
            <h3>ℹ️ Thông Tin Hợp Đồng Đã Thanh Lý</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-secondary mb-3">
                <span class="alert-icon">ℹ️</span>
                <div>Hợp đồng <strong>#<?= (int)$contract['MaHopDong'] ?></strong> đã được tất toán và thanh lý thành công. Căn hộ hiện đang ở trạng thái Trống.</div>
            </div>

            <div class="detail-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                <div class="detail-item">
                    <div class="detail-label">Số phòng</div>
                    <div class="detail-value"><strong>Phòng <?= e($contract['SoPhong']) ?></strong> (<?= e($contract['TenLoai']) ?>)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Khách thuê</div>
                    <div class="detail-value"><strong><?= e($contract['TenKhach']) ?></strong> (<?= e($contract['SoDienThoai']) ?>)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Thời gian hợp đồng</div>
                    <div class="detail-value"><?= formatDate($contract['NgayBatDau']) ?> → <?= formatDate($contract['NgayKetThuc']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Trạng thái</div>
                    <div class="detail-value"><?= renderStatusBadge($contract['TrangThai']) ?></div>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-primary">Quay lại danh sách hợp đồng</a>
                <a href="<?= $baseUrl ?>/detail.php?id=<?= (int)$contract['MaHopDong'] ?>" class="btn btn-outline">Xem chi tiết</a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ================= GIAO DIỆN BƯỚC XÁC NHẬN THANH LÝ ================= -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; max-width: 1100px; margin: 0 auto;">
        <!-- CỘT 1: THÔNG TIN HỢP ĐỒNG & KHÁCH THUÊ -->
        <div>
            <div class="card mb-3">
                <div class="card-header" style="background-color: #f8fafc;">
                    <h3>📄 Thông Tin Hợp Đồng & Bàn Giao</h3>
                </div>
                <div class="card-body">
                    <div class="detail-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="detail-item">
                            <div class="detail-label">Căn hộ</div>
                            <div class="detail-value" style="font-size: 1.2rem; font-weight: 700; color: var(--primary-color);">
                                Phòng <?= e($contract['SoPhong']) ?>
                            </div>
                            <small style="color: var(--text-muted);"><?= e($contract['TenLoai']) ?> - <?= $contract['DienTich'] ?>m²</small>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Khách thuê</div>
                            <div class="detail-value" style="font-weight: 700;">
                                <?= e($contract['TenKhach']) ?>
                            </div>
                            <small style="color: var(--text-muted);"><?= e($contract['SoDienThoai']) ?></small>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Thời hạn thuê</div>
                            <div class="detail-value" style="font-size: 0.95rem;">
                                <?= formatDate($contract['NgayBatDau']) ?> → <?= formatDate($contract['NgayKetThuc']) ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Trạng thái hiện tại</div>
                            <div class="detail-value">
                                <?= renderStatusBadge($contract['TrangThai']) ?>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Giá thuê thỏa thuận</div>
                            <div class="detail-value" style="font-weight: 600; color: var(--text-primary);">
                                <?= formatMoney($contract['GiaThueThoaThuan']) ?> / tháng
                            </div>
                        </div>

                        <div class="detail-item" style="border-left: 3px solid var(--warning-color);">
                            <div class="detail-label">Tiền cọc giữ chỗ</div>
                            <div class="detail-value" style="font-weight: 700; color: var(--primary-color);">
                                <?= formatMoney($contract['TienCoc']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KIỂM TRA CÔNG NỢ HÓA ĐƠN -->
            <div class="card mb-3">
                <div class="card-header" style="background-color: #f8fafc;">
                    <h3>💳 Kiểm Tra Công Nợ & Hóa Đơn</h3>
                </div>
                <div class="card-body">
                    <?php if ($hasDebt): ?>
                        <div class="alert alert-warning mb-3">
                            <span class="alert-icon">⚠️</span>
                            <div>
                                <strong>Khách thuê còn <?= count($unpaidBills) ?> hóa đơn chưa thanh toán!</strong><br>
                                Tổng số tiền nợ: <strong style="color: var(--danger-color);"><?= formatMoney($totalDebt) ?></strong>.
                                <br><small>Theo quy định của hệ thống (SP_ThanhLyHopDong), bạn nên tất toán các hóa đơn này trước khi hoàn trả tiền cọc cho khách.</small>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th>Kỳ hóa đơn</th>
                                        <th>Tổng tiền</th>
                                        <th>Trạng thái</th>
                                        <th style="text-align: right;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unpaidBills as $b): ?>
                                        <tr>
                                            <td><strong><?= e($b['KyThanhToan']) ?></strong></td>
                                            <td style="font-weight: 600; color: var(--danger-color);"><?= formatMoney($b['TongTien']) ?></td>
                                            <td><span class="badge badge-warning"><?= e($b['TrangThai']) ?></span></td>
                                            <td style="text-align: right;">
                                                <a href="<?= url('/admin/hoa-don/index.php') ?>" class="btn btn-sm btn-outline" target="_blank">
                                                    Thu tiền →
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <span class="alert-icon">✓</span>
                            <div>
                                <strong>Đủ điều kiện thanh lý:</strong> Khách thuê không có hóa đơn tồn đọng nào. Toàn bộ tiền phòng và dịch vụ đã được thanh toán đầy đủ.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- CỘT 2: FORM XÁC NHẬN THANH LÝ HỢP ĐỒNG -->
        <div>
            <div class="card" style="border-top: 4px solid var(--danger-color);">
                <div class="card-header" style="background-color: #f8fafc;">
                    <h3 style="color: var(--danger-color);">🚪 Xác Nhận Thanh Lý & Trả Phòng</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="confirm_liquidation">

                        <div class="form-group mb-2">
                            <label for="return_date" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                                Ngày bàn giao trả phòng thực tế <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="return_date" 
                                   name="return_date" 
                                   class="form-control" 
                                   value="<?= e($returnDate) ?>" 
                                   required>
                        </div>

                        <div class="form-group mb-2">
                            <label for="refund_amount" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                                Số tiền cọc hoàn trả cho khách thuê (VNĐ) <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="refund_amount" 
                                   name="refund_amount" 
                                   class="form-control" 
                                   value="<?= (float)$contract['TienCoc'] ?>" 
                                   required>
                            <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                                Tiền cọc gốc ban đầu: <strong><?= formatMoney($contract['TienCoc']) ?></strong>. Có thể trừ chi phí nếu làm hỏng đồ đạc.
                            </small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="handover_notes" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                                Ghi chú tình trạng bàn giao & tài sản
                            </label>
                            <textarea id="handover_notes" 
                                      name="handover_notes" 
                                      class="form-control" 
                                      rows="3" 
                                      placeholder="Ví dụ: Phòng nguyên vẹn, đã chốt chỉ số điện nước cuối, bàn giao đủ 2 chìa khóa..."></textarea>
                        </div>

                        <?php if ($hasDebt): ?>
                            <div style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 0.75rem; margin-bottom: 1.25rem;">
                                <label style="display: flex; gap: 0.5rem; align-items: flex-start; cursor: pointer; font-size: 0.875rem; color: #92400e;">
                                    <input type="checkbox" name="confirm_debt_cleared" value="1" style="margin-top: 3px;" required>
                                    <span>
                                        <strong>Xác nhận tiếp tục thanh lý:</strong> Tôi xác nhận đã đối trừ công nợ còn lại trực tiếp vào tiền cọc hoàn trả hoặc khách thuê đã hoàn tất nghĩa vụ.
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <div style="background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; border: 1px dashed #cbd5e1;">
                            <div style="font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem; color: var(--text-primary);">
                                📌 Khi bấm nút xác nhận thanh lý:
                            </div>
                            <ul style="font-size: 0.85rem; color: var(--text-secondary); margin-left: 1.25rem; line-height: 1.5;">
                                <li>Hợp đồng sẽ chuyển sang trạng thái: <strong>Đã thanh lý</strong>.</li>
                                <li>Căn hộ phòng <strong><?= e($contract['SoPhong']) ?></strong> sẽ tự động chuyển về trạng thái <strong>Trống</strong> để sẵn sàng cho khách thuê mới.</li>
                                <li>Hệ thống lưu vết ngày check-out và lịch sử tất toán cọc.</li>
                            </ul>
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <button type="submit" class="btn btn-danger" style="flex: 1; padding: 0.75rem 1rem; font-weight: 600;">
                                🚪 Xác nhận thanh lý & Hoàn tất trả phòng
                            </button>
                            <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="padding: 0.75rem 1rem;">
                                Hủy bỏ
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
