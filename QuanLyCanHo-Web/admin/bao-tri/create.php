<?php

declare(strict_types=1);

$title = 'Bảo Trì Phòng';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/bao-tri' : '/user/bao-tri');

// Lấy danh sách Địa chỉ Tòa nhà độc bản
$diaChiStmt = $pdo->query('SELECT DISTINCT DiaChi FROM CanHo WHERE DiaChi IS NOT NULL AND DiaChi <> "" ORDER BY DiaChi ASC');
$diaChiList = $diaChiStmt->fetchAll(PDO::FETCH_COLUMN);

// Lấy danh sách tất cả Căn hộ
$apartmentsStmt = $pdo->query('SELECT MaCanHo, SoPhong, DiaChi, TrangThai FROM CanHo ORDER BY DiaChi ASC, SoPhong ASC');
$apartments = $apartmentsStmt->fetchAll();

// Lấy danh sách tất cả Khách thuê
$tenantsSql = 'SELECT kt.MaKhach, kt.HoTen, kt.SoDienThoai, ch.SoPhong
               FROM KhachThue kt
               LEFT JOIN HopDong hp ON kt.MaKhach = hp.MaKhach AND hp.TrangThai = "Đang hiệu lực"
               LEFT JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
               ORDER BY kt.HoTen ASC';
$tenants = $pdo->query($tenantsSql)->fetchAll();

$errors = [];
$formData = [
    'DiaChi' => '',
    'MaCanHo' => '',
    'MaKhach' => '',
    'NoiDung' => '',
    'GhiChu' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['DiaChi'] = trim($_POST['DiaChi'] ?? '');
    $formData['MaCanHo'] = (int)($_POST['MaCanHo'] ?? 0);
    $formData['MaKhach'] = (int)($_POST['MaKhach'] ?? 0);
    $formData['NoiDung'] = trim($_POST['NoiDung'] ?? '');
    $formData['GhiChu'] = trim($_POST['GhiChu'] ?? '');

    if ($formData['MaCanHo'] <= 0) {
        $errors['MaCanHo'] = 'Vui lòng chọn mã phòng / căn hộ cần bảo trì.';
    }

    if ($formData['MaKhach'] <= 0) {
        $errors['MaKhach'] = 'Vui lòng chọn khách thuê gửi yêu cầu bảo trì.';
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

            setFlash('success', 'Gửi yêu cầu bảo trì phòng thành công!');
            redirect($baseUrl . '/index.php');
        } catch (PDOException $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Bảo Trì Phòng</h1>
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

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3>Thông Tin Yêu Cầu Bảo Trì</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                
                <!-- Chọn Địa chỉ Tòa nhà -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="DiaChi" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        📍 Chọn Địa Chỉ Tòa Nhà
                    </label>
                    <select id="DiaChi" name="DiaChi" class="form-control" onchange="filterRooms(this.value)">
                        <option value="">-- Tất cả tòa nhà --</option>
                        <?php foreach ($diaChiList as $dc): ?>
                            <option value="<?= e($dc) ?>" <?= ($formData['DiaChi'] === $dc) ? 'selected' : '' ?>>
                                <?= e($dc) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Chọn Mã phòng / Căn hộ -->
                <div class="form-group">
                    <label for="MaCanHo" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        🏢 Mã Phòng / Căn Hộ <span class="required" style="color: var(--danger-color);">*</span>
                    </label>
                    <select id="MaCanHo" name="MaCanHo" class="form-control" required>
                        <option value="">-- Chọn mã phòng --</option>
                        <?php foreach ($apartments as $ap): ?>
                            <option value="<?= $ap['MaCanHo'] ?>" 
                                    data-building="<?= e($ap['DiaChi'] ?? 'Tòa nhà A') ?>"
                                    <?= ($formData['MaCanHo'] == $ap['MaCanHo']) ? 'selected' : '' ?>>
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
                        👤 Chọn Khách Thuê <span class="required" style="color: var(--danger-color);">*</span>
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

                <!-- Nội dung yêu cầu bảo trì -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="NoiDung" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        🛠️ Nội dung yêu cầu bảo trì / Sự cố <span class="required" style="color: var(--danger-color);">*</span>
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
                        📝 Ghi chú bổ sung
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
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">🛠️ Gửi Yêu Cầu Bảo Trì</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterRooms(buildingAddress) {
    const roomSelect = document.getElementById('MaCanHo');
    const options = roomSelect.options;

    for (let i = 1; i < options.length; i++) {
        const opt = options[i];
        const optBuilding = opt.getAttribute('data-building');
        if (!buildingAddress || optBuilding === buildingAddress) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    }
    roomSelect.selectedIndex = 0;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
