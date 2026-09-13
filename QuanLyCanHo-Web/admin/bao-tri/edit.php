<?php

declare(strict_types=1);

$title = 'Cập nhật Yêu cầu Bảo trì';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/bao-tri' : '/user/bao-tri');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã yêu cầu bảo trì không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// Query chi tiết yêu cầu bảo trì JOIN Căn hộ và Khách thuê
$sql = 'SELECT bt.*, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach
        FROM YeuCauBaoTri bt
        LEFT JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
        LEFT JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
        WHERE bt.MaBaoTri = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('error', 'Không tìm thấy yêu cầu bảo trì.');
    redirect($baseUrl . '/index.php');
}

if (!isStaffAssignedBuilding((string)($item['DiaChi'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền chỉnh sửa yêu cầu bảo trì thuộc tòa nhà này.');
    redirect($baseUrl . '/index.php');
}

$errors = [];
$formData = [
    'LoaiSuCo' => $item['LoaiSuCo'] ?? 'Khác',
    'MucDoUuTien' => $item['MucDoUuTien'] ?? 'Trung bình',
    'NguoiChiuChiPhi' => $item['NguoiChiuChiPhi'] ?? 'Chủ nhà',
    'TrangThai' => $item['TrangThai'] ?? 'Đã tiếp nhận',
    'NgayHoanThanh' => $item['NgayHoanThanh'] ? date('Y-m-d\TH:i', strtotime($item['NgayHoanThanh'])) : '',
    'ChiPhi' => ($item['ChiPhi'] !== null) ? (string)$item['ChiPhi'] : '0',
    'GhiChu' => $item['GhiChu'] ?? '',
    'NoiDung' => $item['NoiDung'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $formData['LoaiSuCo'] = trim($_POST['LoaiSuCo'] ?? ($item['LoaiSuCo'] ?? 'Khác'));
    $formData['MucDoUuTien'] = trim($_POST['MucDoUuTien'] ?? ($item['MucDoUuTien'] ?? 'Trung bình'));
    $formData['NguoiChiuChiPhi'] = trim($_POST['NguoiChiuChiPhi'] ?? 'Chủ nhà');
    $formData['TrangThai'] = trim($_POST['TrangThai'] ?? 'Đã tiếp nhận');
    if (!in_array($formData['TrangThai'], ['Đã tiếp nhận', 'Đang xử lý', 'Hoàn thành'], true)) {
        $formData['TrangThai'] = 'Đã tiếp nhận';
    }
    $formData['NgayHoanThanh'] = trim($_POST['NgayHoanThanh'] ?? '');
    $formData['ChiPhi'] = trim($_POST['ChiPhi'] ?? '0');
    $formData['GhiChu'] = trim($_POST['GhiChu'] ?? '');
    $formData['NoiDung'] = trim($_POST['NoiDung'] ?? $item['NoiDung']);

    $chiPhiVal = null;
    if ($formData['ChiPhi'] !== '') {
        $rawChiPhi = trim((string)$formData['ChiPhi']);
        $cleanChiPhi = str_replace(['.', ',', ' '], '', $rawChiPhi);
        $chiPhiVal = (float)$cleanChiPhi;
        if ($chiPhiVal > 0 && $chiPhiVal < 1000) {
            $chiPhiVal *= 1000;
        }
        if ($chiPhiVal < 0) {
            $errors['ChiPhi'] = 'Chi phí bảo trì không được là số âm.';
        }
    }

    $ngayHoanThanhVal = null;
    if ($formData['TrangThai'] === 'Hoàn thành') {
        if ($formData['NgayHoanThanh'] !== '') {
            $ngayHoanThanhVal = date('Y-m-d H:i:s', strtotime($formData['NgayHoanThanh']));
        } else {
            $ngayHoanThanhVal = date('Y-m-d H:i:s');
        }
    } else {
        if ($formData['NgayHoanThanh'] !== '') {
            $ngayHoanThanhVal = date('Y-m-d H:i:s', strtotime($formData['NgayHoanThanh']));
        }
    }

    if (empty($errors)) {
        try {
            $updateSql = 'UPDATE YeuCauBaoTri 
                          SET LoaiSuCo = :loaiSuCo,
                              MucDoUuTien = :mucDoUuTien,
                              NguoiChiuChiPhi = :nguoiChiuCP,
                              NoiDung = :noiDung,
                              TrangThai = :trangThai, 
                              NgayHoanThanh = :ngayHoanThanh, 
                              ChiPhi = :chiPhi, 
                              GhiChu = :ghiChu 
                          WHERE MaBaoTri = :id';
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':loaiSuCo'     => $formData['LoaiSuCo'],
                ':mucDoUuTien'  => $formData['MucDoUuTien'],
                ':nguoiChiuCP'  => $formData['NguoiChiuChiPhi'],
                ':noiDung'      => $formData['NoiDung'],
                ':trangThai'    => $formData['TrangThai'],
                ':ngayHoanThanh'=> $ngayHoanThanhVal,
                ':chiPhi'       => $chiPhiVal,
                ':ghiChu'       => ($formData['GhiChu'] !== '') ? $formData['GhiChu'] : null,
                ':id'           => $id,
            ]);

            logAudit('UPDATE', 'YeuCauBaoTri', (string)$id, "Cập nhật sự cố #{$id} - Trạng thái: {$formData['TrangThai']} - Chi phí: " . number_format((float)$chiPhiVal) . "đ");

            setFlash('success', 'Cập nhật yêu cầu bảo trì #' . $id . ' thành công!');
            redirect($baseUrl . '/detail.php?id=' . $id);
        } catch (PDOException $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Cập Nhật Yêu Cầu Bảo Trì #<?= $id ?></h1>
        <p class="page-subtitle">Phòng <?= e($item['SoPhong'] ?? 'Chung') ?> | Khách: <?= e($item['TenKhach'] ?: 'Chưa gán khách') ?></p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/detail.php?id=<?= $id ?>" class="btn btn-outline">
            ← Quay lại chi tiết
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 820px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3 style="margin:0;">Cập Nhật Xử Lý Sự Cố</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                
                <!-- Trạng thái -->
                <div class="form-group">
                    <label for="TrangThai" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Trạng thái xử lý <span class="required" style="color: var(--danger-color);">*</span>
                    </label>
                    <select id="TrangThai" name="TrangThai" class="form-control" required>
                        <option value="Đã tiếp nhận" <?= ($formData['TrangThai'] === 'Đã tiếp nhận') ? 'selected' : '' ?>>Đã tiếp nhận</option>
                        <option value="Đang xử lý" <?= ($formData['TrangThai'] === 'Đang xử lý') ? 'selected' : '' ?>>Đang xử lý</option>
                        <option value="Hoàn thành" <?= ($formData['TrangThai'] === 'Hoàn thành') ? 'selected' : '' ?>>Hoàn thành</option>
                    </select>
                </div>

                <!-- Người chịu chi phí -->
                <div class="form-group">
                    <label for="NguoiChiuChiPhi" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Người Chịu Chi Phí
                    </label>
                    <select id="NguoiChiuChiPhi" name="NguoiChiuChiPhi" class="form-control">
                        <option value="Chủ nhà" <?= ($formData['NguoiChiuChiPhi'] === 'Chủ nhà') ? 'selected' : '' ?>>Chủ nhà</option>
                        <option value="Khách thuê" <?= ($formData['NguoiChiuChiPhi'] === 'Khách thuê') ? 'selected' : '' ?>>Khách thuê</option>
                    </select>
                </div>

                <!-- Chi phí thực tế -->
                <div class="form-group">
                    <label for="ChiPhi" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Chi phí bảo trì / linh kiện (VNĐ)
                    </label>
                    <input type="text" 
                           id="ChiPhi" 
                           name="ChiPhi" 
                           class="form-control currency-mask" 
                           placeholder="Ví dụ: 100.000" 
                           value="<?= (isset($formData['ChiPhi']) && $formData['ChiPhi'] !== '' && is_numeric(str_replace('.', '', (string)$formData['ChiPhi']))) ? number_format((float)str_replace('.', '', (string)$formData['ChiPhi']), 0, '', '.') : e($formData['ChiPhi']) ?>">
                    <?php if (isset($errors['ChiPhi'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['ChiPhi']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Ngày hoàn thành -->
                <div class="form-group">
                    <label for="NgayHoanThanh" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ngày giờ hoàn thành
                    </label>
                    <input type="datetime-local" 
                           id="NgayHoanThanh" 
                           name="NgayHoanThanh" 
                           class="form-control" 
                           value="<?= e($formData['NgayHoanThanh']) ?>">
                </div>

                <!-- Nội dung yêu cầu -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="NoiDung" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Mô tả sự cố
                    </label>
                    <textarea id="NoiDung" name="NoiDung" class="form-control" rows="2" required><?= e($formData['NoiDung']) ?></textarea>
                </div>

                <!-- Ghi chú -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="GhiChu" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ghi Chú
                    </label>
                    <input type="text" 
                           id="GhiChu" 
                           name="GhiChu" 
                           class="form-control" 
                           value="<?= e($formData['GhiChu']) ?>">
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="<?= $baseUrl ?>/detail.php?id=<?= $id ?>" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary" style="font-weight:600;"><?= svgIcon('check', '', 16) ?> Cập Nhật Tiến Độ</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
