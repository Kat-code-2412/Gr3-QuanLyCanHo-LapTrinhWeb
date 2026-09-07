<?php

declare(strict_types=1);

$title = 'Tạo Yêu cầu Bảo trì';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = (currentUserRole() === 'Admin') ? '/admin/bao-tri' : '/user/bao-tri';

// Lấy danh sách tất cả căn hộ
$apartmentsStmt = $pdo->query('SELECT MaCanHo, SoPhong, TrangThai FROM CanHo ORDER BY SoPhong ASC');
$apartments = $apartmentsStmt->fetchAll();

// Lấy danh sách khách thuê kèm thông tin phòng đang thuê (nếu có)
$tenantsSql = 'SELECT kt.MaKhach, kt.HoTen, kt.SoDienThoai, ch.MaCanHo, ch.SoPhong
               FROM KhachThue kt
               LEFT JOIN HopDong hp ON kt.MaKhach = hp.MaKhach AND hp.TrangThai = "Đang hiệu lực"
               LEFT JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
               ORDER BY kt.HoTen ASC';
$tenantsStmt = $pdo->query($tenantsSql);
$tenants = $tenantsStmt->fetchAll();

$errors = [];
$formData = [
    'MaCanHo' => '',
    'MaKhach' => '',
    'NoiDung' => '',
    'GhiChu' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['MaCanHo'] = (int)($_POST['MaCanHo'] ?? 0);
    $formData['MaKhach'] = (int)($_POST['MaKhach'] ?? 0);
    $formData['NoiDung'] = trim($_POST['NoiDung'] ?? '');
    $formData['GhiChu'] = trim($_POST['GhiChu'] ?? '');

    // Server-side validation
    if ($formData['MaCanHo'] <= 0) {
        $errors['MaCanHo'] = 'Vui lòng chọn căn hộ cần bảo trì.';
    }

    if ($formData['MaKhach'] <= 0) {
        $errors['MaKhach'] = 'Vui lòng chọn khách thuê gửi yêu cầu.';
    }

    if ($formData['NoiDung'] === '') {
        $errors['NoiDung'] = 'Nội dung yêu cầu bảo trì không được để trống.';
    }

    if (empty($errors)) {
        try {
            $sql = 'INSERT INTO YeuCauBaoTri (MaCanHo, MaKhach, NoiDung, NgayTiepNhan, TrangThai, GhiChu)
                    VALUES (:maCanHo, :maKhach, :noiDung, NOW(), "Đã tiếp nhận", :ghiChu)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':maCanHo' => $formData['MaCanHo'],
                ':maKhach' => $formData['MaKhach'],
                ':noiDung' => $formData['NoiDung'],
                ':ghiChu'  => ($formData['GhiChu'] !== '') ? $formData['GhiChu'] : null,
            ]);

            setFlash('success', 'Tạo mới yêu cầu bảo trì thành công!');
            redirect($baseUrl . '/index.php');
        } catch (PDOException $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tạo Yêu Cầu Bảo Trì Mới</h1>
        <p class="page-subtitle">Ghi nhận thông tin bảo trì / sửa chữa sự cố căn hộ</p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">
            ← Quay lại danh sách
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
        <h3>Thông Tin Yêu Cầu Bảo Trì</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                
                <!-- Chọn Căn hộ -->
                <div class="form-group">
                    <label for="MaCanHo" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Chọn Căn Hộ / Số Phòng <span class="required">*</span>
                    </label>
                    <select id="MaCanHo" name="MaCanHo" class="form-control" required>
                        <option value="">-- Chọn phòng --</option>
                        <?php foreach ($apartments as $ap): ?>
                            <option value="<?= $ap['MaCanHo'] ?>" <?= ($formData['MaCanHo'] == $ap['MaCanHo']) ? 'selected' : '' ?>>
                                Phòng <?= e($ap['SoPhong']) ?> (<?= e($ap['TrangThai']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaCanHo'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['MaCanHo']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Chọn Khách thuê -->
                <div class="form-group">
                    <label for="MaKhach" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Chọn Khách Thuê <span class="required">*</span>
                    </label>
                    <select id="MaKhach" name="MaKhach" class="form-control" required>
                        <option value="">-- Chọn khách thuê --</option>
                        <?php foreach ($tenants as $tn): ?>
                            <?php $roomLabel = $tn['SoPhong'] ? ' [Phòng ' . $tn['SoPhong'] . ']' : ''; ?>
                            <option value="<?= $tn['MaKhach'] ?>" <?= ($formData['MaKhach'] == $tn['MaKhach']) ? 'selected' : '' ?>>
                                <?= e($tn['HoTen']) ?> - SĐT: <?= e($tn['SoDienThoai']) ?><?= e($roomLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaKhach'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['MaKhach']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Nội dung yêu cầu -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="NoiDung" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Nội dung yêu cầu / Sự cố <span class="required">*</span>
                    </label>
                    <textarea id="NoiDung" 
                              name="NoiDung" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Mô tả chi tiết sự cố (VD: Điều hòa không lạnh, vòi nước rò rỉ, bóng đèn hỏng...)" 
                              required><?= e($formData['NoiDung']) ?></textarea>
                    <?php if (isset($errors['NoiDung'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['NoiDung']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Ghi chú -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="GhiChu" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Ghi chú bổ sung
                    </label>
                    <input type="text" 
                           id="GhiChu" 
                           name="GhiChu" 
                           class="form-control" 
                           placeholder="Ghi chú thêm nếu có (VD: Khách dặn gọi trước khi đến...)" 
                           value="<?= e($formData['GhiChu']) ?>">
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary">🛠️ Gửi Yêu Cầu Bảo Trì</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
