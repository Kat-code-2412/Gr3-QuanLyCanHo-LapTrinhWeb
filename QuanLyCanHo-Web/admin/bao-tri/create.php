<?php

declare(strict_types=1);

$title = 'Báo Cáo Sự Cố & Bảo Trì Phòng';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url((currentUserRole() === 'Admin') ? '/admin/bao-tri' : '/user/bao-tri');

// Lấy danh sách Tòa nhà & Căn hộ theo phân quyền
$staffAssigned = getStaffAssignedBuildings();
if ($staffAssigned !== null) {
    if (empty($staffAssigned)) {
        $diaChiList = [];
        $apartments = [];
        $tenants = [];
    } else {
        $diaChiList = $staffAssigned;
        $inPh = implode(',', array_fill(0, count($staffAssigned), '?'));
        $apartmentsStmt = $pdo->prepare("
            SELECT c.MaCanHo, c.SoPhong, c.DiaChi, c.TrangThai,
                   (SELECT hp.MaKhach FROM HopDong hp WHERE hp.MaCanHo = c.MaCanHo AND hp.TrangThai = 'Đang hiệu lực' ORDER BY hp.MaHopDong DESC LIMIT 1) AS CurrentMaKhach
            FROM CanHo c 
            WHERE c.DiaChi IN ($inPh)
            ORDER BY c.DiaChi ASC, c.SoPhong ASC
        ");
        $apartmentsStmt->execute($staffAssigned);
        $apartments = $apartmentsStmt->fetchAll();

        $tenantsStmt = $pdo->prepare("
            SELECT kt.MaKhach, kt.HoTen, kt.SoDienThoai, ch.SoPhong, ch.MaCanHo
            FROM KhachThue kt
            JOIN HopDong hp ON kt.MaKhach = hp.MaKhach AND hp.TrangThai = 'Đang hiệu lực'
            JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
            WHERE ch.DiaChi IN ($inPh)
            ORDER BY kt.HoTen ASC
        ");
        $tenantsStmt->execute($staffAssigned);
        $tenants = $tenantsStmt->fetchAll();
    }
} else {
    $diaChiStmt = $pdo->query('SELECT DISTINCT DiaChi FROM CanHo WHERE DiaChi IS NOT NULL AND DiaChi <> "" ORDER BY DiaChi ASC');
    $diaChiList = $diaChiStmt->fetchAll(PDO::FETCH_COLUMN);

    $apartmentsStmt = $pdo->query('
        SELECT c.MaCanHo, c.SoPhong, c.DiaChi, c.TrangThai,
               (SELECT hp.MaKhach FROM HopDong hp WHERE hp.MaCanHo = c.MaCanHo AND hp.TrangThai = "Đang hiệu lực" ORDER BY hp.MaHopDong DESC LIMIT 1) AS CurrentMaKhach
        FROM CanHo c 
        ORDER BY c.DiaChi ASC, c.SoPhong ASC
    ');
    $apartments = $apartmentsStmt->fetchAll();

    $tenantsSql = 'SELECT kt.MaKhach, kt.HoTen, kt.SoDienThoai, ch.SoPhong, ch.MaCanHo
                   FROM KhachThue kt
                   LEFT JOIN HopDong hp ON kt.MaKhach = hp.MaKhach AND hp.TrangThai = "Đang hiệu lực"
                   LEFT JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
                   ORDER BY kt.HoTen ASC';
    $tenants = $pdo->query($tenantsSql)->fetchAll();
}

$errors = [];
$formData = [
    'DiaChi' => '',
    'MaCanHo' => '',
    'MaKhach' => '',
    'LoaiSuCo' => 'Điện',
    'MucDoUuTien' => 'Trung bình',
    'NguoiChiuChiPhi' => 'Chủ nhà',
    'ChiPhi' => '0',
    'NoiDung' => '',
    'GhiChu' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $formData['DiaChi'] = trim($_POST['DiaChi'] ?? '');
    $formData['MaCanHo'] = (int)($_POST['MaCanHo'] ?? 0);
    $formData['MaKhach'] = (int)($_POST['MaKhach'] ?? 0);
    $formData['LoaiSuCo'] = trim($_POST['LoaiSuCo'] ?? 'Khác');
    $formData['MucDoUuTien'] = trim($_POST['MucDoUuTien'] ?? 'Trung bình');
    $formData['NguoiChiuChiPhi'] = trim($_POST['NguoiChiuChiPhi'] ?? 'Chủ nhà');
    $formData['ChiPhi'] = trim($_POST['ChiPhi'] ?? '0');
    $formData['NoiDung'] = trim($_POST['NoiDung'] ?? '');
    $formData['GhiChu'] = trim($_POST['GhiChu'] ?? '');

    if ($formData['MaCanHo'] <= 0) {
        $errors['MaCanHo'] = 'Vui lòng chọn phòng / căn hộ cần bảo trì.';
    } else {
        $chkApt = $pdo->prepare('SELECT DiaChi FROM CanHo WHERE MaCanHo = ?');
        $chkApt->execute([$formData['MaCanHo']]);
        $aptDiaChi = (string)($chkApt->fetchColumn() ?: '');
        if ($aptDiaChi === '' || !isStaffAssignedBuilding($aptDiaChi)) {
            $errors['MaCanHo'] = 'Bạn không có quyền báo cáo sự cố cho phòng thuộc tòa nhà này.';
        }
    }

    if ($formData['NoiDung'] === '') {
        $errors['NoiDung'] = 'Nội dung sự cố không được để trống.';
    }

    $rawChiPhi = trim((string)($_POST['ChiPhi'] ?? '0'));
    $formData['ChiPhi'] = $rawChiPhi;
    $cleanChiPhi = str_replace(['.', ',', ' '], '', $rawChiPhi);
    $chiPhiVal = (float)$cleanChiPhi;
    if ($chiPhiVal > 0 && $chiPhiVal < 1000) {
        $chiPhiVal *= 1000;
    }
    if ($chiPhiVal < 0) {
        $errors['ChiPhi'] = 'Chi phí dự kiến không được âm.';
    }

    if (empty($errors)) {
        try {
            $sql = 'INSERT INTO YeuCauBaoTri (MaCanHo, MaKhach, LoaiSuCo, MucDoUuTien, NguoiChiuChiPhi, ChiPhi, NoiDung, NgayTiepNhan, TrangThai, GhiChu)
                    VALUES (:maCanHo, :maKhach, :loaiSuCo, :mucDoUuTien, :nguoiChiuCP, :chiPhi, :noiDung, NOW(), "Đã tiếp nhận", :ghiChu)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':maCanHo'       => $formData['MaCanHo'],
                ':maKhach'       => ($formData['MaKhach'] > 0) ? $formData['MaKhach'] : null,
                ':loaiSuCo'      => $formData['LoaiSuCo'],
                ':mucDoUuTien'   => $formData['MucDoUuTien'],
                ':nguoiChiuCP'   => $formData['NguoiChiuChiPhi'],
                ':chiPhi'        => $chiPhiVal,
                ':noiDung'       => $formData['NoiDung'],
                ':ghiChu'        => ($formData['GhiChu'] !== '') ? $formData['GhiChu'] : null,
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Lấy thông tin phòng để ghi audit log
            $rStmt = $pdo->prepare('SELECT SoPhong FROM CanHo WHERE MaCanHo = ?');
            $rStmt->execute([$formData['MaCanHo']]);
            $soPhong = $rStmt->fetchColumn() ?: '';

            logAudit('CREATE', 'YeuCauBaoTri', (string)$newId, "Tạo sự cố bảo trì #{$newId} phòng {$soPhong} - Mức độ: {$formData['MucDoUuTien']} - Loại: {$formData['LoaiSuCo']}");
            addNotification(null, "Yêu cầu bảo trì mới phòng {$soPhong}", "Sự cố: " . mb_substr($formData['NoiDung'], 0, 120), 'BaoTri', "/admin/bao-tri/detail.php?id={$newId}");

            setFlash('success', "Gửi yêu cầu bảo trì #{$newId} thành công!");
            redirect($baseUrl . '/index.php');
        } catch (PDOException $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Báo Cáo Sự Cố & Bảo Trì</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">
            ← Quay lại danh sách
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 860px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3 style="margin:0;">Thông Tin Chi Tiết Yêu Cầu Bảo Trì</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                
                <!-- Chọn Địa chỉ nhà -->
                <div class="form-group">
                    <label for="chonDiaChiNha" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Địa chỉ nhà
                    </label>
                    <select id="chonDiaChiNha" name="DiaChi" class="form-control" data-no-autocomplete="true" onchange="filterRoomsByAddress(this.value)">
                        <option value="">-- Tất cả địa chỉ --</option>
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
                        Phòng <span class="required" style="color: var(--danger-color);">*</span>
                    </label>
                    <select id="MaCanHo" name="MaCanHo" class="form-control" required onchange="onRoomSelected(this)">
                        <option value="">-- Chọn phòng --</option>
                        <?php foreach ($apartments as $ap): ?>
                            <option value="<?= $ap['MaCanHo'] ?>" 
                                    data-building="<?= e($ap['DiaChi'] ?? '') ?>"
                                    data-tenant-id="<?= e((string)($ap['CurrentMaKhach'] ?? '')) ?>"
                                    <?= ($formData['MaCanHo'] == $ap['MaCanHo']) ? 'selected' : '' ?>>
                                Phòng <?= e(formatSoPhong($ap['SoPhong'])) ?> (<?= e($ap['TrangThai']) ?>)
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
                        Khách Thuê Báo Cáo
                    </label>
                    <select id="MaKhach" name="MaKhach" class="form-control">
                        <option value="">-- Chọn khách thuê (không bắt buộc) --</option>
                        <?php foreach ($tenants as $tn): ?>
                            <?php $roomLabel = $tn['SoPhong'] ? ' [Phòng ' . formatSoPhong($tn['SoPhong']) . ']' : ''; ?>
                            <option value="<?= $tn['MaKhach'] ?>" 
                                    data-room-id="<?= e((string)($tn['MaCanHo'] ?? '')) ?>"
                                    <?= ($formData['MaKhach'] == $tn['MaKhach']) ? 'selected' : '' ?>>
                                <?= e($tn['HoTen']) ?> - SĐT: <?= e($tn['SoDienThoai']) ?><?= e($roomLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaKhach'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['MaKhach']) ?></small>
                    <?php endif; ?>
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

                <!-- Chi phí dự kiến -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="ChiPhi" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Chi Phí Dự Kiến (VNĐ)
                    </label>
                    <input type="text" 
                           id="ChiPhi" 
                           name="ChiPhi" 
                           class="form-control currency-mask" 
                           value="<?= (isset($formData['ChiPhi']) && $formData['ChiPhi'] !== '') ? (is_numeric(str_replace('.', '', (string)$formData['ChiPhi'])) ? number_format((float)str_replace('.', '', (string)$formData['ChiPhi']), 0, '', '.') : e($formData['ChiPhi'])) : '0' ?>"
                           placeholder="Ví dụ: 100.000">
                    <?php if (isset($errors['ChiPhi'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['ChiPhi']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Nội dung yêu cầu bảo trì -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="NoiDung" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Mô Tả Chi Tiết Sự Cố <span class="required" style="color: var(--danger-color);">*</span>
                    </label>
                    <textarea id="NoiDung" 
                              name="NoiDung" 
                              class="form-control" 
                              rows="3" 
                              required><?= e($formData['NoiDung']) ?></textarea>
                    <?php if (isset($errors['NoiDung'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500;"><?= e($errors['NoiDung']) ?></small>
                    <?php endif; ?>
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
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;"><?= svgIcon('tool', '', 16) ?> Gửi Yêu Cầu Bảo Trì</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterRoomsByAddress(address) {
    const roomSelect = document.getElementById('MaCanHo');
    const options = roomSelect.options;

    for (let i = 1; i < options.length; i++) {
        const opt = options[i];
        const optBuilding = opt.getAttribute('data-building');
        if (!address || optBuilding === address) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    }

    // Nếu phòng đang chọn không thuộc địa chỉ này, reset lại phòng
    if (roomSelect.selectedIndex > 0 && options[roomSelect.selectedIndex].style.display === 'none') {
        roomSelect.selectedIndex = 0;
        document.getElementById('MaKhach').selectedIndex = 0;
    }
}

function onRoomSelected(selectEl) {
    const roomId = selectEl.value;
    const tenantSelect = document.getElementById('MaKhach');
    if (!roomId) {
        if (tenantSelect) tenantSelect.value = '';
        return;
    }

    // Tự động đồng bộ Địa chỉ nhà
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const building = selectedOpt ? selectedOpt.getAttribute('data-building') : '';
    const tenantId = selectedOpt ? selectedOpt.getAttribute('data-tenant-id') : '';
    const addressSelect = document.getElementById('chonDiaChiNha');
    if (building && addressSelect && (!addressSelect.value || addressSelect.value !== building)) {
        addressSelect.value = building;
    }

    // Tự động chọn Khách thuê (nếu phòng đang có khách thì chọn khách đó, phòng trống thì để trống)
    if (tenantSelect) {
        tenantSelect.value = tenantId || '';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
