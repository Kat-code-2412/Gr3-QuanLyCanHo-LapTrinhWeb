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

// Fetch thông tin hợp đồng hiện tại cùng thông tin khách thuê
$sql = "SELECT hp.*, kt.HoTen, kt.SoDienThoai, kt.Email, kt.CCCD,
           NULL AS NgaySinh, NULL AS GioiTinh, NULL AS DiaChiThuongTru, NULL AS NgheNghiep, NULL AS GhiChuKhach
        FROM HopDong hp
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        WHERE hp.MaHopDong = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    setFlash('error', 'Không tìm thấy thông tin hợp đồng.');
    redirect($baseUrl . '/index.php');
}

// Lấy danh sách tất cả các căn hộ
$canHoList = $pdo->query('SELECT * FROM CanHo ORDER BY SoPhong ASC, MaCanHo ASC')->fetchAll();

// Lấy danh sách các Địa chỉ Tòa nhà độc bản
$diaChiList = [];

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

    'GiaDien' => $contract['GiaDien'] ?? 3500,
    'GiaNuoc' => $contract['GiaNuoc'] ?? 15000,
    'GiaXeMay' => $contract['GiaXeMay'] ?? 150000,
    'GiaOto' => $contract['GiaOto'] ?? 1200000,
    'GiaInternet' => $contract['GiaInternet'] ?? 200000,
    'GiaVeSinh' => $contract['GiaVeSinh'] ?? 100000,

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
    $formData['GiaThueThoaThuan'] = (float)($_POST['GiaThueThoaThuan'] ?? 0);
    $formData['TienCoc'] = (float)($_POST['TienCoc'] ?? 0);
    $formData['SoNguoiOi'] = max(1, (int)($_POST['SoNguoiOi'] ?? 1));
    $formData['SoXeMay'] = max(0, (int)($_POST['SoXeMay'] ?? 0));
    $formData['SoOto'] = max(0, (int)($_POST['SoOto'] ?? 0));
    $formData['GhiChuHopDong'] = trim($_POST['GhiChuHopDong'] ?? '');

    $formData['GiaDien'] = (float)($_POST['GiaDien'] ?? 3500);
    $formData['GiaNuoc'] = (float)($_POST['GiaNuoc'] ?? 15000);
    $formData['GiaXeMay'] = (float)($_POST['GiaXeMay'] ?? 150000);
    $formData['GiaOto'] = (float)($_POST['GiaOto'] ?? 1200000);
    $formData['GiaInternet'] = (float)($_POST['GiaInternet'] ?? 200000);
    $formData['GiaVeSinh'] = (float)($_POST['GiaVeSinh'] ?? 100000);

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
            $fileCccdTruoc = uploadFile($_FILES['file_cccd_truoc'] ?? [], 'cccd') ?? ($contract['AnhCCCDMatTruoc'] ?? $contract['AnhCCCD']);
            $fileCccdSau = uploadFile($_FILES['file_cccd_sau'] ?? [], 'cccd') ?? $contract['AnhCCCDMatSau'];

            // 3. Cập nhật HopDong
            $updateHd = $pdo->prepare('UPDATE HopDong SET MaCanHo = :maCanHo, NgayBatDau = :ngayBatDau, NgayKetThuc = :ngayKetThuc, GiaThueThoaThuan = :giaThue, TienCoc = :tienCoc, TrangThai = :trangThai, GhiChu = :ghiChu WHERE MaHopDong = :id');
            $updateHd->execute([
                ':maCanHo' => $newMaCanHo,
                ':ngayBatDau' => $formData['NgayBatDau'],
                ':ngayKetThuc' => $formData['NgayKetThuc'],
                ':giaThue' => $formData['GiaThueThoaThuan'],
                ':tienCoc' => $formData['TienCoc'],
                ':trangThai' => $formData['TrangThaiHopDong'],
                ':ghiChu' => ($formData['GhiChuHopDong'] !== '') ? $formData['GhiChuHopDong'] : null,
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
        <span class="alert-icon">✕</span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <!-- THÔNG TIN KHÁCH THUÊ -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>👤 THÔNG TIN KHÁCH THUÊ</h3>
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

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="DiaChiThuongTru" style="font-weight: 600;">Địa chỉ thường trú</label>
                    <input type="text" id="DiaChiThuongTru" name="DiaChiThuongTru" class="form-control" value="<?= e($formData['DiaChiThuongTru']) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- THÔNG TIN CĂN HỘ / PHÒNG -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>🏠 THÔNG TIN CĂN HỘ / PHÒNG</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <!-- Chọn Địa chỉ Tòa nhà -->
                <div class="form-group">
                    <label for="selectBuilding" style="font-weight: 700;">📍 Chọn Địa Chỉ Tòa Nhà</label>
                    <select id="selectBuilding" class="form-control" onchange="filterRoomsByBuilding(this.value)">
                        <option value="">-- Tất cả các tòa nhà --</option>
                        <?php foreach ($diaChiList as $dc): ?>
                            <option value="<?= e($dc) ?>"><?= e($dc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Chọn Mã phòng -->
                <div class="form-group">
                    <label for="MaCanHo" style="font-weight: 700;">🏢 Chọn Mã Phòng / Căn Hộ <span style="color: var(--danger-color);">*</span></label>
                    <select id="MaCanHo" name="MaCanHo" class="form-control" style="font-weight: 600;" required onchange="updateRoomInfo(this)">
                        <option value="">-- Chọn Mã Phòng --</option>
                        <?php foreach ($canHoList as $ch): ?>
                            <option value="<?= $ch['MaCanHo'] ?>" 
                                    data-building="<?= e($ch['DiaChi'] ?? 'Tòa nhà A') ?>"
                                    data-so-phong="<?= e($ch['SoPhong']) ?>"
                                    data-dia-chi="<?= e($ch['DiaChi'] ?? 'Tòa nhà A') ?>"
                                    data-gia="<?= $ch['GiaThue'] ?>"
                                    data-trang-thai="<?= e($ch['TrangThai']) ?>"
                                    data-loai="<?= e($ch['TenLoai']) ?>"
                                    data-dien-tich="<?= $ch['DienTich'] ?>"
                                    data-noi-that="<?= e($ch['MoTa'] ?? 'Đầy đủ tiện nghi cơ bản') ?>"
                                    <?= ($formData['MaCanHo'] === (int)$ch['MaCanHo']) ? 'selected' : '' ?>>
                                Phòng <?= e($ch['SoPhong']) ?> (<?= e($ch['TenLoai']) ?>) - <?= formatMoney($ch['GiaThue']) ?> [<?= e($ch['TrangThai']) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaCanHo'])): ?><small style="color: var(--danger-color);"><?= e($errors['MaCanHo']) ?></small><?php endif; ?>
                </div>
            </div>

            <!-- TÁCH RIÊNG CÁC TRƯỜNG THÔNG TIN CĂN HỘ CHI TIẾT -->
            <div id="roomInfoBox" class="detail-grid" style="background-color: #f1f5f9; padding: 1.25rem; border-radius: 8px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); display: none;">
                <div class="detail-item">
                    <div class="detail-label">📍 Địa chỉ tòa nhà</div>
                    <div class="detail-value" id="boxDiaChi" style="font-weight: 700; color: var(--primary-color);">-</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">🏢 Mã phòng / Số phòng</div>
                    <div class="detail-value" id="boxSoPhong" style="font-weight: 700; font-size: 1.15rem;">-</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">🛋️ Dạng phòng (Loại phòng)</div>
                    <div class="detail-value" id="boxLoaiPhong" style="font-weight: 600;">-</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">📐 Diện tích phòng</div>
                    <div class="detail-value" id="boxDienTich">-</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">💰 Giá phòng niêm yết</div>
                    <div class="detail-value" id="boxGiaThue" style="font-weight: 700; color: var(--success-color);">-</div>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-label">📦 Nội thất & tiện ích đi kèm</div>
                    <div class="detail-value" id="boxNoiThat">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- THÔNG TIN HỢP ĐỒNG & CHI PHÍ -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>📄 THÔNG TIN HỢP ĐỒNG & CHI PHÍ</h3>
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
                    <label for="GiaThueThoaThuan" style="font-weight: 600;">Giá thuê thỏa thuận (VNĐ)</label>
                    <input type="number" id="GiaThueThoaThuan" name="GiaThueThoaThuan" class="form-control" value="<?= e((string)$formData['GiaThueThoaThuan']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="TienCoc" style="font-weight: 600;">Tiền đặt cọc (VNĐ)</label>
                    <input type="number" id="TienCoc" name="TienCoc" class="form-control" value="<?= e((string)$formData['TienCoc']) ?>">
                </div>

                <div class="form-group">
                    <label for="GiaDien" style="font-weight: 600;">Đơn giá điện (VNĐ/kWh)</label>
                    <input type="number" id="GiaDien" name="GiaDien" class="form-control" value="<?= e((string)$formData['GiaDien']) ?>">
                </div>

                <div class="form-group">
                    <label for="GiaNuoc" style="font-weight: 600;">Đơn giá nước (đ)</label>
                    <input type="number" id="GiaNuoc" name="GiaNuoc" class="form-control" value="<?= e((string)$formData['GiaNuoc']) ?>">
                </div>

                <div class="form-group">
                    <label for="TrangThaiHopDong" style="font-weight: 700;">Trạng thái hợp đồng</label>
                    <select id="TrangThaiHopDong" name="TrangThaiHopDong" class="form-control">
                        <option value="Đang hiệu lực" <?= ($formData['TrangThaiHopDong'] === 'Đang hiệu lực') ? 'selected' : '' ?>>Đang hiệu lực</option>
                        <option value="Chưa check-in" <?= ($formData['TrangThaiHopDong'] === 'Chưa check-in') ? 'selected' : '' ?>>Chưa check-in</option>
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
            <h3>📁 UPLOAD FILE SCAN HỢP ĐỒNG & CCCD</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="file_hop_dong" style="font-weight: 600;">📄 File Hợp Đồng Scan (Upload để thay mới)</label>
                    <input type="file" id="file_hop_dong" name="file_hop_dong" class="form-control">
                </div>
                <div class="form-group">
                    <label for="file_cccd_truoc" style="font-weight: 600;">💳 Ảnh Mặt Trước CCCD / CMND</label>
                    <input type="file" id="file_cccd_truoc" name="file_cccd_truoc" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="file_cccd_sau" style="font-weight: 600;">💳 Ảnh Mặt Sau CCCD / CMND</label>
                    <input type="file" id="file_cccd_sau" name="file_cccd_sau" class="form-control" accept="image/*">
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

    infoBox.style.display = 'grid';
}

document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('MaCanHo');
    if (select.value) {
        updateRoomInfo(select);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

