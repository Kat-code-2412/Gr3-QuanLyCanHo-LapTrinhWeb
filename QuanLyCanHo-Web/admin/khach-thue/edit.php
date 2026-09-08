<?php

declare(strict_types=1);

$title = 'Sửa Thông Tin Khách Thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/khach-thue' : '/user/khach-thue');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã khách thuê không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// Fetch thông tin hiện tại
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
    'NgheNghiep' => $tenant['NgheNghiep'] ?? '',
    'GhiChu' => $tenant['GhiChu'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['HoTen'] = trim($_POST['HoTen'] ?? '');
    $formData['CCCD'] = trim($_POST['CCCD'] ?? '');
    $formData['NgaySinh'] = trim($_POST['NgaySinh'] ?? '');
    $formData['GioiTinh'] = trim($_POST['GioiTinh'] ?? 'Nam');
    $formData['SoDienThoai'] = trim($_POST['SoDienThoai'] ?? '');
    $formData['Email'] = trim($_POST['Email'] ?? '');
    $formData['DiaChiThuongTru'] = trim($_POST['DiaChiThuongTru'] ?? '');
    $formData['NgheNghiep'] = trim($_POST['NgheNghiep'] ?? '');
    $formData['GhiChu'] = trim($_POST['GhiChu'] ?? '');

    // 1. Kiểm tra Họ tên
    if ($formData['HoTen'] === '') {
        $errors['HoTen'] = 'Họ tên không được để trống.';
    }

    // 2. Kiểm tra Số điện thoại
    if ($formData['SoDienThoai'] === '') {
        $errors['SoDienThoai'] = 'Số điện thoại không được để trống.';
    } elseif (!preg_match('/^[0-9]{9,11}$/', $formData['SoDienThoai'])) {
        $errors['SoDienThoai'] = 'Số điện thoại không đúng định dạng (từ 9 đến 11 chữ số).';
    } else {
        // Kiểm tra trùng SĐT với người khác
        $checkPhone = $pdo->prepare('SELECT COUNT(*) FROM KhachThue WHERE SoDienThoai = ? AND MaKhach <> ?');
        $checkPhone->execute([$formData['SoDienThoai'], $id]);
        if ((int)$checkPhone->fetchColumn() > 0) {
            $errors['SoDienThoai'] = 'Số điện thoại này đã được sử dụng bởi khách thuê khác.';
        }
    }

    // 3. Kiểm tra CCCD
    if ($formData['CCCD'] !== '') {
        if (!preg_match('/^[0-9]{9,12}$/', $formData['CCCD'])) {
            $errors['CCCD'] = 'Số CCCD/CMND phải chứa từ 9 đến 12 chữ số.';
        } else {
            // Kiểm tra trùng CCCD với người khác
            $checkCccd = $pdo->prepare('SELECT COUNT(*) FROM KhachThue WHERE CCCD = ? AND MaKhach <> ?');
            $checkCccd->execute([$formData['CCCD'], $id]);
            if ((int)$checkCccd->fetchColumn() > 0) {
                $errors['CCCD'] = 'Số CCCD này đã tồn tại trên hệ thống.';
            }
        }
    }

    // 4. Kiểm tra Email
    if ($formData['Email'] !== '' && !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
        $errors['Email'] = 'Địa chỉ Email không đúng định dạng.';
    }

    // Nếu không có lỗi -> Cập nhật CSDL
    if (empty($errors)) {
        try {
            $updateSql = 'UPDATE KhachThue 
                          SET HoTen = :hoTen,
                              CCCD = :cccd,
                              NgaySinh = :ngaySinh,
                              GioiTinh = :gioiTinh,
                              SoDienThoai = :soDienThoai,
                              Email = :email,
                              DiaChiThuongTru = :diaChi,
                              NgheNghiep = :ngheNghiep,
                              GhiChu = :ghiChu
                          WHERE MaKhach = :id';
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':hoTen' => $formData['HoTen'],
                ':cccd' => $formData['CCCD'],
                ':ngaySinh' => ($formData['NgaySinh'] !== '') ? $formData['NgaySinh'] : $tenant['NgaySinh'],
                ':gioiTinh' => $formData['GioiTinh'],
                ':soDienThoai' => $formData['SoDienThoai'],
                ':email' => ($formData['Email'] !== '') ? $formData['Email'] : null,
                ':diaChi' => ($formData['DiaChiThuongTru'] !== '') ? $formData['DiaChiThuongTru'] : null,
                ':ngheNghiep' => ($formData['NgheNghiep'] !== '') ? $formData['NgheNghiep'] : null,
                ':ghiChu' => ($formData['GhiChu'] !== '') ? $formData['GhiChu'] : null,
                ':id' => $id,
            ]);

            setFlash('success', 'Cập nhật khách thuê thành công');
            redirect($baseUrl . '/index.php');
        } catch (PDOException $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sửa Thông Tin Khách Thuê</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">
            ← Quay lại danh sách
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon">✕</span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>✏️ Chỉnh Sửa Thông Tin Hồ Sơ #<?= e((string)$tenant['MaKhach']) ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                <!-- Mã khách (Không được sửa) -->
                <div class="form-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 0.35rem; color: var(--text-muted);">
                        Mã khách thuê (Khóa chính)
                    </label>
                    <input type="text" 
                           class="form-control" 
                           value="#<?= e((string)$tenant['MaKhach']) ?>" 
                           disabled 
                           style="background-color: #f1f5f9; cursor: not-allowed;">
                </div>

                <!-- Họ tên -->
                <div class="form-group">
                    <label for="HoTen" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Họ và tên <span class="required" style="color: var(--danger-color);">*</span>
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

                <!-- Số điện thoại -->
                <div class="form-group">
                    <label for="SoDienThoai" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Số điện thoại <span class="required" style="color: var(--danger-color);">*</span>
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
                <div class="form-group">
                    <label for="Email" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Email
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

                <!-- CCCD -->
                <div class="form-group">
                    <label for="CCCD" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Số CCCD / CMND
                    </label>
                    <input type="text" 
                           id="CCCD" 
                           name="CCCD" 
                           class="form-control" 
                           value="<?= e($formData['CCCD']) ?>">
                    <?php if (isset($errors['CCCD'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['CCCD']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Ngày sinh -->
                <div class="form-group">
                    <label for="NgaySinh" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ngày sinh
                    </label>
                    <input type="date" 
                           id="NgaySinh" 
                           name="NgaySinh" 
                           class="form-control" 
                           value="<?= e($formData['NgaySinh']) ?>">
                </div>

                <!-- Giới tính -->
                <div class="form-group">
                    <label for="GioiTinh" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Giới tính
                    </label>
                    <select id="GioiTinh" name="GioiTinh" class="form-control">
                        <option value="Nam" <?= ($formData['GioiTinh'] === 'Nam') ? 'selected' : '' ?>>Nam</option>
                        <option value="Nữ" <?= ($formData['GioiTinh'] === 'Nữ') ? 'selected' : '' ?>>Nữ</option>
                        <option value="Khác" <?= ($formData['GioiTinh'] === 'Khác') ? 'selected' : '' ?>>Khác</option>
                    </select>
                </div>

                <!-- Nghề nghiệp -->
                <div class="form-group">
                    <label for="NgheNghiep" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Nghề nghiệp
                    </label>
                    <input type="text" 
                           id="NgheNghiep" 
                           name="NgheNghiep" 
                           class="form-control" 
                           value="<?= e($formData['NgheNghiep']) ?>">
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

                <!-- Ghi chú -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="GhiChu" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ghi chú
                    </label>
                    <textarea id="GhiChu" 
                              name="GhiChu" 
                              class="form-control" 
                              rows="3"><?= e($formData['GhiChu']) ?></textarea>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Hủy</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-weight: 600;">
                    Lưu Thay Đổi
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
