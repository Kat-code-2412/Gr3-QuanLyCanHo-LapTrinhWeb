<?php

declare(strict_types=1);

$title = 'Chỉnh Sửa Hợp Đồng Thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$role = currentUserRole();
$baseUrl = url(($role === 'Admin') ? '/admin/hop-dong' : '/user/hop-dong');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã hợp đồng không hợp lệ.');
    redirect($baseUrl . '/index.php');
}

// Fetch thông tin hợp đồng hiện tại cùng thông tin khách thuê & căn hộ
$sql = "SELECT hp.*, ch.DiaChi AS DiaChiCanHo, kt.HoTen, kt.SoDienThoai, kt.Email, kt.CCCD,
           kt.NgaySinh, kt.GioiTinh, kt.DiaChiThuongTru, kt.NgheNghiep, kt.GhiChu AS GhiChuKhach
        FROM HopDong hp
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        WHERE hp.MaHopDong = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    setFlash('error', 'Không tìm thấy thông tin hợp đồng.');
    redirect($baseUrl . '/index.php');
}

if (!isStaffAssignedBuilding((string)($contract['DiaChiCanHo'] ?? ''))) {
    setFlash('error', 'Bạn không có quyền chỉnh sửa hợp đồng thuộc tòa nhà này.');
    redirect($baseUrl . '/index.php');
}

// Lấy danh sách căn hộ theo phân quyền
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

$errors = [];
$formData = [
    'HoTen' => $contract['HoTen'],
    'SoDienThoai' => $contract['SoDienThoai'],
    'Email' => $contract['Email'] ?? '',
    'CCCD' => $contract['CCCD'] ?? '',
    'NgaySinh' => $contract['NgaySinh'],
    'GioiTinh' => $contract['GioiTinh'],
    'DiaChiThuongTru' => $contract['DiaChiThuongTru'] ?? '',
    'NgheNghiep' => $contract['NgheNghiep'] ?? '',
    'GhiChuKhach' => $contract['GhiChuKhach'] ?? '',

    'MaCanHo' => (int)$contract['MaCanHo'],
    'NgayKy' => $contract['NgayKy'] ?? $contract['NgayBatDau'],
    'NgayBatDau' => $contract['NgayBatDau'],
    'ThoiHanThang' => (int)($contract['ThoiHanThang'] ?? 6),
    'NgayKetThuc' => $contract['NgayKetThuc'],
    'GiaThueThoaThuan' => $contract['GiaThueThoaThuan'],
    'TienCoc' => $contract['TienCoc'],
    'SoNguoiOi' => $contract['SoNguoiOi'],
    'SoXeMay' => $contract['SoXeMay'],
    'SoOto' => $contract['SoOto'],
    'GhiChuHopDong' => $contract['GhiChu'] ?? '',

    'GiaDien' => normalizeServiceFee($contract['GiaDien'] ?? 3800, 'dien'),
    'GiaNuoc' => normalizeServiceFee($contract['GiaNuoc'] ?? 100000, 'nuoc'),
    'GiaXeMay' => normalizeServiceFee($contract['GiaXeMay'] ?? 120000, 'xemay'),
    'GiaOto' => normalizeServiceFee($contract['GiaOto'] ?? 1200000, 'oto'),
    'GiaInternet' => normalizeServiceFee($contract['GiaInternet'] ?? 100000, 'internet'),
    'GiaVeSinh' => normalizeServiceFee($contract['GiaVeSinh'] ?? 50000, 'vesinh'),

    'TrangThaiHopDong' => $contract['TrangThai'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['HoTen'] = trim($_POST['HoTen'] ?? '');
    $formData['SoDienThoai'] = trim($_POST['SoDienThoai'] ?? '');
    $formData['Email'] = trim($_POST['Email'] ?? '');
    $formData['CCCD'] = trim($_POST['CCCD'] ?? '');
    $formData['NgaySinh'] = trim($_POST['NgaySinh'] ?? '');
    $formData['GioiTinh'] = trim($_POST['GioiTinh'] ?? 'Nam');
    $formData['DiaChiThuongTru'] = trim($_POST['DiaChiThuongTru'] ?? '');
    $formData['NgheNghiep'] = trim($_POST['NgheNghiep'] ?? '');
    $formData['GhiChuKhach'] = trim($_POST['GhiChuKhach'] ?? '');

    $newMaCanHo = (int)($_POST['MaCanHo'] ?? 0);
    $oldMaCanHo = (int)$contract['MaCanHo'];

    $formData['NgayKy'] = trim($_POST['NgayKy'] ?? date('Y-m-d'));
    $formData['NgayBatDau'] = trim($_POST['NgayBatDau'] ?? date('Y-m-d'));
    $formData['ThoiHanThang'] = max(1, (int)($_POST['ThoiHanThang'] ?? 6));
    $formData['NgayKetThuc'] = trim($_POST['NgayKetThuc'] ?? date('Y-m-d'));
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

    $formData['NgayCheckIn'] = trim($_POST['NgayCheckIn'] ?? $formData['NgayBatDau']);
    $formData['GioCheckIn'] = trim($_POST['GioCheckIn'] ?? '14:00');
    $formData['NgayCheckOut'] = trim($_POST['NgayCheckOut'] ?? $formData['NgayKetThuc']);
    $formData['GioCheckOut'] = trim($_POST['GioCheckOut'] ?? '12:00');
    $formData['TrangThaiHopDong'] = trim($_POST['TrangThaiHopDong'] ?? 'Đang hiệu lực');

    // Validations
    if ($formData['HoTen'] === '') {
        $errors['HoTen'] = 'Họ tên không được để trống.';
    }

    if ($formData['SoDienThoai'] === '') {
        $errors['SoDienThoai'] = 'Số điện thoại không được để trống.';
    } else {
        $checkPhone = $pdo->prepare('SELECT COUNT(*) FROM KhachThue WHERE SoDienThoai = ? AND MaKhach <> ?');
        $checkPhone->execute([$formData['SoDienThoai'], $contract['MaKhach']]);
        if ((int)$checkPhone->fetchColumn() > 0) {
            $errors['SoDienThoai'] = 'Số điện thoại này đã được dùng bởi khách khác.';
        }
    }

    if ($formData['CCCD'] !== '') {
        $checkCccd = $pdo->prepare('SELECT COUNT(*) FROM KhachThue WHERE CCCD = ? AND MaKhach <> ?');
        $checkCccd->execute([$formData['CCCD'], $contract['MaKhach']]);
        if ((int)$checkCccd->fetchColumn() > 0) {
            $errors['CCCD'] = 'Số CCCD này đã được dùng bởi khách khác.';
        }
    }

    if ($newMaCanHo <= 0) {
        $errors['MaCanHo'] = 'Vui lòng chọn căn hộ / phòng thuê.';
    } elseif ($newMaCanHo !== $oldMaCanHo) {
        // Kiểm tra phòng mới có đang có hợp đồng khác không
        $checkNewRoom = $pdo->prepare("SELECT TrangThai FROM CanHo WHERE MaCanHo = ?");
        $checkNewRoom->execute([$newMaCanHo]);
        $newRoomStatus = $checkNewRoom->fetchColumn();
        if ($newRoomStatus === 'Đang thuê') {
            $errors['MaCanHo'] = 'Phòng mới chọn đang có hợp đồng hiệu lực.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Cập nhật KhachThue
            $updateKt = $pdo->prepare('UPDATE KhachThue SET HoTen = :hoTen, SoDienThoai = :sdt, Email = :email, CCCD = :cccd WHERE MaKhach = :id');
            $updateKt->execute([
                ':hoTen' => $formData['HoTen'],
                ':sdt' => $formData['SoDienThoai'],
                ':email' => ($formData['Email'] !== '') ? $formData['Email'] : null,
                ':cccd' => ($formData['CCCD'] !== '') ? $formData['CCCD'] : null,
                ':id' => $contract['MaKhach'],
            ]);

            // 2. Upload Files nếu có chọn file mới
            $fileHopDong = uploadFile($_FILES['file_hop_dong'] ?? [], 'contracts') ?? $contract['FileHopDong'];
            $fileCccdTruoc = $contract['AnhCCCDMatTruoc'] ?? $contract['AnhCCCD'];
            $fileCccdSau = $contract['AnhCCCDMatSau'];

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
                    if (!empty($uploaded[0])) {
                        $fileCccdTruoc = $uploaded[0];
                    }
                    if (!empty($uploaded[1])) {
                        $fileCccdSau = $uploaded[1];
                    }
                } else {
                    $up = uploadFile($_FILES['file_cccd'], 'cccd');
                    if ($up) $fileCccdTruoc = $up;
                }
            }
            if (!empty($_FILES['file_cccd_truoc'])) {
                $up = uploadFile($_FILES['file_cccd_truoc'], 'cccd');
                if ($up) $fileCccdTruoc = $up;
            }
            if (!empty($_FILES['file_cccd_sau'])) {
                $up = uploadFile($_FILES['file_cccd_sau'], 'cccd');
                if ($up) $fileCccdSau = $up;
            }

            // 3. Cập nhật HopDong
            $updateHd = $pdo->prepare('UPDATE HopDong SET 
                MaCanHo = :maCanHo, 
                NgayBatDau = :ngayBatDau, 
                NgayKetThuc = :ngayKetThuc, 
                GiaThueThoaThuan = :giaThue, 
                TienCoc = :tienCoc, 
                TrangThai = :trangThai, 
                GhiChu = :ghiChu, 
                FileHopDong = :fileHd, 
                AnhCCCDMatTruoc = :cccdTruoc, 
                AnhCCCDMatSau = :cccdSau,
                SoNguoiOi = :soNguoi,
                SoXeMay = :soXeMay,
                SoOto = :soOto,
                GiaDien = :giaDien,
                GiaNuoc = :giaNuoc,
                GiaXeMay = :giaXeMay,
                GiaOto = :giaOto,
                GiaInternet = :giaInternet,
                GiaVeSinh = :giaVeSinh
            WHERE MaHopDong = :id');
            $updateHd->execute([
                ':maCanHo' => $newMaCanHo,
                ':ngayBatDau' => $formData['NgayBatDau'],
                ':ngayKetThuc' => $formData['NgayKetThuc'],
                ':giaThue' => $formData['GiaThueThoaThuan'],
                ':tienCoc' => $formData['TienCoc'],
                ':trangThai' => $formData['TrangThaiHopDong'],
                ':ghiChu' => ($formData['GhiChuHopDong'] !== '') ? $formData['GhiChuHopDong'] : null,
                ':fileHd' => $fileHopDong,
                ':cccdTruoc' => $fileCccdTruoc,
                ':cccdSau' => $fileCccdSau,
                ':soNguoi' => $formData['SoNguoiOi'],
                ':soXeMay' => $formData['SoXeMay'],
                ':soOto' => $formData['SoOto'],
                ':giaDien' => $formData['GiaDien'],
                ':giaNuoc' => $formData['GiaNuoc'],
                ':giaXeMay' => $formData['GiaXeMay'],
                ':giaOto' => $formData['GiaOto'],
                ':giaInternet' => $formData['GiaInternet'],
                ':giaVeSinh' => $formData['GiaVeSinh'],
                ':id' => $id,
            ]);

            // 4. Nếu đổi phòng -> Chuyển phòng cũ thành Trống, phòng mới thành Đang thuê
            if ($newMaCanHo !== $oldMaCanHo) {
                $pdo->prepare("UPDATE CanHo SET TrangThai = 'Trống' WHERE MaCanHo = ?")->execute([$oldMaCanHo]);
                if ($formData['TrangThaiHopDong'] === 'Đang hiệu lực') {
                    $pdo->prepare("UPDATE CanHo SET TrangThai = 'Đang thuê' WHERE MaCanHo = ?")->execute([$newMaCanHo]);
                }
            } elseif ($formData['TrangThaiHopDong'] === 'Đã thanh lý') {
                $pdo->prepare("UPDATE CanHo SET TrangThai = 'Trống' WHERE MaCanHo = ?")->execute([$newMaCanHo]);
            } elseif ($formData['TrangThaiHopDong'] === 'Đang hiệu lực') {
                $pdo->prepare("UPDATE CanHo SET TrangThai = 'Đang thuê' WHERE MaCanHo = ?")->execute([$newMaCanHo]);
            }

            $pdo->commit();

            setFlash('success', 'Cập nhật hợp đồng #' . $id . ' thành công.');
            redirect($baseUrl . '/detail.php?id=' . $id);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = 'Lỗi hệ thống khi cập nhật: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sửa Hợp Đồng Thuê #<?= e((string)$contract['MaHopDong']) ?></h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/detail.php?id=<?= $id ?>" class="btn btn-outline">← Hủy chỉnh sửa</a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <!-- THÔNG TIN KHÁCH THUÊ -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>THÔNG TIN KHÁCH THUÊ</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="HoTen" style="font-weight: 600;">Họ và tên <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" id="HoTen" name="HoTen" class="form-control" value="<?= e($formData['HoTen']) ?>" required>
                    <?php if (isset($errors['HoTen'])): ?><small style="color: var(--danger-color);"><?= e($errors['HoTen']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="SoDienThoai" style="font-weight: 600;">Số điện thoại <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" id="SoDienThoai" name="SoDienThoai" class="form-control" value="<?= e($formData['SoDienThoai']) ?>" required>
                    <?php if (isset($errors['SoDienThoai'])): ?><small style="color: var(--danger-color);"><?= e($errors['SoDienThoai']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="Email" style="font-weight: 600;">Email</label>
                    <input type="email" id="Email" name="Email" class="form-control" value="<?= e($formData['Email']) ?>">
                </div>

                <div class="form-group">
                    <label for="CCCD" style="font-weight: 600;">Số CCCD / CMND</label>
                    <input type="text" id="CCCD" name="CCCD" class="form-control" value="<?= e($formData['CCCD']) ?>">
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
                    <input type="text" id="NgheNghiep" name="NgheNghiep" class="form-control" value="<?= e($formData['NgheNghiep']) ?>">
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
                                    data-building="<?= e($ch['DiaChi'] ?? 'Tòa nhà A') ?>"
                                    data-so-phong="<?= e($ch['SoPhong'] ?? ('#' . $ch['MaCanHo'])) ?>"
                                    data-dia-chi="<?= e($ch['DiaChi'] ?? 'Tòa nhà A') ?>"
                                    data-gia="<?= (float)($ch['GiaThue'] ?? 0) ?>"
                                    data-trang-thai="<?= e($ch['TrangThai'] ?? 'Trống') ?>"
                                    data-loai="<?= e($ch['TenLoai'] ?? 'Tiêu chuẩn') ?>"
                                    data-dien-tich="<?= (float)($ch['DienTich'] ?? 0) ?>"
                                    data-noi-that="<?= e($ch['MoTa'] ?? 'Đầy đủ tiện nghi cơ bản') ?>"
                                    <?= ($formData['MaCanHo'] === (int)$ch['MaCanHo']) ? 'selected' : '' ?>>
                                Phòng <?= e($ch['SoPhong'] ?? '') ?> (<?= e($ch['TenLoai'] ?? 'Tiêu chuẩn') ?>) - <?= formatMoney((float)($ch['GiaThue'] ?? 0)) ?> [<?= e($ch['TrangThai'] ?? 'Trống') ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaCanHo'])): ?><small style="color: var(--danger-color);"><?= e($errors['MaCanHo']) ?></small><?php endif; ?>
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
        </div>
    </div>

    <!-- THÔNG TIN HỢP ĐỒNG & CHI PHÍ -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>THÔNG TIN HỢP ĐỒNG & CHI PHÍ</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="NgayBatDau" style="font-weight: 600;">Ngày bắt đầu</label>
                    <input type="date" id="NgayBatDau" name="NgayBatDau" class="form-control" value="<?= e($formData['NgayBatDau']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="NgayKetThuc" style="font-weight: 600;">Ngày kết thúc</label>
                    <input type="date" id="NgayKetThuc" name="NgayKetThuc" class="form-control" value="<?= e($formData['NgayKetThuc']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="GiaThueThoaThuan" style="font-weight: 600;">Giá thuê thỏa thuận (VNĐ/tháng)</label>
                    <input type="text" id="GiaThueThoaThuan" name="GiaThueThoaThuan" class="form-control currency-mask" value="<?= is_numeric($formData['GiaThueThoaThuan']) ? number_format((float)$formData['GiaThueThoaThuan'], 0, '', '.') : e((string)$formData['GiaThueThoaThuan']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="TienCoc" style="font-weight: 600;">Tiền đặt cọc (VNĐ)</label>
                    <input type="text" id="TienCoc" name="TienCoc" class="form-control currency-mask" value="<?= is_numeric($formData['TienCoc']) ? number_format((float)$formData['TienCoc'], 0, '', '.') : e((string)$formData['TienCoc']) ?>">
                </div>

                <div class="form-group">
                    <label for="SoNguoiOi" style="font-weight: 600;">Số người ở</label>
                    <input type="number" min="1" id="SoNguoiOi" name="SoNguoiOi" class="form-control" value="<?= (int)($formData['SoNguoiOi'] ?? 1) ?>" required>
                </div>

                <div class="form-group">
                    <label for="SoXeMay" style="font-weight: 600;">Số xe máy</label>
                    <input type="number" min="0" id="SoXeMay" name="SoXeMay" class="form-control" value="<?= (int)($formData['SoXeMay'] ?? 0) ?>">
                </div>

                <div class="form-group">
                    <label for="SoOto" style="font-weight: 600;">Số ô tô</label>
                    <input type="number" min="0" id="SoOto" name="SoOto" class="form-control" value="<?= (int)($formData['SoOto'] ?? 0) ?>">
                </div>

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

                <div class="form-group">
                    <label for="TrangThaiHopDong" style="font-weight: 700;">Trạng thái hợp đồng</label>
                    <select id="TrangThaiHopDong" name="TrangThaiHopDong" class="form-control">
                        <option value="Đang hiệu lực" <?= ($formData['TrangThaiHopDong'] === 'Đang hiệu lực') ? 'selected' : '' ?>>Đang hiệu lực</option>
                        <option value="Sắp hết hạn" <?= ($formData['TrangThaiHopDong'] === 'Sắp hết hạn') ? 'selected' : '' ?>>Sắp hết hạn</option>
                        <option value="Đã thanh lý" <?= ($formData['TrangThaiHopDong'] === 'Đã thanh lý') ? 'selected' : '' ?>>Đã thanh lý</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- UPLOAD FILE SCAN HỢP ĐỒNG & CCCD -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>UPLOAD FILE SCAN HỢP ĐỒNG & CCCD</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                <div class="form-group">
                    <label for="file_hop_dong" style="font-weight: 600;">File Hợp Đồng Scan (Upload để thay mới)</label>
                    <input type="file" id="file_hop_dong" name="file_hop_dong" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
                <div class="form-group">
                    <label for="file_cccd" style="font-weight: 600;">Ảnh CCCD / CMND (Upload để thay mới)</label>
                    <input type="file" id="file_cccd" name="file_cccd[]" class="form-control" accept="image/*" multiple>
                    <small class="text-muted" style="display: block; margin-top: 4px; font-size: 0.8rem;">
                        Có thể chọn 1 ảnh hoặc chọn cả 2 mặt trước và sau (JPG, PNG)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 3rem;">
        <a href="<?= $baseUrl ?>/detail.php?id=<?= $id ?>" class="btn btn-outline">Hủy</a>
        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-weight: 700;">
            Lưu Thay Đổi
        </button>
    </div>
</form>

<script>
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
}

function updateRoomInfo(select) {
    const opt = select.options[select.selectedIndex];
    const infoBox = document.getElementById('roomInfoBox');

    if (!select.value) {
        infoBox.style.display = 'none';
        return;
    }

    const soPhong = opt.getAttribute('data-so-phong');
    const diaChi = opt.getAttribute('data-dia-chi');
    const gia = parseFloat(opt.getAttribute('data-gia') || '0');
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
}

document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('MaCanHo');
    if (select && select.value) {
        updateRoomInfo(select);
        const selectedOpt = select.options[select.selectedIndex];
        const building = selectedOpt ? selectedOpt.getAttribute('data-building') : '';
        const bSelect = document.getElementById('selectBuilding');
        if (bSelect && building) {
            bSelect.value = building;
        }
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

