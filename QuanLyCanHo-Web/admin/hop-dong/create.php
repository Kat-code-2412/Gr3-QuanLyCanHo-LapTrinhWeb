<?php

declare(strict_types=1);

$title = 'Thêm Khách Thuê';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$role = currentUserRole();
$baseUrl = url(($role === 'Admin') ? '/admin/hop-dong' : '/user/hop-dong');
$currentStaffId = $_SESSION['MaNV'] ?? null;

// Lấy danh sách tất cả các Căn hộ kèm Loại căn hộ để đổ vào Dropdown
$canHoStmt = $pdo->query('SELECT * FROM CanHo ORDER BY Tang ASC, MaCanHo ASC');
$canHoList = $canHoStmt->fetchAll();

// Lấy danh sách các Địa chỉ Tòa nhà độc bản
$diaChiList = [];

$errors = [];
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
    'ThoiHanThang' => 6,
    'NgayKetThuc' => date('Y-m-d', strtotime('+6 months')),
    'GiaThueThoaThuan' => '',
    'TienCoc' => '',
    'SoNguoiOi' => 1,
    'SoXeMay' => 1,
    'SoOto' => 0,
    'GhiChuHopDong' => '',

    // Điện / Nước / Dịch vụ
    'GiaDien' => 3500,
    'GiaNuoc' => 15000,
    'GiaXeMay' => 150000,
    'GiaOto' => 1200000,
    'GiaInternet' => 200000,
    'GiaVeSinh' => 100000,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect Form Data
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
    $formData['ThoiHanThang'] = max(1, (int)($_POST['ThoiHanThang'] ?? 6));
    $formData['NgayKetThuc'] = trim($_POST['NgayKetThuc'] ?? date('Y-m-d', strtotime("+{$formData['ThoiHanThang']} months")));
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

    // Validations
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
            $errors['SoDienThoai'] = 'Số điện thoại này đã tồn tại trên hệ thống.';
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

    if ($formData['MaCanHo'] <= 0) {
        $errors['MaCanHo'] = 'Vui lòng chọn căn hộ / phòng thuê.';
    } else {
        $checkRoom = $pdo->prepare("SELECT TrangThai FROM CanHo WHERE MaCanHo = ?");
        $checkRoom->execute([$formData['MaCanHo']]);
        $roomStatus = $checkRoom->fetchColumn();
        if ($roomStatus === 'Đang thuê') {
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

            // 1. Insert KhachThue
            $insertKhachSql = 'INSERT INTO KhachThue (HoTen, CCCD, SoDienThoai, Email)
                               VALUES (:hoTen, :cccd, :soDienThoai, :email)';
            $stmtKhach = $pdo->prepare($insertKhachSql);
            $stmtKhach->execute([
                ':hoTen' => $formData['HoTen'],
                ':cccd' => ($formData['CCCD'] !== '') ? $formData['CCCD'] : null,
                ':soDienThoai' => $formData['SoDienThoai'],
                ':email' => ($formData['Email'] !== '') ? $formData['Email'] : null,
            ]);
            $maKhach = (int)$pdo->lastInsertId();

            // 2. Upload Files
            $fileHopDong = uploadFile($_FILES['file_hop_dong'] ?? [], 'contracts');
            $fileCccdTruoc = uploadFile($_FILES['file_cccd_truoc'] ?? [], 'cccd');
            $fileCccdSau = uploadFile($_FILES['file_cccd_sau'] ?? [], 'cccd');

            // 3. Insert HopDong
            $insertHdSql = 'INSERT INTO HopDong (MaCanHo, MaKhach, MaNV, NgayBatDau, NgayKetThuc, GiaThueThoaThuan, TienCoc, TrangThai, GhiChu)
                            VALUES (:maCanHo, :maKhach, :maNv, :ngayBatDau, :ngayKetThuc, :giaThue, :tienCoc, "Đang hiệu lực", :ghiChu)';
            $stmtHd = $pdo->prepare($insertHdSql);
            $stmtHd->execute([
                ':maCanHo' => $formData['MaCanHo'],
                ':maKhach' => $maKhach,
                ':maNv' => $currentStaffId,
                ':ngayBatDau' => $formData['NgayBatDau'],
                ':ngayKetThuc' => $formData['NgayKetThuc'],
                ':giaThue' => $formData['GiaThueThoaThuan'],
                ':tienCoc' => $formData['TienCoc'],
                ':ghiChu' => ($formData['GhiChuHopDong'] !== '') ? $formData['GhiChuHopDong'] : null,
            ]);

            // 4. Update CanHo -> Đang thuê
            $pdo->prepare("UPDATE CanHo SET TrangThai = 'Đang thuê' WHERE MaCanHo = ?")->execute([$formData['MaCanHo']]);

            $pdo->commit();

            setFlash('success', 'Thêm khách thuê và lập hợp đồng thành công.');
            redirect($baseUrl . '/index.php');
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
        <h1 class="page-title">Thêm Khách Thuê</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Quay lại danh sách</a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon">✕</span>
        <div><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data" id="createForm">
    <!-- THÔNG TIN KHÁCH THUÊ -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>👤 THÔNG TIN KHÁCH THUÊ</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="HoTen" style="font-weight: 600;">Họ và tên khách thuê <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" id="HoTen" name="HoTen" class="form-control" placeholder="Nhập họ và tên đầy đủ..." value="<?= e($formData['HoTen']) ?>" required>
                    <?php if (isset($errors['HoTen'])): ?><small style="color: var(--danger-color);"><?= e($errors['HoTen']) ?></small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="SoDienThoai" style="font-weight: 600;">Số điện thoại <span style="color: var(--danger-color);">*</span></label>
                    <input type="text" id="SoDienThoai" name="SoDienThoai" class="form-control" placeholder="Ví dụ: 0912345678" value="<?= e($formData['SoDienThoai']) ?>" required>
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

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="DiaChiThuongTru" style="font-weight: 600;">Địa chỉ thường trú</label>
                    <input type="text" id="DiaChiThuongTru" name="DiaChiThuongTru" class="form-control" placeholder="Địa chỉ theo CCCD..." value="<?= e($formData['DiaChiThuongTru']) ?>">
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
                                    data-building=""
                                    data-so-phong="<?= e($ch['MaCanHoHienThi'] ?? ('#' . $ch['MaCanHo'])) ?>"
                                    data-dia-chi="Tầng <?= e((string)($ch['Tang'] ?? '-')) ?>"
                                    data-gia=""
                                    data-trang-thai="<?= e($ch['TrangThai']) ?>"
                                    data-loai="Căn hộ"
                                    data-dien-tich="<?= $ch['DienTich'] ?>"
                                    data-noi-that=""
                                    <?= ($formData['MaCanHo'] === (int)$ch['MaCanHo']) ? 'selected' : '' ?>>
                                Phòng <?= e($ch['MaCanHoHienThi'] ?? ('#' . $ch['MaCanHo'])) ?> - [<?= e($ch['TrangThai']) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['MaCanHo'])): ?>
                        <small style="color: var(--danger-color); font-weight: 600; display: block; margin-top: 0.5rem;"><?= e($errors['MaCanHo']) ?></small>
                    <?php endif; ?>
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

            <div id="roomWarning" class="alert alert-danger mt-3" style="display: none;">
                <span class="alert-icon">⚠️</span>
                <div style="font-weight: 700;">Phòng này đang có hợp đồng hiệu lực. Vui lòng chọn phòng khác có trạng thái Trống!</div>
            </div>
        </div>
    </div>

    <!-- THÔNG TIN HỢP ĐỒNG -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>📄 THÔNG TIN HỢP ĐỒNG</h3>
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
                    <input type="number" step="50000" id="GiaThueThoaThuan" name="GiaThueThoaThuan" class="form-control" placeholder="Ví dụ: 9600000" value="<?= e((string)$formData['GiaThueThoaThuan']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="TienCoc" style="font-weight: 600;">Tiền đặt cọc (VNĐ)</label>
                    <input type="number" step="50000" id="TienCoc" name="TienCoc" class="form-control" placeholder="Ví dụ: 19200000" value="<?= e((string)$formData['TienCoc']) ?>">
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
            <h3>⚡ ĐƠN GIÁ ĐIỆN / NƯỚC / DỊCH VỤ</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div class="form-group">
                    <label for="GiaDien" style="font-weight: 600;">Đơn giá điện (VNĐ/kWh)</label>
                    <input type="number" id="GiaDien" name="GiaDien" class="form-control" value="<?= e((string)$formData['GiaDien']) ?>">
                </div>

                <div class="form-group">
                    <label for="GiaNuoc" style="font-weight: 600;">Đơn giá nước (VNĐ)</label>
                    <input type="number" id="GiaNuoc" name="GiaNuoc" class="form-control" value="<?= e((string)$formData['GiaNuoc']) ?>">
                </div>

                <div class="form-group">
                    <label for="GiaXeMay" style="font-weight: 600;">Phí gửi xe máy (VNĐ/xe/tháng)</label>
                    <input type="number" id="GiaXeMay" name="GiaXeMay" class="form-control" value="<?= e((string)$formData['GiaXeMay']) ?>">
                </div>

                <div class="form-group">
                    <label for="GiaOto" style="font-weight: 600;">Phí gửi ô tô (VNĐ/xe/tháng)</label>
                    <input type="number" id="GiaOto" name="GiaOto" class="form-control" value="<?= e((string)$formData['GiaOto']) ?>">
                </div>

                <div class="form-group">
                    <label for="GiaInternet" style="font-weight: 600;">Phí Internet / Dịch vụ (VNĐ/tháng)</label>
                    <input type="number" id="GiaInternet" name="GiaInternet" class="form-control" value="<?= e((string)$formData['GiaInternet']) ?>">
                </div>

                <div class="form-group">
                    <label for="GiaVeSinh" style="font-weight: 600;">Phí Vệ sinh / Rác (VNĐ/tháng)</label>
                    <input type="number" id="GiaVeSinh" name="GiaVeSinh" class="form-control" value="<?= e((string)$formData['GiaVeSinh']) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- UPLOAD HÌNH ẢNH & FILE SCAN HỢP ĐỒNG (CÓ XEM & XÓA FILE + 2 ẢNH CCCD MẶT TRƯỚC/SAU) -->
    <div class="card mb-3">
        <div class="card-header" style="background-color: #f8fafc; border-bottom: 2px solid var(--primary-color);">
            <h3>📁 UPLOAD HÌNH ẢNH & FILE SCAN HỢP ĐỒNG</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <!-- File Hợp đồng Scan -->
                <div class="form-group">
                    <label for="file_hop_dong" style="font-weight: 600;">📄 File Hợp Đồng Scan (PDF, DOC, JPG...)</label>
                    <input type="file" id="file_hop_dong" name="file_hop_dong" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="previewFile(this, 'previewHd')">
                    <div id="previewHd" style="margin-top: 0.5rem; display: none; align-items: center; gap: 0.5rem;">
                        <span id="fileNameHd" style="font-weight: 600; color: var(--primary-color);"></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="clearFile('file_hop_dong', 'previewHd')">🗑️ Xóa</button>
                    </div>
                </div>

                <!-- Ảnh CCCD Mặt Trước -->
                <div class="form-group">
                    <label for="file_cccd_truoc" style="font-weight: 600;">💳 Ảnh Mặt Trước CCCD / CMND</label>
                    <input type="file" id="file_cccd_truoc" name="file_cccd_truoc" class="form-control" accept="image/*" onchange="previewFile(this, 'previewCccdTruoc')">
                    <div id="previewCccdTruoc" style="margin-top: 0.5rem; display: none; align-items: center; gap: 0.5rem;">
                        <span id="fileNameCccdTruoc" style="font-weight: 600; color: var(--primary-color);"></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="clearFile('file_cccd_truoc', 'previewCccdTruoc')">🗑️ Xóa</button>
                    </div>
                </div>

                <!-- Ảnh CCCD Mặt Sau -->
                <div class="form-group">
                    <label for="file_cccd_sau" style="font-weight: 600;">💳 Ảnh Mặt Sau CCCD / CMND</label>
                    <input type="file" id="file_cccd_sau" name="file_cccd_sau" class="form-control" accept="image/*" onchange="previewFile(this, 'previewCccdSau')">
                    <div id="previewCccdSau" style="margin-top: 0.5rem; display: none; align-items: center; gap: 0.5rem;">
                        <span id="fileNameCccdSau" style="font-weight: 600; color: var(--primary-color);"></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="clearFile('file_cccd_sau', 'previewCccdSau')">🗑️ Xóa</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NÚT LƯU VÀ HỦY -->
    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 3rem;">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="padding: 0.75rem 2rem; font-weight: 600;">Hủy</a>
        <button type="submit" id="btnSubmit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-size: 1.05rem; font-weight: 700;">
            Lưu Khách Thuê
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

    infoBox.style.display = 'grid';

    const inputGia = document.getElementById('GiaThueThoaThuan');
    if (!inputGia.value || inputGia.value == 0) {
        inputGia.value = gia;
        const inputCoc = document.getElementById('TienCoc');
        if (!inputCoc.value || inputCoc.value == 0) {
            inputCoc.value = gia * 2;
        }
    }

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

    if (input.files && input.files[0]) {
        fileNameSpan.innerText = '📄 ' + input.files[0].name;
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
    if (select.value) {
        updateRoomInfo(select);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
