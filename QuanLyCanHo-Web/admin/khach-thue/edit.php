<?php

declare(strict_types=1);

$title = 'Sửa Khách thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = (currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã khách thuê không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// Fetch thông tin khách thuê hiện tại
$stmt = $pdo->prepare('SELECT * FROM KhachThue WHERE MaKhach = ?');
$stmt->execute([$id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    setFlash('error', 'Không tìm thấy thông tin khách thuê.');
    redirect($baseUrl . '/index.php');
}

$errors = [];
$formData = [
    'HoTen' => $tenant['HoTen'],
    'CCCD' => $tenant['CCCD'],
    'NgaySinh' => $tenant['NgaySinh'],
    'GioiTinh' => $tenant['GioiTinh'],
    'SoDienThoai' => $tenant['SoDienThoai'],
    'Email' => $tenant['Email'] ?? '',
    'DiaChiThuongTru' => $tenant['DiaChiThuongTru'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['HoTen'] = trim($_POST['HoTen'] ?? '');
    $formData['CCCD'] = trim($_POST['CCCD'] ?? '');
    $formData['NgaySinh'] = trim($_POST['NgaySinh'] ?? '');
    $formData['GioiTinh'] = trim($_POST['GioiTinh'] ?? 'Nam');
    $formData['SoDienThoai'] = trim($_POST['SoDienThoai'] ?? '');
    $formData['Email'] = trim($_POST['Email'] ?? '');
    $formData['DiaChiThuongTru'] = trim($_POST['DiaChiThuongTru'] ?? '');

    // Server-side Validation
    if ($formData['HoTen'] === '') {
        $errors['HoTen'] = 'Họ tên khách thuê không được để trống.';
    }

    if ($formData['CCCD'] === '') {
        $errors['CCCD'] = 'Số CCCD/CMND không được để trống.';
    } else {
        // Kiểm tra CCCD không trùng với khách thuê KHÁC
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM KhachThue WHERE CCCD = ? AND MaKhach <> ?');
        $checkStmt->execute([$formData['CCCD'], $id]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            $errors['CCCD'] = 'Số CCCD này đã thuộc về một khách thuê khác trong hệ thống.';
        }
    }

    if ($formData['NgaySinh'] === '') {
        $errors['NgaySinh'] = 'Ngày sinh không được để trống.';
    }

    if ($formData['GioiTinh'] === '') {
        $errors['GioiTinh'] = 'Vui lòng chọn giới tính.';
    }

    if ($formData['SoDienThoai'] === '') {
        $errors['SoDienThoai'] = 'Số điện thoại không được để trống.';
    }

    if ($formData['Email'] !== '' && !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
        $errors['Email'] = 'Địa chỉ Email không đúng định dạng.';
    }

    // Nếu hợp lệ -> Cập nhật CSDL
    if (empty($errors)) {
        try {
            $updateSql = 'UPDATE KhachThue 
                          SET HoTen = :hoTen, 
                              CCCD = :cccd, 
                              NgaySinh = :ngaySinh, 
                              GioiTinh = :gioiTinh, 
                              SoDienThoai = :soDienThoai, 
                              Email = :email, 
                              DiaChiThuongTru = :diaChi 
                          WHERE MaKhach = :id';
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':hoTen' => $formData['HoTen'],
                ':cccd' => $formData['CCCD'],
                ':ngaySinh' => $formData['NgaySinh'],
                ':gioiTinh' => $formData['GioiTinh'],
                ':soDienThoai' => $formData['SoDienThoai'],
                ':email' => ($formData['Email'] !== '') ? $formData['Email'] : null,
                ':diaChi' => ($formData['DiaChiThuongTru'] !== '') ? $formData['DiaChiThuongTru'] : null,
                ':id' => $id,
            ]);

            setFlash('success', 'Cập nhật thông tin khách thuê "' . $formData['HoTen'] . '" thành công!');
            redirect($baseUrl . '/detail.php?id=' . $id);
        } catch (PDOException $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sửa Thông Tin Khách Thuê</h1>
        <p class="page-subtitle">Cập nhật hồ sơ thông tin cho khách thuê #<?= $id ?></p>
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

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3>Chỉnh Sửa Thông Tin</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                <!-- Họ tên -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="HoTen" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Họ và Tên <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="HoTen" 
                           name="HoTen" 
                           class="form-control" 
                           value="<?= e($formData['HoTen']) ?>" 
                           required>
                    <?php if (isset($errors['HoTen'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['HoTen']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- CCCD -->
                <div class="form-group">
                    <label for="CCCD" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Số CCCD / CMND <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="CCCD" 
                           name="CCCD" 
                           class="form-control" 
                           value="<?= e($formData['CCCD']) ?>" 
                           required>
                    <?php if (isset($errors['CCCD'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['CCCD']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Ngày sinh -->
                <div class="form-group">
                    <label for="NgaySinh" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ngày sinh <span class="required">*</span>
                    </label>
                    <input type="date" 
                           id="NgaySinh" 
                           name="NgaySinh" 
                           class="form-control" 
                           value="<?= e($formData['NgaySinh']) ?>" 
                           required>
                    <?php if (isset($errors['NgaySinh'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['NgaySinh']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Giới tính -->
                <div class="form-group">
                    <label for="GioiTinh" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Giới tính <span class="required">*</span>
                    </label>
                    <select id="GioiTinh" name="GioiTinh" class="form-control" required>
                        <option value="Nam" <?= ($formData['GioiTinh'] === 'Nam') ? 'selected' : '' ?>>Nam</option>
                        <option value="Nữ" <?= ($formData['GioiTinh'] === 'Nữ') ? 'selected' : '' ?>>Nữ</option>
                        <option value="Khác" <?= ($formData['GioiTinh'] === 'Khác') ? 'selected' : '' ?>>Khác</option>
                    </select>
                </div>

                <!-- Số điện thoại -->
                <div class="form-group">
                    <label for="SoDienThoai" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Số điện thoại <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="SoDienThoai" 
                           name="SoDienThoai" 
                           class="form-control" 
                           value="<?= e($formData['SoDienThoai']) ?>" 
                           required>
                    <?php if (isset($errors['SoDienThoai'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['SoDienThoai']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="Email" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Địa chỉ Email
                    </label>
                    <input type="email" 
                           id="Email" 
                           name="Email" 
                           class="form-control" 
                           value="<?= e($formData['Email']) ?>">
                    <?php if (isset($errors['Email'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['Email']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Địa chỉ thường trú -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="DiaChiThuongTru" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Địa chỉ thường trú
                    </label>
                    <input type="text" 
                           id="DiaChiThuongTru" 
                           name="DiaChiThuongTru" 
                           class="form-control" 
                           value="<?= e($formData['DiaChiThuongTru']) ?>">
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="<?= $baseUrl ?>/detail.php?id=<?= $id ?>" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary">💾 Cập Nhật Thay Đổi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
