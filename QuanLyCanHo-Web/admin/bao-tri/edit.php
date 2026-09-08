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
$sql = 'SELECT bt.*, ch.SoPhong, kt.HoTen AS TenKhach
        FROM YeuCauBaoTri bt
        JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
        WHERE bt.MaBaoTri = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('error', 'Không tìm thấy yêu cầu bảo trì.');
    redirect($baseUrl . '/index.php');
}

$errors = [];
$formData = [
    'TrangThai' => $item['TrangThai'],
    'NgayHoanThanh' => $item['NgayHoanThanh'] ? date('Y-m-d\TH:i', strtotime($item['NgayHoanThanh'])) : '',
    'ChiPhi' => ($item['ChiPhi'] !== null) ? (string)$item['ChiPhi'] : '',
    'GhiChu' => $item['GhiChu'] ?? '',
    'NoiDung' => $item['NoiDung'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['TrangThai'] = trim($_POST['TrangThai'] ?? 'Đã tiếp nhận');
    $formData['NgayHoanThanh'] = trim($_POST['NgayHoanThanh'] ?? '');
    $formData['ChiPhi'] = trim($_POST['ChiPhi'] ?? '');
    $formData['GhiChu'] = trim($_POST['GhiChu'] ?? '');
    $formData['NoiDung'] = trim($_POST['NoiDung'] ?? $item['NoiDung']);

    // Logic kiểm tra ChiPhi
    $chiPhiVal = null;
    if ($formData['ChiPhi'] !== '') {
        $chiPhiVal = (float)$formData['ChiPhi'];
        if ($chiPhiVal < 0) {
            $errors['ChiPhi'] = 'Chi phí bảo trì không được là số âm.';
        }
    }

    // Logic kiểm tra TrangThai & NgayHoanThanh
    $ngayHoanThanhVal = null;
    if ($formData['TrangThai'] === 'Hoàn thành') {
        if ($formData['NgayHoanThanh'] !== '') {
            $ngayHoanThanhVal = date('Y-m-d H:i:s', strtotime($formData['NgayHoanThanh']));
        } else {
            // Tự động gán thời gian hiện tại khi hoàn thành
            $ngayHoanThanhVal = date('Y-m-d H:i:s');
        }
    } else {
        // Trạng thái khác "Hoàn thành" -> Ngày hoàn thành có thể NULL
        if ($formData['NgayHoanThanh'] !== '') {
            $ngayHoanThanhVal = date('Y-m-d H:i:s', strtotime($formData['NgayHoanThanh']));
        }
    }

    if (empty($errors)) {
        try {
            $updateSql = 'UPDATE YeuCauBaoTri 
                          SET NoiDung = :noiDung,
                              TrangThai = :trangThai, 
                              NgayHoanThanh = :ngayHoanThanh, 
                              ChiPhi = :chiPhi, 
                              GhiChu = :ghiChu 
                          WHERE MaBaoTri = :id';
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':noiDung' => $formData['NoiDung'],
                ':trangThai' => $formData['TrangThai'],
                ':ngayHoanThanh' => $ngayHoanThanhVal,
                ':chiPhi' => $chiPhiVal,
                ':ghiChu' => ($formData['GhiChu'] !== '') ? $formData['GhiChu'] : null,
                ':id' => $id,
            ]);

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
        <p class="page-subtitle">Phòng <?= e($item['SoPhong']) ?> | Khách: <?= e($item['TenKhach']) ?></p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/detail.php?id=<?= $id ?>" class="btn btn-outline">
            ← Quay lại chi tiết
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger">
        <span class="alert-icon">✕</span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 750px; margin: 0 auto;">
    <div class="card-header">
        <h3>Cập Nhật Xử Lý Sự Cố</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                
                <!-- Nội dung yêu cầu -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="NoiDung" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Nội dung bảo trì
                    </label>
                    <textarea id="NoiDung" name="NoiDung" class="form-control" rows="2" required><?= e($formData['NoiDung']) ?></textarea>
                </div>

                <!-- Trạng thái -->
                <div class="form-group">
                    <label for="TrangThai" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Trạng thái xử lý <span class="required">*</span>
                    </label>
                    <select id="TrangThai" name="TrangThai" class="form-control" required>
                        <option value="Đã tiếp nhận" <?= ($formData['TrangThai'] === 'Đã tiếp nhận') ? 'selected' : '' ?>>Đã tiếp nhận</option>
                        <option value="Đang xử lý" <?= ($formData['TrangThai'] === 'Đang xử lý') ? 'selected' : '' ?>>Đang xử lý</option>
                        <option value="Hoàn thành" <?= ($formData['TrangThai'] === 'Hoàn thành') ? 'selected' : '' ?>>Hoàn thành</option>
                    </select>
                </div>

                <!-- Chi phí -->
                <div class="form-group">
                    <label for="ChiPhi" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Chi phí bảo trì (VNĐ)
                    </label>
                    <input type="number" 
                           step="1000" 
                           min="0" 
                           id="ChiPhi" 
                           name="ChiPhi" 
                           class="form-control" 
                           placeholder="Nhập chi phí (nếu có)" 
                           value="<?= e($formData['ChiPhi']) ?>">
                    <?php if (isset($errors['ChiPhi'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['ChiPhi']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Ngày hoàn thành -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="NgayHoanThanh" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ngày giờ hoàn thành
                    </label>
                    <input type="datetime-local" 
                           id="NgayHoanThanh" 
                           name="NgayHoanThanh" 
                           class="form-control" 
                           value="<?= e($formData['NgayHoanThanh']) ?>">
                    <small style="color: var(--text-muted);">
                        * Nếu chọn trạng thái "Hoàn thành" mà để trống ngày, hệ thống sẽ tự động lấy thời gian hiện tại.
                    </small>
                </div>

                <!-- Ghi chú -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="GhiChu" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ghi chú chi tiết kết quả xử lý
                    </label>
                    <input type="text" 
                           id="GhiChu" 
                           name="GhiChu" 
                           class="form-control" 
                           placeholder="Ví dụ: Đã thay linh kiện hỏng, nạp gas..." 
                           value="<?= e($formData['GhiChu']) ?>">
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="<?= $baseUrl ?>/detail.php?id=<?= $id ?>" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary">💾 Cập Nhật Tiến Độ</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
