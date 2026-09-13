<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/module2_helpers.php';
requirePermission('CANHO_MANAGE');

$isAdmin = (currentUserRole() === 'Admin');
$mode = trim((string)($_GET['mode'] ?? ''));
if (!$isAdmin) {
    // Nhân viên chỉ được phép Thêm Mã Phòng cho các tòa nhà được phân công
    $mode = 'room';
}
$isRoomMode = ($mode === 'room');
$title = $isRoomMode ? 'Thêm mã phòng' : 'Thêm căn hộ mới';
$types = $pdo->query("SELECT MaLoai, TenLoai, GiaThueChuan FROM LoaiCanHo ORDER BY FIELD(TenLoai, 'Studio', 'Duplex', '1 Phòng Ngủ', '2 Phòng Ngủ')")->fetchAll();

$staffAssigned = getStaffAssignedBuildings();
if ($staffAssigned !== null) {
    $buildings = $staffAssigned;
} else {
    $buildings = $pdo->query("SELECT DISTINCT DiaChi FROM CanHo WHERE DiaChi IS NOT NULL AND DiaChi <> '' ORDER BY DiaChi")->fetchAll(PDO::FETCH_COLUMN);
}

// Lấy thông tin biểu phí của từng tòa nhà có sẵn để tự động điền khi chọn tòa nhà
$buildingFees = [];
if (!empty($buildings)) {
    $bldStmt = $pdo->query("SELECT DiaChi, GiaDien, GiaNuoc, GiaXeMay, GiaOto, GiaInternet, GiaVeSinh FROM CanHo ORDER BY MaCanHo DESC");
    $allBldRows = $bldStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allBldRows as $row) {
        $addr = trim((string)$row['DiaChi']);
        if ($addr !== '' && !isset($buildingFees[$addr])) {
            $buildingFees[$addr] = [
                'GiaDien' => number_format((float)normalizeServiceFee($row['GiaDien'] ?? 3800, 'dien'), 0, '', '.'),
                'GiaNuoc' => number_format((float)normalizeServiceFee($row['GiaNuoc'] ?? 100000, 'nuoc'), 0, '', '.'),
                'GiaXeMay' => number_format((float)normalizeServiceFee($row['GiaXeMay'] ?? 120000, 'xemay'), 0, '', '.'),
                'GiaOto' => number_format((float)normalizeServiceFee($row['GiaOto'] ?? 1200000, 'oto'), 0, '', '.'),
                'GiaInternet' => number_format((float)normalizeServiceFee($row['GiaInternet'] ?? 100000, 'internet'), 0, '', '.'),
                'GiaVeSinh' => number_format((float)normalizeServiceFee($row['GiaVeSinh'] ?? 50000, 'vesinh'), 0, '', '.')
            ];
        }
    }
}

$presetDiaChi = trim((string)($_GET['DiaChi'] ?? ''));

$errors = [];
$data = [
    'SoPhong' => '',
    'DiaChi' => $presetDiaChi,
    'MaLoai' => '',
    'DienTich' => '',
    'GiaThue' => '',
    'TrangThai' => 'Trống',
    'MoTa' => '',
    'GiaDien' => '3.800',
    'GiaNuoc' => '100.000',
    'GiaXeMay' => '120.000',
    'GiaOto' => '1.200.000',
    'GiaInternet' => '100.000',
    'GiaVeSinh' => '50.000'
];

if ($presetDiaChi !== '' && isset($buildingFees[$presetDiaChi])) {
    foreach ($buildingFees[$presetDiaChi] as $fKey => $fVal) {
        $data[$fKey] = $fVal;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    module2_require_csrf();
    foreach (['SoPhong', 'DiaChi', 'DienTich', 'MoTa'] as $key) {
        $data[$key] = trim((string)($_POST[$key] ?? ''));
    }
    
    // Parse money inputs formatted with dots
    $rawGiaThue = str_replace('.', '', trim((string)($_POST['GiaThue'] ?? '')));
    $data['GiaThue'] = $rawGiaThue;
    $data['GiaDien'] = normalizeServiceFee(str_replace('.', '', trim((string)($_POST['GiaDien'] ?? '3800'))), 'dien');
    $data['GiaNuoc'] = normalizeServiceFee(str_replace('.', '', trim((string)($_POST['GiaNuoc'] ?? '100000'))), 'nuoc');
    $data['GiaXeMay'] = normalizeServiceFee(str_replace('.', '', trim((string)($_POST['GiaXeMay'] ?? '120000'))), 'xemay');
    $data['GiaOto'] = normalizeServiceFee(str_replace('.', '', trim((string)($_POST['GiaOto'] ?? '1200000'))), 'oto');
    $data['GiaInternet'] = normalizeServiceFee(str_replace('.', '', trim((string)($_POST['GiaInternet'] ?? '100000'))), 'internet');
    $data['GiaVeSinh'] = normalizeServiceFee(str_replace('.', '', trim((string)($_POST['GiaVeSinh'] ?? '50000'))), 'vesinh');

    $data['MaLoai'] = (int)($_POST['MaLoai'] ?? 0);
    $data['TrangThai'] = (string)($_POST['TrangThai'] ?? '');

    if ($data['SoPhong'] === '' || mb_strlen($data['SoPhong']) > 20) {
        $errors['SoPhong'] = 'Số phòng bắt buộc, tối đa 20 ký tự.';
    }
    if ($data['DiaChi'] === '') {
        $errors['DiaChi'] = $isRoomMode ? 'Vui lòng chọn địa chỉ.' : 'Tòa nhà / Địa chỉ bắt buộc.';
    } elseif ($isRoomMode && !in_array($data['DiaChi'], $buildings, true)) {
        $errors['DiaChi'] = 'Vui lòng chọn địa chỉ từ các căn có sẵn trên hệ thống.';
    } elseif (!isStaffAssignedBuilding($data['DiaChi'])) {
        $errors['DiaChi'] = 'Bạn không có quyền thêm phòng vào tòa nhà này.';
    }
    if ($data['MaLoai'] <= 0) {
        $errors['MaLoai'] = 'Vui lòng chọn loại căn.';
    }
    if ($data['DienTich'] === '' || !is_numeric($data['DienTich']) || (float)$data['DienTich'] <= 0) {
        $errors['DienTich'] = 'Diện tích phải > 0.';
    }
    if ($data['GiaThue'] === '' || !is_numeric($data['GiaThue']) || (float)$data['GiaThue'] < 0) {
        $errors['GiaThue'] = 'Giá thuê phải >= 0.';
    }
    if (!in_array($data['TrangThai'], ['Trống', 'Đang thuê', 'Bảo trì'], true)) {
        $errors['TrangThai'] = 'Trạng thái không hợp lệ.';
    }
    if (mb_strlen($data['MoTa']) > 255) {
        $errors['MoTa'] = 'Mô tả tối đa 255 ký tự.';
    }

    $hasFiles = isset($_FILES['images']['name']) && is_array($_FILES['images']['name']) && count(array_filter($_FILES['images']['name'])) > 0;
    if (!$hasFiles) {
        $errors['images'] = 'Căn hộ phải có ít nhất 1 ảnh đại diện.';
    }

    // Kiểm tra trùng số phòng trong cùng tòa nhà
    $s = $pdo->prepare('SELECT COUNT(*) FROM CanHo WHERE SoPhong = :sp AND DiaChi = :dc');
    $s->execute([':sp' => $data['SoPhong'], ':dc' => $data['DiaChi']]);
    if ((int)$s->fetchColumn() > 0) {
        $errors['SoPhong'] = 'Số phòng ' . $data['SoPhong'] . ' đã tồn tại trong tòa nhà này.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare('INSERT INTO CanHo (
                SoPhong, MaLoai, DienTich, GiaThue, TrangThai, MoTa, DiaChi,
                GiaDien, GiaNuoc, GiaXeMay, GiaOto, GiaInternet, GiaVeSinh
            ) VALUES (
                :sp, :loai, :dt, :gia, :tt, :mota, :diachi,
                :gd, :gn, :gxm, :goto, :gnet, :gvs
            )');
            $s->execute([
                ':sp' => $data['SoPhong'],
                ':loai' => $data['MaLoai'],
                ':dt' => (float)$data['DienTich'],
                ':gia' => (float)$data['GiaThue'],
                ':tt' => $data['TrangThai'],
                ':mota' => $data['MoTa'] ?: null,
                ':diachi' => $data['DiaChi'],
                ':gd' => $data['GiaDien'],
                ':gn' => $data['GiaNuoc'],
                ':gxm' => $data['GiaXeMay'],
                ':goto' => $data['GiaOto'],
                ':gnet' => $data['GiaInternet'],
                ':gvs' => $data['GiaVeSinh']
            ]);
            $id = (int)$pdo->lastInsertId();
            module2_upload_images($_FILES['images'], $id, $pdo, dirname(__DIR__, 2) . '/uploads/can-ho', 'uploads/can-ho');
            module2_repair_cover($pdo, $id);
            $pdo->commit();

            setFlash('success', ($isRoomMode ? 'Thêm mã phòng ' : 'Thêm căn hộ ') . e($data['SoPhong']) . ' thành công.');
            redirect('/admin/can-ho/detail.php?id=' . $id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $errors['general'] = 'Không thể thêm căn hộ. Vui lòng kiểm tra lại thông tin và ảnh tải lên: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $isRoomMode ? 'Thêm Mã Phòng' : 'Thêm căn hộ' ?></h1>
    </div>
    <a class="btn btn-outline" href="index.php">
        &larr; <span>Quay lại danh sách</span>
    </a>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger mb-3">
        <?= svgIcon('alert-triangle', '', 18) ?>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header" style="background: #f8fafc;">
        <h3 style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
            <?= svgIcon('door', '', 18) ?>
            <span><?= $isRoomMode ? 'Hồ Sơ Mã Phòng' : 'Hồ Sơ Căn Hộ Mới' ?></span>
        </h3>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(module2_csrf_token()) ?>">

            <!-- NHÓM 1: THÔNG TIN CƠ BẢN CĂN HỘ -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                        Số phòng <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="SoPhong" class="form-control" maxlength="20" value="<?= e($data['SoPhong']) ?>" placeholder="Nhập số phòng..." required autofocus>
                    <?php if (isset($errors['SoPhong'])): ?>
                        <small style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= e($errors['SoPhong']) ?></small>
                    <?php endif; ?>
                </div>

                <?php if ($isRoomMode): ?>
                    <!-- TRANG THÊM MÃ PHÒNG: ĐỊA CHỈ LẤY TỪ CÁC CĂN CÓ SẴN TRÊN HỆ THỐNG -->
                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                            Địa chỉ <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="DiaChi" id="roomBuildingSelect" class="form-control" required style="font-weight: 600;">
                            <option value="">-- Chọn địa chỉ căn hộ / tòa nhà có sẵn --</option>
                            <?php foreach ($buildings as $bld): ?>
                                <option value="<?= e($bld) ?>" <?= (trim($data['DiaChi']) === trim($bld)) ? 'selected' : '' ?>>
                                    <?= e($bld) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['DiaChi'])): ?>
                            <small style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= e($errors['DiaChi']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- TRANG THÊM CĂN HỘ MỚI: GIỮ NGUYÊN GỐC TÒA NHÀ / ĐỊA CHỈ AUTOCOMPLETE -->
                    <div class="form-group" style="position: relative;">
                        <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                            Tòa nhà / Địa chỉ <span style="color: #ef4444;">*</span>
                        </label>
                        <div style="position: relative;">
                            <input type="text" 
                                   id="addressInput" 
                                   name="DiaChi" 
                                   class="form-control" 
                                   value="<?= e($data['DiaChi']) ?>" 
                                   placeholder="Gõ địa chỉ hoặc tên tòa nhà..." 
                                   autocomplete="off" 
                                   required>
                            <div id="addressSuggestions" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 1000; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-height: 280px; overflow-y: auto;"></div>
                        </div>
                        <?php if (isset($errors['DiaChi'])): ?>
                            <small style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= e($errors['DiaChi']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                        Loại căn <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="MaLoai" id="selectLoaiCan" class="form-control" required>
                        <option value="">-- Chọn --</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['MaLoai'] ?>" 
                                    data-price="<?= (float)$t['GiaThueChuan'] ?>" 
                                    <?= $data['MaLoai'] == (int)$t['MaLoai'] ? 'selected' : '' ?>>
                                <?= e($t['TenLoai']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaLoai'])): ?>
                        <small style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= e($errors['MaLoai']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                        Trạng thái căn hộ <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="TrangThai" class="form-control" required>
                        <?php foreach (['Trống', 'Đang thuê', 'Bảo trì'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $data['TrangThai'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                        Diện tích (m²) <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="number" name="DienTich" class="form-control" min="0.01" step="0.01" value="<?= e($data['DienTich']) ?>" placeholder="Ví dụ: 35.5" required>
                    <?php if (isset($errors['DienTich'])): ?>
                        <small style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= e($errors['DienTich']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                        Giá thuê niêm yết (VNĐ) <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="giaThueInput" name="GiaThue" class="form-control currency-mask" value="<?= is_numeric($data['GiaThue']) && (float)$data['GiaThue'] > 0 ? number_format((float)$data['GiaThue'], 0, '', '.') : e($data['GiaThue']) ?>" placeholder="Ví dụ: 7.000.000" required>
                    <?php if (isset($errors['GiaThue'])): ?>
                        <small style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= e($errors['GiaThue']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NHÓM 2: BIỂU PHÍ DỊCH VỤ & TIỆN ÍCH TÒA NHÀ -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.25rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                    <span style="font-size: 0.95rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                        <?= svgIcon('cog', '', 18) ?> Biểu Phí Dịch Vụ & Tiện Ích Tòa Nhà
                    </span>
                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">(Đơn giá áp dụng riêng theo từng tòa nhà / căn hộ)</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 0.8rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                            Đơn giá điện (VNĐ/kWh) <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" id="fee_GiaDien" name="GiaDien" class="form-control currency-mask" value="<?= is_numeric(str_replace('.', '', (string)($data['GiaDien'] ?? '3800'))) ? number_format((float)normalizeServiceFee($data['GiaDien'] ?? 3800, 'dien'), 0, '', '.') : e($data['GiaDien']) ?>" placeholder="3.800" required>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 0.8rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                            Tiền nước (VNĐ/tháng) <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" id="fee_GiaNuoc" name="GiaNuoc" class="form-control currency-mask" value="<?= is_numeric(str_replace('.', '', (string)($data['GiaNuoc'] ?? '100000'))) ? number_format((float)normalizeServiceFee($data['GiaNuoc'] ?? 100000, 'nuoc'), 0, '', '.') : e($data['GiaNuoc']) ?>" placeholder="100.000" required>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 0.8rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                            Phí gửi xe máy (VNĐ/xe/tháng)
                        </label>
                        <input type="text" id="fee_GiaXeMay" name="GiaXeMay" class="form-control currency-mask" value="<?= is_numeric(str_replace('.', '', (string)($data['GiaXeMay'] ?? '120000'))) ? number_format((float)normalizeServiceFee($data['GiaXeMay'] ?? 120000, 'xemay'), 0, '', '.') : e($data['GiaXeMay']) ?>" placeholder="120.000">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 0.8rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                            Phí gửi ô tô (VNĐ/xe/tháng)
                        </label>
                        <input type="text" id="fee_GiaOto" name="GiaOto" class="form-control currency-mask" value="<?= is_numeric(str_replace('.', '', (string)($data['GiaOto'] ?? '1200000'))) ? number_format((float)normalizeServiceFee($data['GiaOto'] ?? 1200000, 'oto'), 0, '', '.') : e($data['GiaOto']) ?>" placeholder="1.200.000">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 0.8rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                            Internet / Wifi (VNĐ/tháng)
                        </label>
                        <input type="text" id="fee_GiaInternet" name="GiaInternet" class="form-control currency-mask" value="<?= is_numeric(str_replace('.', '', (string)($data['GiaInternet'] ?? '100000'))) ? number_format((float)normalizeServiceFee($data['GiaInternet'] ?? 100000, 'internet'), 0, '', '.') : e($data['GiaInternet']) ?>" placeholder="100.000">
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 0.8rem; color: #334155; margin-bottom: 0.35rem; display: block;">
                            Phí vệ sinh & rác (VNĐ/tháng)
                        </label>
                        <input type="text" id="fee_GiaVeSinh" name="GiaVeSinh" class="form-control currency-mask" value="<?= is_numeric(str_replace('.', '', (string)($data['GiaVeSinh'] ?? '50000'))) ? number_format((float)normalizeServiceFee($data['GiaVeSinh'] ?? 50000, 'vesinh'), 0, '', '.') : e($data['GiaVeSinh']) ?>" placeholder="50.000">
                    </div>
                </div>
            </div>

            <!-- NHÓM 3: MÔ TẢ & HÌNH ẢNH -->
            <div class="form-group mb-3">
                <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                    Mô tả tiện ích / Ghi chú
                </label>
                <textarea name="MoTa" class="form-control" maxlength="255" rows="3" placeholder="Nhập các tiện ích đi kèm: giường nệm, tủ lạnh, bếp, máy giặt, ban công..."><?= e($data['MoTa']) ?></textarea>
            </div>

            <div class="form-group mb-4">
                <label style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; display: block;">
                    Hình ảnh căn hộ <span style="color: #ef4444;">*</span>
                </label>
                <input type="file" name="images[]" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required>
                <small style="color: #64748b; font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                    Chọn một hoặc nhiều hình ảnh thực tế của căn hộ (JPG, PNG, WEBP tối đa 5MB/ảnh).
                </small>
                <?php if (isset($errors['images'])): ?>
                    <small style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= e($errors['images']) ?></small>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem;">
                <a class="btn btn-outline" href="index.php">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary" style="font-weight: 700; padding: 0.65rem 1.5rem;">
                    <?= svgIcon('check', '', 16) ?> <span><?= $isRoomMode ? 'Lưu mã phòng' : 'Lưu căn hộ' ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Biểu phí theo từng tòa nhà có sẵn để tự động điền
const buildingFeesMap = <?= json_encode($buildingFees, JSON_UNESCAPED_UNICODE) ?> || {};

function applyBuildingFees(addr) {
    if (!addr) return;
    const fees = buildingFeesMap[addr];
    if (fees) {
        if (document.getElementById('fee_GiaDien') && fees.GiaDien) document.getElementById('fee_GiaDien').value = fees.GiaDien;
        if (document.getElementById('fee_GiaNuoc') && fees.GiaNuoc) document.getElementById('fee_GiaNuoc').value = fees.GiaNuoc;
        if (document.getElementById('fee_GiaXeMay') && fees.GiaXeMay) document.getElementById('fee_GiaXeMay').value = fees.GiaXeMay;
        if (document.getElementById('fee_GiaOto') && fees.GiaOto) document.getElementById('fee_GiaOto').value = fees.GiaOto;
        if (document.getElementById('fee_GiaInternet') && fees.GiaInternet) document.getElementById('fee_GiaInternet').value = fees.GiaInternet;
        if (document.getElementById('fee_GiaVeSinh') && fees.GiaVeSinh) document.getElementById('fee_GiaVeSinh').value = fees.GiaVeSinh;
    }
}

// Khi chọn địa chỉ ở trang Thêm Mã Phòng -> Tự động cập nhật biểu phí
const roomBldSelect = document.getElementById('roomBuildingSelect');
if (roomBldSelect) {
    roomBldSelect.addEventListener('change', function() {
        applyBuildingFees(this.value);
    });
}

// Live Currency Formatter with dots (e.g. 7.000.000)
document.querySelectorAll('.currency-mask').forEach(input => {
    input.addEventListener('input', function() {
        const raw = this.value.replace(/\D/g, '');
        this.value = raw ? Number(raw).toLocaleString('vi-VN') : '';
    });
});

// Auto fill GiaThue based on LoaiCanHo standard price if empty
document.getElementById('selectLoaiCan')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const price = selected.getAttribute('data-price');
    const priceInput = document.getElementById('giaThueInput');
    if (price && (!priceInput.value || priceInput.value === '0')) {
        priceInput.value = Number(price).toLocaleString('vi-VN');
    }
});

// Google Maps Style Address Autocomplete (Restricted strictly to Vietnam & DB buildings)
(function() {
    const input = document.getElementById('addressInput');
    const box = document.getElementById('addressSuggestions');
    if (!input || !box) return;

    const dbBuildings = <?= json_encode($buildings, JSON_UNESCAPED_UNICODE) ?> || [];
    const curatedAddresses = [
        "113/4/99 Võ Duy Ninh, Phường 22, Quận Bình Thạnh, TP.HCM",
        "Tòa Orchard Garden - 128 Hồng Hà, Phường 9, Quận Phú Nhuận, TP.HCM",
        "Vinhomes Central Park - 208 Nguyễn Hữu Cảnh, Phường 22, Quận Bình Thạnh, TP.HCM",
        "Landmark 81 - 720A Điện Biên Phủ, Phường 22, Quận Bình Thạnh, TP.HCM",
        "Saigon Pearl - 92 Nguyễn Hữu Cảnh, Phường 22, Quận Bình Thạnh, TP.HCM",
        "The Manor - 91 Nguyễn Hữu Cảnh, Phường 22, Quận Bình Thạnh, TP.HCM",
        "Masteri Thảo Điền - 159 Xa Lộ Hà Nội, Thảo Điền, TP. Thủ Đức, TP.HCM",
        "The Sun Avenue - 28 Mai Chí Thọ, An Phú, TP. Thủ Đức, TP.HCM",
        "Sunrise City - 23 Nguyễn Hữu Thọ, Phường Tân Hưng, Quận 7, TP.HCM",
        "Botanica Premier - 108 Hồng Hà, Phường 2, Quận Tân Bình, TP.HCM",
        "D'Edge Thảo Điền - 44 Nguyễn Văn Hưởng, Thảo Điền, TP. Thủ Đức, TP.HCM",
        "Toà nhà The Nassim - 30 Đường 11, Thảo Điền, TP. Thủ Đức, TP.HCM",
        "Vinhomes Golden River - 2 Tôn Đức Thắng, Phường Bến Nghé, Quận 1, TP.HCM",
        "Căn hộ 235/30 Lê Văn Sỹ, Phường Nhiêu Lộc, Quận 3, TP.HCM",
        "Toà nhà 36 Lê Văn Sỹ, Phường 11, Quận Phú Nhuận, TP.HCM"
    ];

    const allPresets = Array.from(new Set([...dbBuildings, ...curatedAddresses]));
    let debounceTimer;

    function renderSuggestions(query, list) {
        const trimmed = query.trim();
        let html = '';

        // Option 1: Direct exact text option
        if (trimmed) {
            html += `
                <div class="address-item" data-val="${escapeAttr(trimmed)}" style="padding: 10px 14px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; border-bottom: 1px solid #e2e8f0; background: #eff6ff;">
                    <div style="color: #2563eb; flex-shrink: 0; margin-top: 2px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; font-weight: 700; color: #1d4ed8;">Sử dụng địa chỉ vừa nhập: "${escapeHtml(trimmed)}"</div>
                        <div style="font-size: 0.75rem; color: #3b82f6;">Nhấn để chọn chính xác nội dung bạn đã gõ</div>
                    </div>
                </div>
            `;
        }

        list.forEach(item => {
            html += `
                <div class="address-item" data-val="${escapeAttr(item)}" style="padding: 10px 14px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                    <div style="color: #ef4444; flex-shrink: 0; margin-top: 2px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; font-weight: 600; color: #0f172a; line-height: 1.4;">${escapeHtml(item)}</div>
                        <div style="font-size: 0.75rem; color: #64748b;">Định vị Google Maps &bull; Việt Nam</div>
                    </div>
                </div>
            `;
        });

        box.innerHTML = html;
        box.style.display = 'block';

        // Bind mousedown to prevent premature blur before selection
        box.querySelectorAll('.address-item').forEach(el => {
            el.addEventListener('mousedown', function(e) {
                e.preventDefault();
                const chosen = this.getAttribute('data-val') || this.innerText;
                input.value = chosen;
                box.style.display = 'none';
                if (typeof applyBuildingFees === 'function') {
                    applyBuildingFees(chosen);
                }
                if (bldSelector) {
                    let matched = false;
                    for (let opt of bldSelector.options) {
                        if (opt.value && opt.value.trim() === chosen.trim()) {
                            bldSelector.value = opt.value;
                            matched = true;
                            break;
                        }
                    }
                    if (!matched) {
                        bldSelector.value = '__custom__';
                    }
                }
            });
        });
    }

    async function searchAddresses(query) {
        const qLower = query.toLowerCase();
        let matches = allPresets.filter(a => a.toLowerCase().includes(qLower));

        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&countrycodes=vn&addressdetails=1&limit=6&q=' + encodeURIComponent(query);
            const res = await fetch(url, { headers: { 'Accept-Language': 'vi' } });
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data)) {
                    data.forEach(item => {
                        const a = item.address || {};
                        const parts = [];
                        if (a.house_number) parts.push(a.house_number);
                        if (a.road) parts.push(a.road);
                        if (a.suburb) parts.push(a.suburb);
                        else if (a.neighbourhood) parts.push(a.neighbourhood);
                        if (a.city_district) parts.push(a.city_district);
                        if (a.city) parts.push(a.city);
                        else if (a.state) parts.push(a.state);
                        
                        let cleanName = parts.length > 1 ? parts.join(', ') : item.display_name.split(',').slice(0, 3).join(', ');
                        if (cleanName && !matches.includes(cleanName)) {
                            matches.push(cleanName);
                        }
                    });
                }
            }
        } catch (err) {}

        renderSuggestions(query, matches.slice(0, 7));
    }

    input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (!q) {
            box.style.display = 'none';
            return;
        }
        debounceTimer = setTimeout(() => {
            searchAddresses(q);
        }, 220);
    });

    input.addEventListener('focus', function() {
        const q = this.value.trim();
        if (q) {
            searchAddresses(q);
        }
    });

    document.addEventListener('mousedown', function(e) {
        if (!input.contains(e.target) && !box.contains(e.target)) {
            box.style.display = 'none';
        }
    });

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/"/g, '&quot;');
    }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
