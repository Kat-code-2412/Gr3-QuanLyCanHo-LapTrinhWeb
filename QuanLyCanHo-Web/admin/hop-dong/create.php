<?php

declare(strict_types=1);

$title = 'Tạo Hợp Đồng Thuê Mới';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$role = currentUserRole();
$baseUrl = url(($role === 'Admin') ? '/admin/hop-dong' : '/user/hop-dong');
$currentStaffId = $_SESSION['MaNV'] ?? null;

// Lấy danh sách các Căn hộ và Tòa nhà theo phân quyền
$staffAssigned = getStaffAssignedBuildings();
if ($staffAssigned !== null) {
    if (empty($staffAssigned)) {
        $canHoList = [];
        $diaChiList = [];
    } else {
        $inPh = implode(',', array_fill(0, count($staffAssigned), '?'));
        $canHoStmt = $pdo->prepare("SELECT c.*, l.TenLoai FROM CanHo c JOIN LoaiCanHo l ON c.MaLoai = l.MaLoai WHERE c.DiaChi IN ($inPh) ORDER BY c.DiaChi ASC, c.SoPhong ASC");
        $canHoStmt->execute($staffAssigned);
        $canHoList = $canHoStmt->fetchAll();
        $diaChiList = $staffAssigned;
    }
} else {
    $canHoStmt = $pdo->query('SELECT c.*, l.TenLoai FROM CanHo c JOIN LoaiCanHo l ON c.MaLoai = l.MaLoai ORDER BY c.DiaChi ASC, c.SoPhong ASC');
    $canHoList = $canHoStmt->fetchAll();
    $diaChiList = $pdo->query('SELECT DISTINCT DiaChi FROM CanHo WHERE DiaChi IS NOT NULL AND DiaChi <> "" ORDER BY DiaChi ASC')->fetchAll(PDO::FETCH_COLUMN);
}

// Lấy danh sách khách thuê đã có trên hệ thống
$existingTenants = $pdo->query('SELECT MaKhach, HoTen, SoDienThoai, CCCD FROM KhachThue ORDER BY HoTen ASC')->fetchAll();

$errors = [];
$tenantMode = trim($_POST['tenant_mode'] ?? 'new'); // 'existing' hoặc 'new'
$selectedExistingKhach = (int)($_POST['existing_ma_khach'] ?? 0);

$formData = [
    // Thông tin Khách thuê
    'HoTen' => '',
    'SoDienThoai' => '',
    'Email' => '',
    'CCCD' => '',
    'NgaySinh' => '',
    'GioiTinh' => 'Nam',
    'DiaChiThuongTru' => '',
    'NgheNghiep' => '',
    'GhiChuKhach' => '',

    // Căn hộ
    'MaCanHo' => '',

    // Hợp đồng
    'NgayKy' => date('Y-m-d'),
    'NgayBatDau' => date('Y-m-d'),
    'ThoiHanThang' => 12,
    'NgayKetThuc' => date('Y-m-d', strtotime('+12 months')),
    'GiaThueThoaThuan' => '',
    'TienCoc' => '',
    'SoNguoiOi' => 1,
    'SoXeMay' => 1,
    'SoOto' => 0,
    'GhiChuHopDong' => '',

    // Điện / Nước / Dịch vụ
    'GiaDien' => 3800,
    'GiaNuoc' => 20000,
    'GiaXeMay' => 150000,
    'GiaOto' => 1200000,
    'GiaInternet' => 200000,
    'GiaVeSinh' => 100000,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect Form Data
    $tenantMode = trim($_POST['tenant_mode'] ?? 'new');
    $selectedExistingKhach = (int)($_POST['existing_ma_khach'] ?? 0);

    $formData['HoTen'] = trim($_POST['HoTen'] ?? '');
    $formData['SoDienThoai'] = trim($_POST['SoDienThoai'] ?? '');
    $formData['Email'] = trim($_POST['Email'] ?? '');
    $formData['CCCD'] = trim($_POST['CCCD'] ?? '');
    $formData['NgaySinh'] = trim($_POST['NgaySinh'] ?? '');
    $formData['GioiTinh'] = trim($_POST['GioiTinh'] ?? 'Nam');
    $formData['DiaChiThuongTru'] = trim($_POST['DiaChiThuongTru'] ?? '');
    $formData['NgheNghiep'] = trim($_POST['NgheNghiep'] ?? '');
    $formData['GhiChuKhach'] = trim($_POST['GhiChuKhach'] ?? '');

    $formData['MaCanHo'] = (int)($_POST['MaCanHo'] ?? 0);

    $formData['NgayKy'] = trim($_POST['NgayKy'] ?? date('Y-m-d'));
    $formData['NgayBatDau'] = trim($_POST['NgayBatDau'] ?? date('Y-m-d'));
    $formData['ThoiHanThang'] = max(1, (int)($_POST['ThoiHanThang'] ?? 12));
    $formData['NgayKetThuc'] = trim($_POST['NgayKetThuc'] ?? date('Y-m-d', strtotime("+{$formData['ThoiHanThang']} months")));
    $formData['GiaThueThoaThuan'] = (float)str_replace('.', '', (string)($_POST['GiaThueThoaThuan'] ?? 0));
    $formData['TienCoc'] = (float)str_replace('.', '', (string)($_POST['TienCoc'] ?? 0));
    $formData['SoNguoiOi'] = max(1, (int)($_POST['SoNguoiOi'] ?? 1));
    $formData['SoXeMay'] = max(0, (int)($_POST['SoXeMay'] ?? 0));
    $formData['SoOto'] = max(0, (int)($_POST['SoOto'] ?? 0));
    $formData['GhiChuHopDong'] = trim($_POST['GhiChuHopDong'] ?? '');

    $formData['GiaDien'] = normalizeServiceFee(str_replace('.', '', (string)($_POST['GiaDien'] ?? 3800)), 'dien');
    $formData['GiaNuoc'] = normalizeServiceFee(str_replace('.', '', (string)($_POST['GiaNuoc'] ?? 100000)), 'nuoc');
    $formData['GiaXeMay'] = normalizeServiceFee(str_replace('.', '', (string)($_POST['GiaXeMay'] ?? 120000)), 'xemay');
    $formData['GiaOto'] = normalizeServiceFee(str_replace('.', '', (string)($_POST['GiaOto'] ?? 1200000)), 'oto');
    $formData['GiaInternet'] = normalizeServiceFee(str_replace('.', '', (string)($_POST['GiaInternet'] ?? 100000)), 'internet');
    $formData['GiaVeSinh'] = normalizeServiceFee(str_replace('.', '', (string)($_POST['GiaVeSinh'] ?? 50000)), 'vesinh');

    // Validations
    if ($tenantMode === 'existing') {
        if ($selectedExistingKhach <= 0) {
            $errors['existing_ma_khach'] = 'Vui lòng chọn khách thuê từ danh sách.';
        }
    } else {
        if ($formData['HoTen'] === '') {
            $errors['HoTen'] = 'Họ và tên khách thuê không được để trống.';
        }

        if ($formData['SoDienThoai'] === '') {
            $errors['SoDienThoai'] = 'Số điện thoại không được để trống.';
        } elseif (!preg_match('/^[0-9]{9,11}$/', $formData['SoDienThoai'])) {
            $errors['SoDienThoai'] = 'Số điện thoại phải chứa từ 9 đến 11 chữ số.';
        } else {
            $checkPhone = $pdo->prepare('SELECT COUNT(*) FROM KhachThue WHERE SoDienThoai = ?');
            $checkPhone->execute([$formData['SoDienThoai']]);
            if ((int)$checkPhone->fetchColumn() > 0) {
                $errors['SoDienThoai'] = 'Số điện thoại này đã tồn tại trên hệ thống (hãy chọn Khách thuê đã có).';
            }
        }

        if ($formData['CCCD'] !== '') {
            if (!preg_match('/^[0-9]{9,12}$/', $formData['CCCD'])) {
                $errors['CCCD'] = 'Số CCCD/CMND phải chứa từ 9 đến 12 chữ số.';
            } else {
                $checkCccd = $pdo->prepare('SELECT COUNT(*) FROM KhachThue WHERE CCCD = ?');
                $checkCccd->execute([$formData['CCCD']]);
                if ((int)$checkCccd->fetchColumn() > 0) {
                    $errors['CCCD'] = 'Số CCCD này đã tồn tại trên hệ thống.';
                }
            }
        }

        if ($formData['Email'] !== '' && !filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors['Email'] = 'Địa chỉ Email không đúng định dạng.';
        }
    }

    if ($formData['MaCanHo'] <= 0) {
        $errors['MaCanHo'] = 'Vui lòng chọn căn hộ / phòng thuê.';
    } else {
        $checkRoom = $pdo->prepare("SELECT SoPhong, TrangThai, DiaChi FROM CanHo WHERE MaCanHo = ?");
        $checkRoom->execute([$formData['MaCanHo']]);
        $roomRow = $checkRoom->fetch();
        if (!$roomRow) {
            $errors['MaCanHo'] = 'Phòng không tồn tại.';
        } elseif (!isStaffAssignedBuilding((string)($roomRow['DiaChi'] ?? ''))) {
            $errors['MaCanHo'] = 'Bạn không có quyền tạo hợp đồng cho căn hộ thuộc tòa nhà này.';
        } elseif ($roomRow['TrangThai'] === 'Đang thuê') {
            $errors['MaCanHo'] = 'Phòng này đang có hợp đồng hiệu lực.';
        }
    }

    if ($formData['GiaThueThoaThuan'] <= 0) {
        $errors['GiaThueThoaThuan'] = 'Vui lòng nhập giá thuê thỏa thuận hợp lệ.';
    }

    // NẾU KHÔNG CÓ LỖI -> TRANSACTION
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Xác định MaKhach
            if ($tenantMode === 'existing') {
                $maKhach = $selectedExistingKhach;
                $stmtGetKhach = $pdo->prepare("SELECT HoTen FROM KhachThue WHERE MaKhach = ?");
                $stmtGetKhach->execute([$maKhach]);
                $tenKhach = $stmtGetKhach->fetchColumn() ?: 'Khách hàng';
            } else {
                $insertKhachSql = 'INSERT INTO KhachThue (HoTen, CCCD, NgaySinh, GioiTinh, SoDienThoai, Email, DiaChiThuongTru, NgheNghiep, GhiChu)
                                   VALUES (:hoTen, :cccd, :ngaySinh, :gioiTinh, :soDienThoai, :email, :diaChi, :ngheNghiep, :ghiChu)';
                $stmtKhach = $pdo->prepare($insertKhachSql);
                $stmtKhach->execute([
                    ':hoTen' => $formData['HoTen'],
                    ':cccd' => ($formData['CCCD'] !== '') ? $formData['CCCD'] : null,
                    ':ngaySinh' => ($formData['NgaySinh'] !== '') ? $formData['NgaySinh'] : null,
                    ':gioiTinh' => $formData['GioiTinh'],
                    ':soDienThoai' => $formData['SoDienThoai'],
                    ':email' => ($formData['Email'] !== '') ? $formData['Email'] : null,
                    ':diaChi' => ($formData['DiaChiThuongTru'] !== '') ? $formData['DiaChiThuongTru'] : null,
                    ':ngheNghiep' => ($formData['NgheNghiep'] !== '') ? $formData['NgheNghiep'] : null,
                    ':ghiChu' => ($formData['GhiChuKhach'] !== '') ? $formData['GhiChuKhach'] : null,
                ]);
                $maKhach = (int)$pdo->lastInsertId();
                $tenKhach = $formData['HoTen'];
            }

            // 2. Upload Files nếu có
            $fileHopDong = function_exists('uploadFile') ? uploadFile($_FILES['file_hop_dong'] ?? [], 'contracts') : null;
            $fileCccdTruoc = null;
            $fileCccdSau = null;
            if (!empty($_FILES['file_cccd'])) {
                if (is_array($_FILES['file_cccd']['name'])) {
                    $uploaded = [];
                    $count = count($_FILES['file_cccd']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if (!empty($_FILES['file_cccd']['name'][$i]) && ($_FILES['file_cccd']['error'][$i] === UPLOAD_ERR_OK)) {
                            $singleFile = [
                                'name' => $_FILES['file_cccd']['name'][$i],
                                'type' => $_FILES['file_cccd']['type'][$i] ?? '',
                                'tmp_name' => $_FILES['file_cccd']['tmp_name'][$i],
                                'error' => $_FILES['file_cccd']['error'][$i],
                                'size' => $_FILES['file_cccd']['size'][$i],
                            ];
                            $path = uploadFile($singleFile, 'cccd');
                            if ($path) {
                                $uploaded[] = $path;
                            }
                        }
                    }
                    $fileCccdTruoc = $uploaded[0] ?? null;
                    $fileCccdSau = $uploaded[1] ?? null;
                } else {
                    $fileCccdTruoc = uploadFile($_FILES['file_cccd'], 'cccd');
                }
            }
            if (!$fileCccdTruoc && !empty($_FILES['file_cccd_truoc'])) {
                $fileCccdTruoc = uploadFile($_FILES['file_cccd_truoc'], 'cccd');
            }
            if (!$fileCccdSau && !empty($_FILES['file_cccd_sau'])) {
                $fileCccdSau = uploadFile($_FILES['file_cccd_sau'], 'cccd');
            }

            // 3. Insert HopDong
            $insertHdSql = 'INSERT INTO HopDong (
                MaCanHo, MaKhach, MaNV, NgayKy, NgayBatDau, NgayKetThuc, ThoiHanThang, 
                GiaThueThoaThuan, TienCoc, TrangThai, GhiChu, SoNguoiOi, SoXeMay, SoOto,
                FileHopDong, AnhCCCDMatTruoc, AnhCCCDMatSau,
                GiaDien, GiaNuoc, GiaXeMay, GiaOto, GiaInternet, GiaVeSinh
            ) VALUES (
                :maCanHo, :maKhach, :maNv, :ngayKy, :ngayBatDau, :ngayKetThuc, :thoiHan,
                :giaThue, :tienCoc, "Đang hiệu lực", :ghiChu, :soNguoi, :soXeMay, :soOto,
                :fileHd, :cccdTruoc, :cccdSau,
                :giaDien, :giaNuoc, :giaXeMay, :giaOto, :giaInternet, :giaVeSinh
            )';
            $stmtHd = $pdo->prepare($insertHdSql);
            $stmtHd->execute([
                ':maCanHo' => $formData['MaCanHo'],
                ':maKhach' => $maKhach,
                ':maNv' => $currentStaffId,
                ':ngayKy' => $formData['NgayKy'],
                ':ngayBatDau' => $formData['NgayBatDau'],
                ':ngayKetThuc' => $formData['NgayKetThuc'],
                ':thoiHan' => $formData['ThoiHanThang'],
                ':giaThue' => $formData['GiaThueThoaThuan'],
                ':tienCoc' => $formData['TienCoc'],
                ':ghiChu' => ($formData['GhiChuHopDong'] !== '') ? $formData['GhiChuHopDong'] : null,
                ':soNguoi' => $formData['SoNguoiOi'],
                ':soXeMay' => $formData['SoXeMay'],
                ':soOto' => $formData['SoOto'],
                ':fileHd' => $fileHopDong,
                ':cccdTruoc' => $fileCccdTruoc,
                ':cccdSau' => $fileCccdSau,
                ':giaDien' => $formData['GiaDien'],
                ':giaNuoc' => $formData['GiaNuoc'],
                ':giaXeMay' => $formData['GiaXeMay'],
                ':giaOto' => $formData['GiaOto'],
                ':giaInternet' => $formData['GiaInternet'],
                ':giaVeSinh' => $formData['GiaVeSinh'],
            ]);
            $newHdId = (int)$pdo->lastInsertId();

            // 4. Update CanHo -> Đang thuê
            $pdo->prepare("UPDATE CanHo SET TrangThai = 'Đang thuê' WHERE MaCanHo = ?")->execute([$formData['MaCanHo']]);

            // 5. Ghi Audit Log
            $soPhong = $roomRow['SoPhong'] ?? (string)$formData['MaCanHo'];
            logAudit('CREATE_CONTRACT', 'HopDong', (string)$newHdId, "Tạo hợp đồng thuê phòng {$soPhong} cho khách {$tenKhach}");

            $pdo->commit();

            setFlash('success', "Lập hợp đồng #{$newHdId} thành công cho khách {$tenKhach}. Phòng {$soPhong} đã chuyển sang trạng thái Đang thuê.");
            redirect($baseUrl . '/detail.php?id=' . $newHdId);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = 'Lỗi hệ thống khi tạo hợp đồng: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tạo Hợp Đồng Thuê Mới</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Quay lại danh sách</a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data" id="createForm">
    <!-- THÔNG TIN KHÁCH THUÊ -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>THÔNG TIN KHÁCH THUÊ</h3>
        </div>
        <div class="card-body">
            <!-- CHỌN CHẾ ĐỘ KHÁCH THUÊ -->
            <div style="display: flex; gap: 2rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0;">
                <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                    <input type="radio" name="tenant_mode" value="new" <?= ($tenantMode !== 'existing') ? 'checked' : '' ?> onchange="toggleTenantMode('new')">
                    Thêm hồ sơ khách thuê mới
                </label>
                <label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                    <input type="radio" name="tenant_mode" value="existing" <?= ($tenantMode === 'existing') ? 'checked' : '' ?> onchange="toggleTenantMode('existing')">
                    Chọn khách thuê đã có trên hệ thống
                </label>
            </div>

            <!-- CHỌN KHÁCH ĐÃ CÓ -->
            <div id="existingTenantBox" style="display: <?= ($tenantMode === 'existing') ? 'block' : 'none' ?>; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="existing_ma_khach" style="font-weight: 600;">Chọn hồ sơ khách thuê <span style="color: var(--danger-color);">*</span></label>
                    <select id="existing_ma_khach" name="existing_ma_khach" class="form-control" style="font-weight: 600;">
                        <option value="">-- Chọn khách thuê đã có --</option>
                        <?php foreach ($existingTenants as $ek): ?>
                            <option value="<?= $ek['MaKhach'] ?>" <?= ($selectedExistingKhach === (int)$ek['MaKhach']) ? 'selected' : '' ?>>
                                <?= e($ek['HoTen']) ?> - SĐT: <?= e($ek['SoDienThoai']) ?> <?= $ek['CCCD'] ? ('- CCCD: ' . e($ek['CCCD'])) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['existing_ma_khach'])): ?><small style="color: var(--danger-color);"><?= e($errors['existing_ma_khach']) ?></small><?php endif; ?>
                </div>
            </div>

            <!-- NHẬP KHÁCH MỚI -->
            <div id="newTenantBox" style="display: <?= ($tenantMode !== 'existing') ? 'grid' : 'none' ?>; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="HoTen" style="font-weight: 600;">Họ và tên khách thuê <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" id="HoTen" name="HoTen" class="form-control" placeholder="Nhập họ và tên đầy đủ..." value="<?= e($formData['HoTen']) ?>">
                    <?php if (isset($errors['HoTen'])): ?><small style="color: var(--danger-color);"><?= e($errors['HoTen']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="SoDienThoai" style="font-weight: 600;">Số điện thoại <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" id="SoDienThoai" name="SoDienThoai" class="form-control" placeholder="Ví dụ: 0912345678" value="<?= e($formData['SoDienThoai']) ?>">
                    <?php if (isset($errors['SoDienThoai'])): ?><small style="color: var(--danger-color);"><?= e($errors['SoDienThoai']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="Email" style="font-weight: 600;">Email</label>
                    <input type="email" id="Email" name="Email" class="form-control" placeholder="khachthue@gmail.com" value="<?= e($formData['Email']) ?>">
                    <?php if (isset($errors['Email'])): ?><small style="color: var(--danger-color);"><?= e($errors['Email']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="CCCD" style="font-weight: 600;">Số CCCD / CMND</label>
                    <input type="text" id="CCCD" name="CCCD" class="form-control" placeholder="12 số CCCD" value="<?= e($formData['CCCD']) ?>">
                    <?php if (isset($errors['CCCD'])): ?><small style="color: var(--danger-color);"><?= e($errors['CCCD']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="NgaySinh" style="font-weight: 600;">Ngày sinh</label>
                    <input type="date" id="NgaySinh" name="NgaySinh" class="form-control" value="<?= e($formData['NgaySinh']) ?>">
                </div>

                <div class="form-group">
                    <label for="GioiTinh" style="font-weight: 600;">Giới tính</label>
                    <select id="GioiTinh" name="GioiTinh" class="form-control">
                        <option value="Nam" <?= ($formData['GioiTinh'] === 'Nam') ? 'selected' : '' ?>>Nam</option>
                        <option value="Nữ" <?= ($formData['GioiTinh'] === 'Nữ') ? 'selected' : '' ?>>Nữ</option>
                        <option value="Khác" <?= ($formData['GioiTinh'] === 'Khác') ? 'selected' : '' ?>>Khác</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="NgheNghiep" style="font-weight: 600;">Nghề nghiệp</label>
                    <input type="text" id="NgheNghiep" name="NgheNghiep" class="form-control" placeholder="Ví dụ: Kỹ sư, Nhân viên văn phòng..." value="<?= e($formData['NgheNghiep']) ?>">
                </div>

                <div class="form-group" style="grid-column: 1 / -1; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <label for="DiaChiThuongTru" style="font-weight: 600; margin-bottom: 0;">Địa chỉ thường trú</label>
                        <span style="font-size: 0.75rem; color: #2563eb; display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            Gợi ý địa chỉ chuẩn Google Maps
                        </span>
                    </div>
                    <input type="text" id="DiaChiThuongTru" name="DiaChiThuongTru" class="form-control address-autocomplete" placeholder="Nhập số nhà, tên đường, phường/xã, quận/huyện để chọn..." value="<?= e($formData['DiaChiThuongTru']) ?>">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="GhiChuKhach" style="font-weight: 600;">Ghi chú thông tin khách</label>
                    <textarea id="GhiChuKhach" name="GhiChuKhach" class="form-control" rows="2" placeholder="Ghi chú thêm về khách thuê..."><?= e($formData['GhiChuKhach']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- THÔNG TIN CĂN HỘ / PHÒNG -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>THÔNG TIN CĂN HỘ / PHÒNG</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <!-- Chọn Địa chỉ Tòa nhà -->
                <div class="form-group">
                    <label for="selectBuilding" style="font-weight: 700;">Chọn Địa Chỉ Tòa Nhà</label>
                    <select id="selectBuilding" class="form-control" onchange="filterRoomsByBuilding(this.value)">
                        <option value="">-- Tất cả các tòa nhà --</option>
                        <?php foreach ($diaChiList as $dc): ?>
                            <option value="<?= e($dc) ?>"><?= e($dc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Chọn Mã phòng -->
                <div class="form-group">
                    <label for="MaCanHo" style="font-weight: 700;">Chọn Mã Phòng / Căn Hộ <span style="color: var(--danger-color);">*</span></label>
                    <select id="MaCanHo" name="MaCanHo" class="form-control" style="font-weight: 600;" required onchange="updateRoomInfo(this)">
                        <option value="">-- Chọn Mã Phòng --</option>
                        <?php foreach ($canHoList as $ch): ?>
                            <option value="<?= $ch['MaCanHo'] ?>" 
                                    data-building="<?= e($ch['DiaChi'] ?? '') ?>"
                                    data-so-phong="<?= e($ch['SoPhong'] ?? ('#' . $ch['MaCanHo'])) ?>"
                                    data-dia-chi="<?= e($ch['DiaChi'] ?? ('Phòng ' . $ch['SoPhong'])) ?>"
                                    data-gia="<?= (float)$ch['GiaThue'] ?>"
                                    data-trang-thai="<?= e($ch['TrangThai']) ?>"
                                    data-loai="<?= e($ch['TenLoai'] ?? 'Căn hộ') ?>"
                                    data-dien-tich="<?= number_format((float)$ch['DienTich'], 2) ?>"
                                    data-noi-that="<?= e($ch['MoTa'] ?? 'Đầy đủ tiện nghi') ?>"
                                    data-gia-dien="<?= (float)normalizeServiceFee($ch['GiaDien'] ?? 3800, 'dien') ?>"
                                    data-gia-nuoc="<?= (float)normalizeServiceFee($ch['GiaNuoc'] ?? 100000, 'nuoc') ?>"
                                    data-gia-xe-may="<?= (float)normalizeServiceFee($ch['GiaXeMay'] ?? 120000, 'xemay') ?>"
                                    data-gia-oto="<?= (float)normalizeServiceFee($ch['GiaOto'] ?? 1200000, 'oto') ?>"
                                    data-gia-internet="<?= (float)normalizeServiceFee($ch['GiaInternet'] ?? 100000, 'internet') ?>"
                                    data-gia-ve-sinh="<?= (float)normalizeServiceFee($ch['GiaVeSinh'] ?? 50000, 'vesinh') ?>"
                                    <?= ($formData['MaCanHo'] === (int)$ch['MaCanHo']) ? 'selected' : '' ?>>
                                Phòng <?= e($ch['SoPhong'] ?? ('#' . $ch['MaCanHo'])) ?> [<?= e($ch['TenLoai'] ?? 'Căn hộ') ?>] - <?= formatMoney($ch['GiaThue']) ?> - (<?= e($ch['TrangThai']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaCanHo'])): ?>
                        <small style="color: var(--danger-color); font-weight: 600; display: block; margin-top: 0.5rem;"><?= e($errors['MaCanHo']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- THẺ SHOWCASE THÔNG TIN CĂN HỘ CHI TIẾT -->
            <div id="roomInfoBox" class="room-showcase-box" style="display: none;">
                <div class="room-showcase-header">
                    <div class="room-showcase-title-wrap">
                        <div class="room-showcase-badge-icon">
                            <?= svgIcon('door', '', 18) ?>
                        </div>
                        <div>
                            <div class="room-showcase-title">Căn Hộ Được Chọn</div>
                            <div class="room-showcase-subtitle">Hồ sơ niêm yết và thông số diện tích, tiện nghi</div>
                        </div>
                    </div>
                    <span class="badge badge-success" style="font-size: 0.72rem; padding: 4px 12px; border-radius: 999px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                        <?= svgIcon('check-circle', '', 13) ?> Đã liên kết phòng
                    </span>
                </div>

                <div class="room-showcase-grid">
                    <!-- ĐỊA CHỈ TÒA NHÀ -->
                    <div class="room-spec-card is-address" style="grid-column: span 2;">
                        <div class="room-spec-header">
                            <span class="room-spec-icon" style="background: #e0e7ff; color: #4f46e5;"><?= svgIcon('map-pin', '', 13) ?></span>
                            <span>Địa chỉ tòa nhà</span>
                        </div>
                        <div class="room-spec-value" id="boxDiaChi" style="font-weight: 700; color: #1e293b; font-size: 0.92rem; line-height: 1.45;">-</div>
                    </div>

                    <!-- MÃ / SỐ PHÒNG -->
                    <div class="room-spec-card">
                        <div class="room-spec-header">
                            <span class="room-spec-icon" style="background: #eff6ff; color: #2563eb;"><?= svgIcon('door', '', 13) ?></span>
                            <span>Mã / Số phòng</span>
                        </div>
                        <div class="room-spec-value" id="boxSoPhong" style="font-size: 1.25rem; font-weight: 800; color: #2563eb; letter-spacing: -0.01em;">-</div>
                    </div>

                    <!-- LOẠI PHÒNG -->
                    <div class="room-spec-card">
                        <div class="room-spec-header">
                            <span class="room-spec-icon" style="background: #f5f3ff; color: #7c3aed;"><?= svgIcon('building', '', 13) ?></span>
                            <span>Loại phòng</span>
                        </div>
                        <div class="room-spec-value" id="boxLoaiPhong" style="font-weight: 700; color: #0f172a;">-</div>
                    </div>

                    <!-- DIỆN TÍCH -->
                    <div class="room-spec-card">
                        <div class="room-spec-header">
                            <span class="room-spec-icon" style="background: #fef3c7; color: #d97706;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
                            </span>
                            <span>Diện tích</span>
                        </div>
                        <div class="room-spec-value" id="boxDienTich" style="font-size: 1.15rem; font-weight: 800; color: #0f172a;">-</div>
                    </div>

                    <!-- GIÁ NIÊM YẾT -->
                    <div class="room-spec-card is-highlight">
                        <div class="room-spec-header">
                            <span class="room-spec-icon" style="background: #ecfdf5; color: #059669;"><?= svgIcon('payment', '', 13) ?></span>
                            <span>Giá niêm yết</span>
                        </div>
                        <div class="room-spec-value" id="boxGiaThue" style="font-size: 1.15rem; font-weight: 800; color: #059669;">-</div>
                    </div>

                    <!-- NỘI THẤT & TIỆN ÍCH -->
                    <div class="room-spec-card" style="grid-column: 1 / -1; background: #ffffff;">
                        <div class="room-spec-header">
                            <span class="room-spec-icon" style="background: #ecfeff; color: #0891b2;"><?= svgIcon('sparkles', '', 13) ?></span>
                            <span>Nội thất & tiện ích đi kèm</span>
                        </div>
                        <div class="room-spec-value" id="boxNoiThat" style="font-weight: 500; font-size: 0.92rem; color: #334155; line-height: 1.5; background: #f8fafc; padding: 0.6rem 0.85rem; border-radius: 8px; border: 1px dashed #cbd5e1;">-</div>
                    </div>
                </div>
            </div>

            <div id="roomWarning" class="alert alert-danger mt-3" style="display: none;">
                <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
                <div style="font-weight: 700;">Phòng này đang có hợp đồng hiệu lực. Vui lòng chọn phòng khác có trạng thái Trống!</div>
            </div>
        </div>
    </div>

    <!-- THÔNG TIN HỢP ĐỒNG -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>THÔNG TIN HỢP ĐỒNG</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="NgayKy" style="font-weight: 600;">Ngày ký hợp đồng</label>
                    <input type="date" id="NgayKy" name="NgayKy" class="form-control" value="<?= e($formData['NgayKy']) ?>">
                </div>

                <div class="form-group">
                    <label for="NgayBatDau" style="font-weight: 600;">Ngày bắt đầu <span style="color: var(--danger-color);">*</span></label>
                    <input type="date" id="NgayBatDau" name="NgayBatDau" class="form-control" value="<?= e($formData['NgayBatDau']) ?>" onchange="calcEndDate()" required>
                </div>

                <div class="form-group">
                    <label for="ThoiHanThang" style="font-weight: 600;">Thời hạn thuê</label>
                    <select id="ThoiHanThang" name="ThoiHanThang" class="form-control" onchange="calcEndDate()">
                        <option value="1" <?= ($formData['ThoiHanThang'] === 1) ? 'selected' : '' ?>>1 Tháng</option>
                        <option value="3" <?= ($formData['ThoiHanThang'] === 3) ? 'selected' : '' ?>>3 Tháng</option>
                        <option value="6" <?= ($formData['ThoiHanThang'] === 6) ? 'selected' : '' ?>>6 Tháng</option>
                        <option value="12" <?= ($formData['ThoiHanThang'] === 12) ? 'selected' : '' ?>>12 Tháng (1 Năm)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="NgayKetThuc" style="font-weight: 600;">Ngày kết thúc <span style="color: var(--danger-color);">*</span></label>
                    <input type="date" id="NgayKetThuc" name="NgayKetThuc" class="form-control" value="<?= e($formData['NgayKetThuc']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="GiaThueThoaThuan" style="font-weight: 600;">Giá thuê thỏa thuận (VNĐ/tháng) <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" id="GiaThueThoaThuan" name="GiaThueThoaThuan" class="form-control currency-mask" placeholder="Ví dụ: 9.600.000" value="<?= is_numeric($formData['GiaThueThoaThuan']) && (float)$formData['GiaThueThoaThuan'] > 0 ? number_format((float)$formData['GiaThueThoaThuan'], 0, '', '.') : e((string)$formData['GiaThueThoaThuan']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="TienCoc" style="font-weight: 600;">Tiền đặt cọc (VNĐ)</label>
                    <input type="text" id="TienCoc" name="TienCoc" class="form-control currency-mask" placeholder="Ví dụ: 19.200.000" value="<?= is_numeric($formData['TienCoc']) && (float)$formData['TienCoc'] > 0 ? number_format((float)$formData['TienCoc'], 0, '', '.') : e((string)$formData['TienCoc']) ?>">
                </div>

                <div class="form-group">
                    <label for="SoNguoiOi" style="font-weight: 600;">Số người ở</label>
                    <input type="number" id="SoNguoiOi" name="SoNguoiOi" class="form-control" min="1" value="<?= e((string)$formData['SoNguoiOi']) ?>">
                </div>

                <div class="form-group">
                    <label for="SoXeMay" style="font-weight: 600;">Số xe máy</label>
                    <input type="number" id="SoXeMay" name="SoXeMay" class="form-control" min="0" value="<?= e((string)$formData['SoXeMay']) ?>">
                </div>

                <div class="form-group">
                    <label for="SoOto" style="font-weight: 600;">Số ô tô</label>
                    <input type="number" id="SoOto" name="SoOto" class="form-control" min="0" value="<?= e((string)$formData['SoOto']) ?>">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="GhiChuHopDong" style="font-weight: 600;">Ghi chú điều khoản hợp đồng</label>
                    <textarea id="GhiChuHopDong" name="GhiChuHopDong" class="form-control" rows="2" placeholder="Ghi chú điều khoản thỏa thuận thêm..."><?= e($formData['GhiChuHopDong']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ĐƠN GIÁ ĐIỆN / NƯỚC / DỊCH VỤ -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>ĐƠN GIÁ ĐIỆN / NƯỚC / DỊCH VỤ</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="GiaDien" style="font-weight: 600;">Đơn giá điện (VNĐ/kWh)</label>
                    <input type="text" id="GiaDien" name="GiaDien" class="form-control currency-mask" value="<?= number_format((float)normalizeServiceFee($formData['GiaDien'] ?? 3800, 'dien'), 0, '', '.') ?>">
                </div>

                <div class="form-group">
                    <label for="GiaNuoc" style="font-weight: 600;">Đơn giá nước (VNĐ/tháng)</label>
                    <input type="text" id="GiaNuoc" name="GiaNuoc" class="form-control currency-mask" value="<?= number_format((float)normalizeServiceFee($formData['GiaNuoc'] ?? 100000, 'nuoc'), 0, '', '.') ?>">
                </div>

                <div class="form-group">
                    <label for="GiaXeMay" style="font-weight: 600;">Phí gửi xe máy (VNĐ/xe/tháng)</label>
                    <input type="text" id="GiaXeMay" name="GiaXeMay" class="form-control currency-mask" value="<?= number_format((float)normalizeServiceFee($formData['GiaXeMay'] ?? 120000, 'xemay'), 0, '', '.') ?>">
                </div>

                <div class="form-group">
                    <label for="GiaOto" style="font-weight: 600;">Phí gửi ô tô (VNĐ/xe/tháng)</label>
                    <input type="text" id="GiaOto" name="GiaOto" class="form-control currency-mask" value="<?= number_format((float)normalizeServiceFee($formData['GiaOto'] ?? 1200000, 'oto'), 0, '', '.') ?>">
                </div>

                <div class="form-group">
                    <label for="GiaInternet" style="font-weight: 600;">Phí Internet / Dịch vụ (VNĐ/tháng)</label>
                    <input type="text" id="GiaInternet" name="GiaInternet" class="form-control currency-mask" value="<?= number_format((float)normalizeServiceFee($formData['GiaInternet'] ?? 100000, 'internet'), 0, '', '.') ?>">
                </div>

                <div class="form-group">
                    <label for="GiaVeSinh" style="font-weight: 600;">Phí Vệ sinh / Rác (VNĐ/tháng)</label>
                    <input type="text" id="GiaVeSinh" name="GiaVeSinh" class="form-control currency-mask" value="<?= number_format((float)normalizeServiceFee($formData['GiaVeSinh'] ?? 50000, 'vesinh'), 0, '', '.') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- UPLOAD HÌNH ẢNH & FILE SCAN HỢP ĐỒNG -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>UPLOAD HÌNH ẢNH & FILE SCAN HỢP ĐỒNG</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                <!-- File Hợp đồng Scan -->
                <div class="form-group">
                    <label for="file_hop_dong" style="font-weight: 600;">File Hợp Đồng Scan (PDF, DOC, JPG...)</label>
                    <input type="file" id="file_hop_dong" name="file_hop_dong" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="previewFile(this, 'previewHd')">
                    <div id="previewHd" style="margin-top: 0.5rem; display: none; align-items: center; gap: 0.5rem;">
                        <span id="fileNameHd" style="font-weight: 600; color: var(--primary-color);"></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="clearFile('file_hop_dong', 'previewHd')">Xóa</button>
                    </div>
                </div>

                <!-- Ảnh CCCD / CMND (Gộp chung) -->
                <div class="form-group">
                    <label for="file_cccd" style="font-weight: 600;">Ảnh CCCD / CMND</label>
                    <input type="file" id="file_cccd" name="file_cccd[]" class="form-control" accept="image/*" multiple onchange="previewFile(this, 'previewCccd')">
                    <small class="text-muted" style="display: block; margin-top: 4px; font-size: 0.8rem;">
                        Có thể chọn 1 ảnh hoặc chọn cả 2 mặt trước và sau (JPG, PNG)
                    </small>
                    <div id="previewCccd" style="margin-top: 0.5rem; display: none; align-items: center; gap: 0.5rem;">
                        <span id="fileNameCccd" style="font-weight: 600; color: var(--primary-color);"></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="clearFile('file_cccd', 'previewCccd')">Xóa</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NÚT LƯU VÀ HỦY -->
    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 3rem;">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="padding: 0.75rem 2rem; font-weight: 600;">Hủy</a>
        <button type="submit" id="btnSubmit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-size: 1.05rem; font-weight: 700;">
            Ký & Tạo Hợp Đồng Thuê
        </button>
    </div>
</form>

<script>
function toggleTenantMode(mode) {
    const existingBox = document.getElementById('existingTenantBox');
    const newBox = document.getElementById('newTenantBox');
    const existingSelect = document.getElementById('existing_ma_khach');
    const hoTenInput = document.getElementById('HoTen');
    const sdtInput = document.getElementById('SoDienThoai');

    if (mode === 'existing') {
        existingBox.style.display = 'block';
        newBox.style.display = 'none';
        if (existingSelect) existingSelect.required = true;
        if (hoTenInput) hoTenInput.required = false;
        if (sdtInput) sdtInput.required = false;
    } else {
        existingBox.style.display = 'none';
        newBox.style.display = 'grid';
        if (existingSelect) existingSelect.required = false;
        if (hoTenInput) hoTenInput.required = true;
        if (sdtInput) sdtInput.required = true;
    }
}

function filterRoomsByBuilding(buildingAddress) {
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
    updateRoomInfo(roomSelect);
}

function updateRoomInfo(select) {
    const opt = select.options[select.selectedIndex];
    const infoBox = document.getElementById('roomInfoBox');
    const warningBox = document.getElementById('roomWarning');
    const btnSubmit = document.getElementById('btnSubmit');

    if (!select.value) {
        infoBox.style.display = 'none';
        warningBox.style.display = 'none';
        btnSubmit.disabled = false;
        return;
    }

    const soPhong = opt.getAttribute('data-so-phong');
    const diaChi = opt.getAttribute('data-dia-chi');
    const gia = parseFloat(opt.getAttribute('data-gia') || '0');
    const trangThai = opt.getAttribute('data-trang-thai');
    const loai = opt.getAttribute('data-loai');
    const dienTich = opt.getAttribute('data-dien-tich');
    const noiThat = opt.getAttribute('data-noi-that');

    document.getElementById('boxDiaChi').innerText = diaChi;
    document.getElementById('boxSoPhong').innerText = 'Phòng ' + soPhong;
    document.getElementById('boxLoaiPhong').innerText = loai;
    document.getElementById('boxDienTich').innerText = dienTich + ' m²';
    document.getElementById('boxGiaThue').innerText = gia.toLocaleString('vi-VN') + ' đ / tháng';
    document.getElementById('boxNoiThat').innerText = noiThat;

    infoBox.style.display = 'block';

    const inputGia = document.getElementById('GiaThueThoaThuan');
    if (!inputGia.value || inputGia.value == 0) {
        inputGia.value = Number(gia).toLocaleString('vi-VN');
        const inputCoc = document.getElementById('TienCoc');
        if (!inputCoc.value || inputCoc.value == 0) {
            inputCoc.value = Number(gia * 2).toLocaleString('vi-VN');
        }
    }

    // Tự động điền đơn giá dịch vụ & tiện ích của tòa nhà/căn hộ đó
    const giaDien = opt.getAttribute('data-gia-dien') || '3800';
    const giaNuoc = opt.getAttribute('data-gia-nuoc') || '100000';
    const giaXeMay = opt.getAttribute('data-gia-xe-may') || '120000';
    const giaOto = opt.getAttribute('data-gia-oto') || '1200000';
    const giaInternet = opt.getAttribute('data-gia-internet') || '100000';
    const giaVeSinh = opt.getAttribute('data-gia-ve-sinh') || '50000';

    document.getElementById('GiaDien').value = Number(giaDien).toLocaleString('vi-VN');
    document.getElementById('GiaNuoc').value = Number(giaNuoc).toLocaleString('vi-VN');
    document.getElementById('GiaXeMay').value = Number(giaXeMay).toLocaleString('vi-VN');
    document.getElementById('GiaOto').value = Number(giaOto).toLocaleString('vi-VN');
    document.getElementById('GiaInternet').value = Number(giaInternet).toLocaleString('vi-VN');
    document.getElementById('GiaVeSinh').value = Number(giaVeSinh).toLocaleString('vi-VN');

    if (trangThai === 'Đang thuê') {
        warningBox.style.display = 'block';
        btnSubmit.disabled = true;
    } else {
        warningBox.style.display = 'none';
        btnSubmit.disabled = false;
    }
}

function calcEndDate() {
    const startStr = document.getElementById('NgayBatDau').value;
    const months = parseInt(document.getElementById('ThoiHanThang').value || '6');

    if (!startStr) return;

    const startDate = new Date(startStr);
    startDate.setMonth(startDate.getMonth() + months);
    
    const year = startDate.getFullYear();
    const month = String(startDate.getMonth() + 1).padStart(2, '0');
    const day = String(startDate.getDate()).padStart(2, '0');

    document.getElementById('NgayKetThuc').value = `${year}-${month}-${day}`;
}

function previewFile(input, previewId) {
    const previewBox = document.getElementById(previewId);
    const fileNameSpan = previewBox.querySelector('span');

    if (input.files && input.files.length > 0) {
        if (input.files.length === 1) {
            fileNameSpan.innerText = input.files[0].name;
        } else {
            fileNameSpan.innerText = `${input.files.length} ảnh đã chọn`;
        }
        previewBox.style.display = 'inline-flex';
    } else {
        previewBox.style.display = 'none';
    }
}

function clearFile(inputId, previewId) {
    const input = document.getElementById(inputId);
    input.value = '';
    document.getElementById(previewId).style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('MaCanHo');
    if (select && select.value) {
        updateRoomInfo(select);
    }
    document.querySelectorAll('.currency-mask').forEach(input => {
        input.addEventListener('input', function() {
            const raw = this.value.replace(/\D/g, '');
            this.value = raw ? Number(raw).toLocaleString('vi-VN') : '';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
