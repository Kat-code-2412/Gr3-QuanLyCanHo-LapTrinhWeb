<?php

declare(strict_types=1);

$title = 'Chỉnh sửa hóa đơn';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Không tìm thấy hóa đơn cần chỉnh sửa.');
    redirect($baseUrl . '/index.php');
}

$stmt = $pdo->prepare('
    SELECT hd.*, hp.MaHopDong, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach, kt.SoDienThoai
    FROM HoaDon hd
    JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
    JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
    JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
    WHERE hd.MaHoaDon = ?
');
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('error', 'Hóa đơn không tồn tại.');
    redirect($baseUrl . '/index.php');
}

if (!isStaffAssignedBuilding((string)($invoice['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền chỉnh sửa hóa đơn thuộc tòa nhà này.');
    redirect($baseUrl . '/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kyThanhToan = trim((string)($_POST['ky_thanh_toan'] ?? ''));
    
    // Xử lý loại bỏ dấu chấm ngăn cách nghìn và chuẩn hóa số tiền
    $cleanNumber = function($val, $type = 'other'): float {
        $clean = str_replace('.', '', trim((string)$val));
        $num = (float)$clean;
        return normalizeServiceFee($num, $type);
    };

    $tienThue = (float)str_replace('.', '', trim((string)($_POST['tien_thue'] ?? '0')));
    if ($tienThue > 0 && $tienThue < 10000) {
        $tienThue *= 1000;
    }
    $tienDien = $cleanNumber($_POST['tien_dien'] ?? 0, 'dien');
    $tienNuoc = $cleanNumber($_POST['tien_nuoc'] ?? 0, 'nuoc');
    $tienDichVu = $cleanNumber($_POST['tien_dich_vu'] ?? 0, 'dichvu');
    $tongTien = (float)str_replace('.', '', trim((string)($_POST['tong_tien'] ?? '0')));
    if ($tongTien > 0 && $tongTien < 10000) {
        $tongTien *= 1000;
    }
    if ($tongTien <= 0) {
        $tongTien = $tienThue + $tienDien + $tienNuoc + $tienDichVu;
    }

    $trangThai = trim((string)($_POST['trang_thai'] ?? 'Chưa TT'));
    $trangThaiThanhToan = trim((string)($_POST['trang_thai_thanh_toan'] ?? 'Chưa thanh toán'));
    $ngayThanhToan = trim((string)($_POST['ngay_thanh_toan'] ?? ''));

    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{4}$/', $kyThanhToan)) {
        $error = 'Kỳ thanh toán không đúng định dạng MM/YYYY (Ví dụ: 10/2026).';
    } elseif ($tienThue < 0 || $tienDien < 0 || $tienNuoc < 0 || $tienDichVu < 0 || $tongTien < 0) {
        $error = 'Số tiền không được là số âm.';
    } else {
        try {
            $formattedNgayTT = null;
            if ($trangThaiThanhToan === 'Đã thanh toán' || $trangThai === 'Đã TT') {
                $formattedNgayTT = $ngayThanhToan !== '' ? date('Y-m-d H:i:s', strtotime($ngayThanhToan)) : date('Y-m-d H:i:s');
                $trangThai = 'Đã TT';
                $trangThaiThanhToan = 'Đã thanh toán';
            } else {
                $formattedNgayTT = null;
                if ($trangThai === 'Đã TT') {
                    $trangThai = 'Chưa TT';
                }
                $trangThaiThanhToan = 'Chưa thanh toán';
            }

            $updateStmt = $pdo->prepare('
                UPDATE HoaDon 
                SET KyThanhToan = ?, TienThue = ?, TienDien = ?, TienNuoc = ?, TienDichVu = ?, TongTien = ?, TrangThai = ?, TrangThaiThanhToan = ?, NgayThanhToan = ?
                WHERE MaHoaDon = ?
            ');
            $updateStmt->execute([
                $kyThanhToan,
                $tienThue,
                $tienDien,
                $tienNuoc,
                $tienDichVu,
                $tongTien,
                $trangThai,
                $trangThaiThanhToan,
                $formattedNgayTT,
                $id
            ]);

            logAudit('UPDATE_INVOICE', 'HoaDon', (string)$id, 'Cập nhật hóa đơn #' . $id . ' kỳ ' . $kyThanhToan);
            setFlash('success', 'Đã lưu thay đổi hóa đơn #' . $id . ' thành công.');
            redirect($baseUrl . '/index.php?ky_thanh_toan=' . urlencode($kyThanhToan));
        } catch (Throwable $e) {
            $error = 'Lỗi khi cập nhật hóa đơn: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Chỉnh sửa hóa đơn #<?= (int)$invoice['MaHoaDon'] ?></h1>
        <p class="text-muted" style="margin: 4px 0 0 0; font-size: 0.9rem;">
            Phòng: <strong><?= e(formatSoPhong($invoice['SoPhong'])) ?></strong> &bull; Địa chỉ: <strong><?= e($invoice['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?></strong> &bull; Khách thuê: <strong><?= e($invoice['TenKhach']) ?></strong> (<?= e($invoice['SoDienThoai']) ?>) &bull; Hợp đồng: <strong>#<?= e((string)$invoice['MaHopDong']) ?></strong>
        </p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">&larr; Quay lại danh sách</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3>Thông tin chi tiết hóa đơn</h3>
    </div>
    <div class="card-body">
        <form method="POST" id="editInvoiceForm">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="row" style="display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="ky_thanh_toan" style="font-weight: 600;">Kỳ thanh toán (MM/YYYY) <span class="text-danger">*</span></label>
                    <input type="text" id="ky_thanh_toan" name="ky_thanh_toan" class="form-control" value="<?= e($invoice['KyThanhToan']) ?>" placeholder="Ví dụ: 10/2026" required>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">Địa chỉ căn hộ</label>
                    <input type="text" class="form-control" value="<?= e($invoice['DiaChi'] ?: 'Chưa cập nhật địa chỉ') ?>" readonly style="background-color: #f8fafc;">
                </div>
                <div class="form-group">
                    <label for="ngay_tao" style="font-weight: 600;">Ngày tạo hóa đơn</label>
                    <input type="text" id="ngay_tao" class="form-control" value="<?= formatDate($invoice['NgayTao']) ?>" readonly style="background-color: #f8fafc;">
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; color: #1e293b; font-weight: 600;">Chi tiết các khoản phí (VNĐ)</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="tien_thue" style="font-weight: 600; font-size: 0.88rem;">Tiền phòng / Thuê (VNĐ)</label>
                        <input type="text" id="tien_thue" name="tien_thue" class="form-control fee-input currency-mask" value="<?= number_format((float)$invoice['TienThue'], 0, '', '.') ?>" placeholder="Ví dụ: 6.500.000" required>
                    </div>

                    <div class="form-group">
                        <label for="tien_dien" style="font-weight: 600; font-size: 0.88rem;">Tiền điện (VNĐ)</label>
                        <input type="text" id="tien_dien" name="tien_dien" class="form-control fee-input currency-mask" value="<?= number_format((float)$invoice['TienDien'], 0, '', '.') ?>" placeholder="Ví dụ: 200.000" required>
                    </div>

                    <div class="form-group">
                        <label for="tien_nuoc" style="font-weight: 600; font-size: 0.88rem;">Tiền nước (VNĐ)</label>
                        <input type="text" id="tien_nuoc" name="tien_nuoc" class="form-control fee-input currency-mask" value="<?= number_format((float)$invoice['TienNuoc'], 0, '', '.') ?>" placeholder="Ví dụ: 100.000" required>
                    </div>

                    <div class="form-group">
                        <label for="tien_dich_vu" style="font-weight: 600; font-size: 0.88rem;">Tiền dịch vụ (Xe, Wifi, Vệ sinh...)</label>
                        <input type="text" id="tien_dich_vu" name="tien_dich_vu" class="form-control fee-input currency-mask" value="<?= number_format((float)$invoice['TienDichVu'], 0, '', '.') ?>" placeholder="Ví dụ: 90.000" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem; border-top: 1px dashed #cbd5e1; padding-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label for="tong_tien" style="font-weight: 700; font-size: 1rem; color: #1d4ed8; margin: 0;">TỔNG TIỀN THANH TOÁN (VNĐ)</label>
                        <button type="button" id="btnAutoCalc" class="btn btn-sm btn-outline" style="font-size: 0.8rem;">Tự động tính lại tổng</button>
                    </div>
                    <input type="text" id="tong_tien" name="tong_tien" class="form-control currency-mask" value="<?= number_format((float)$invoice['TongTien'], 0, '', '.') ?>" style="font-size: 1.15rem; font-weight: 700; color: #1d4ed8; margin-top: 0.5rem;" required>
                    <small class="text-muted">Tổng tiền có dấu chấm phân cách hàng nghìn, tự động cập nhật khi thay đổi các khoản phí bên trên.</small>
                </div>
            </div>

            <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="trang_thai" style="font-weight: 600;">Trạng thái hiển thị</label>
                    <select id="trang_thai" name="trang_thai" class="form-control">
                        <option value="Chưa TT" <?= $invoice['TrangThai'] === 'Chưa TT' ? 'selected' : '' ?>>Chưa TT</option>
                        <option value="Đã TT" <?= $invoice['TrangThai'] === 'Đã TT' ? 'selected' : '' ?>>Đã TT</option>
                        <option value="Quá hạn" <?= $invoice['TrangThai'] === 'Quá hạn' ? 'selected' : '' ?>>Quá hạn</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="trang_thai_thanh_toan" style="font-weight: 600;">Tình trạng thanh toán</label>
                    <select id="trang_thai_thanh_toan" name="trang_thai_thanh_toan" class="form-control">
                        <option value="Chưa thanh toán" <?= $invoice['TrangThaiThanhToan'] === 'Chưa thanh toán' ? 'selected' : '' ?>>Chưa thanh toán</option>
                        <option value="Đã thanh toán" <?= $invoice['TrangThaiThanhToan'] === 'Đã thanh toán' ? 'selected' : '' ?>>Đã thanh toán</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="ngayThanhToanGroup" style="margin-bottom: 1.5rem; <?= $invoice['TrangThaiThanhToan'] === 'Đã thanh toán' ? '' : 'display:none;' ?>">
                <label for="ngay_thanh_toan" style="font-weight: 600;">Ngày thanh toán</label>
                <input type="datetime-local" id="ngay_thanh_toan" name="ngay_thanh_toan" class="form-control" value="<?= $invoice['NgayThanhToan'] ? date('Y-m-d\TH:i', strtotime($invoice['NgayThanhToan'])) : date('Y-m-d\TH:i') ?>">
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.fee-input');
    const tongTienInput = document.getElementById('tong_tien');
    const statusSelect = document.getElementById('trang_thai');
    const paymentStatusSelect = document.getElementById('trang_thai_thanh_toan');
    const ngayTTGroup = document.getElementById('ngayThanhToanGroup');

    // Tự động định dạng dấu chấm hàng nghìn khi người dùng gõ
    document.querySelectorAll('.currency-mask').forEach(input => {
        input.addEventListener('input', function() {
            let raw = this.value.replace(/\D/g, '');
            if (raw !== '') {
                this.value = Number(raw).toLocaleString('vi-VN');
            } else {
                this.value = '';
            }
        });
    });

    function calcTotal() {
        let sum = 0;
        inputs.forEach(input => {
            const raw = input.value.replace(/\D/g, '');
            const val = parseFloat(raw) || 0;
            sum += val;
        });
        tongTienInput.value = sum > 0 ? Number(sum).toLocaleString('vi-VN') : '0';
    }

    inputs.forEach(input => {
        input.addEventListener('input', calcTotal);
    });

    document.getElementById('btnAutoCalc')?.addEventListener('click', calcTotal);

    paymentStatusSelect.addEventListener('change', function() {
        if (this.value === 'Đã thanh toán') {
            statusSelect.value = 'Đã TT';
            ngayTTGroup.style.display = 'block';
        } else {
            statusSelect.value = 'Chưa TT';
            ngayTTGroup.style.display = 'none';
        }
    });

    statusSelect.addEventListener('change', function() {
        if (this.value === 'Đã TT') {
            paymentStatusSelect.value = 'Đã thanh toán';
            ngayTTGroup.style.display = 'block';
        } else {
            paymentStatusSelect.value = 'Chưa thanh toán';
            ngayTTGroup.style.display = 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
