<?php

declare(strict_types=1);

$title = 'Sửa Thông Tin Nhân Viên';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url('/admin/nhan-vien');
$currentUserId = (int)($_SESSION['MaNV'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã nhân viên không hợp lệ.');
    redirect('/admin/nhan-vien/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM NhanVien WHERE MaNV = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('error', 'Không tìm thấy nhân viên yêu cầu.');
    redirect('/admin/nhan-vien/index.php');
}

$isSelf = ($currentUserId === (int)$employee['MaNV']);
$errors = [];
$formData = [
    'HoTen'       => $employee['HoTen'],
    'TenDangNhap' => $employee['TenDangNhap'],
    'MatKhau'     => '',
    'VaiTro'      => $employee['VaiTro'],
    'SoDienThoai' => $employee['SoDienThoai'] ?? '',
    'Email'       => $employee['Email'] ?? '',
    'TrangThai'   => $employee['TrangThai'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $formData['HoTen']       = trim((string)($_POST['HoTen'] ?? ''));
    $formData['TenDangNhap'] = trim((string)($_POST['TenDangNhap'] ?? ''));
    $formData['MatKhau']     = (string)($_POST['MatKhau'] ?? '');
    $formData['VaiTro']      = $isSelf ? 'Admin' : trim((string)($_POST['VaiTro'] ?? 'NhanVien'));
    $formData['SoDienThoai'] = trim((string)($_POST['SoDienThoai'] ?? ''));
    $formData['Email']       = trim((string)($_POST['Email'] ?? ''));
    $formData['TrangThai']   = $isSelf ? 'Đang làm việc' : trim((string)($_POST['TrangThai'] ?? 'Đang làm việc'));

    // 1. Kiểm tra Họ tên
    if ($formData['HoTen'] === '') {
        $errors['HoTen'] = 'Họ và tên không được để trống.';
    } elseif (mb_strlen($formData['HoTen']) < 2) {
        $errors['HoTen'] = 'Họ tên phải có ít nhất 2 ký tự.';
    }

    // 2. Kiểm tra Tên đăng nhập
    if ($formData['TenDangNhap'] === '') {
        $errors['TenDangNhap'] = 'Tên đăng nhập không được để trống.';
    } elseif (strlen($formData['TenDangNhap']) < 3) {
        $errors['TenDangNhap'] = 'Tên đăng nhập phải có ít nhất 3 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $formData['TenDangNhap'])) {
        $errors['TenDangNhap'] = 'Tên đăng nhập chỉ chứa chữ cái, chữ số và các ký tự ., _, -';
    } else {
        // Kiểm tra trùng lặp Tên đăng nhập (trừ chính tài khoản này)
        $checkUser = $pdo->prepare('SELECT COUNT(*) FROM NhanVien WHERE TenDangNhap = ? AND MaNV <> ?');
        $checkUser->execute([$formData['TenDangNhap'], $id]);
        if ((int)$checkUser->fetchColumn() > 0) {
            $errors['TenDangNhap'] = 'Tên đăng nhập "' . $formData['TenDangNhap'] . '" đã được sử dụng. Vui lòng chọn tên khác.';
        }
    }

    // 3. Kiểm tra Mật khẩu (nếu có nhập)
    if ($formData['MatKhau'] !== '' && strlen($formData['MatKhau']) < 6) {
        $errors['MatKhau'] = 'Mật khẩu mới phải có độ dài tối thiểu 6 ký tự.';
    }

    // 4. Kiểm tra Vai trò
    if (!in_array($formData['VaiTro'], ['Admin', 'NhanVien'], true)) {
        $errors['VaiTro'] = 'Vai trò không hợp lệ.';
    }

    // 5. Kiểm tra Số điện thoại
    if ($formData['SoDienThoai'] !== '') {
        if (!preg_match('/^[0-9]{9,11}$/', $formData['SoDienThoai'])) {
            $errors['SoDienThoai'] = 'Số điện thoại không đúng định dạng (9 đến 11 chữ số).';
        }
    }

    // 6. Kiểm tra Email
    if ($formData['Email'] !== '') {
        if (!filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors['Email'] = 'Địa chỉ Email không đúng định dạng.';
        } else {
            // Kiểm tra trùng Email (trừ chính tài khoản này)
            $checkEmail = $pdo->prepare('SELECT COUNT(*) FROM NhanVien WHERE Email = ? AND MaNV <> ?');
            $checkEmail->execute([$formData['Email'], $id]);
            if ((int)$checkEmail->fetchColumn() > 0) {
                $errors['Email'] = 'Email này đã được sử dụng cho một tài khoản khác.';
            }
        }
    }

    // 7. Kiểm tra Trạng thái (Self-Protection)
    if ($isSelf && $formData['TrangThai'] === 'Nghỉ việc') {
        $errors['TrangThai'] = 'Bạn không thể tự chuyển tài khoản của chính mình sang "Nghỉ việc".';
    }

    // Xử lý upload Avatar
    $newAvatarPath = $employee['Avatar'] ?? null;
    if (!empty($_POST['remove_avatar'])) {
        if (!empty($employee['Avatar']) && file_exists(__DIR__ . '/../../' . $employee['Avatar'])) {
            @unlink(__DIR__ . '/../../' . $employee['Avatar']);
        }
        $newAvatarPath = null;
    }

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if ($file['size'] > $maxSize) {
            $errors['avatar'] = 'Kích thước ảnh đại diện không được vượt quá 5MB.';
        } else {
            $imgInfo = @getimagesize($file['tmp_name']);
            $mimeType = $imgInfo['mime'] ?? '';

            if (!$imgInfo || !isset($allowedTypes[$mimeType])) {
                $errors['avatar'] = 'Định dạng ảnh không hợp lệ. Chỉ chấp nhận JPG, PNG, WEBP, GIF.';
            } else {
                $ext = $allowedTypes[$mimeType];
                $uploadDir = __DIR__ . '/../../uploads/avatars';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                $fileName = 'avatar_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetFile = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    if (!empty($employee['Avatar']) && file_exists(__DIR__ . '/../../' . $employee['Avatar'])) {
                        @unlink(__DIR__ . '/../../' . $employee['Avatar']);
                    }
                    $newAvatarPath = 'uploads/avatars/' . $fileName;
                } else {
                    $errors['avatar'] = 'Không thể lưu file ảnh lên máy chủ.';
                }
            }
        }
    }

    // Nếu hợp lệ -> Cập nhật CSDL
    if (empty($errors)) {
        try {
            if ($formData['MatKhau'] !== '') {
                $hashed = password_hash($formData['MatKhau'], PASSWORD_DEFAULT);
                $updateSql = '
                    UPDATE NhanVien 
                    SET HoTen = :hoTen, TenDangNhap = :tenDangNhap, MatKhau = :matKhau, 
                        VaiTro = :vaiTro, SoDienThoai = :sdt, Email = :email, TrangThai = :trangThai, Avatar = :avatar
                    WHERE MaNV = :id
                ';
                $params = [
                    ':hoTen'       => $formData['HoTen'],
                    ':tenDangNhap' => $formData['TenDangNhap'],
                    ':matKhau'     => $hashed,
                    ':vaiTro'      => $formData['VaiTro'],
                    ':sdt'         => ($formData['SoDienThoai'] !== '') ? $formData['SoDienThoai'] : null,
                    ':email'       => ($formData['Email'] !== '') ? $formData['Email'] : null,
                    ':trangThai'   => $formData['TrangThai'],
                    ':avatar'      => $newAvatarPath,
                    ':id'          => $id,
                ];
            } else {
                $updateSql = '
                    UPDATE NhanVien 
                    SET HoTen = :hoTen, TenDangNhap = :tenDangNhap, 
                        VaiTro = :vaiTro, SoDienThoai = :sdt, Email = :email, TrangThai = :trangThai, Avatar = :avatar
                    WHERE MaNV = :id
                ';
                $params = [
                    ':hoTen'       => $formData['HoTen'],
                    ':tenDangNhap' => $formData['TenDangNhap'],
                    ':vaiTro'      => $formData['VaiTro'],
                    ':sdt'         => ($formData['SoDienThoai'] !== '') ? $formData['SoDienThoai'] : null,
                    ':email'       => ($formData['Email'] !== '') ? $formData['Email'] : null,
                    ':trangThai'   => $formData['TrangThai'],
                    ':avatar'      => $newAvatarPath,
                    ':id'          => $id,
                ];
            }

            $pdo->beginTransaction();
            $stmtUpdate = $pdo->prepare($updateSql);
            $stmtUpdate->execute($params);

            // Cập nhật tòa nhà phân công
            $stmtDelBld = $pdo->prepare('DELETE FROM nhanvien_toanha WHERE MaNV = ?');
            $stmtDelBld->execute([$id]);

            if ($formData['VaiTro'] === 'NhanVien') {
                $selectedBuildings = $_POST['buildings'] ?? [];
                if (is_array($selectedBuildings) && !empty($selectedBuildings)) {
                    $stmtInsBld = $pdo->prepare('INSERT INTO nhanvien_toanha (MaNV, DiaChi) VALUES (?, ?)');
                    foreach ($selectedBuildings as $bld) {
                        $bld = trim((string)$bld);
                        if ($bld !== '') {
                            $stmtInsBld->execute([$id, $bld]);
                        }
                    }
                }
            }

            $pdo->commit();
            refreshStaffBuildingSession();

            // Cập nhật lại session nếu tự sửa họ tên hoặc avatar chính mình
            if ($isSelf) {
                $_SESSION['HoTen'] = $formData['HoTen'];
                $_SESSION['TenDangNhap'] = $formData['TenDangNhap'];
                $_SESSION['Avatar'] = $newAvatarPath;
            }

            setFlash('success', 'Cập nhật thông tin nhân viên "' . $formData['HoTen'] . '" thành công!');
            redirect('/admin/nhan-vien/index.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}

// Lấy danh sách tòa nhà nhân viên hiện đang được phân công
$stmtBldCurrent = $pdo->prepare('SELECT DiaChi FROM nhanvien_toanha WHERE MaNV = ?');
$stmtBldCurrent->execute([$id]);
$currentAssignedBuildings = $stmtBldCurrent->fetchAll(PDO::FETCH_COLUMN) ?: [];

$selectedBuildings = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? ($_POST['buildings'] ?? [])
    : $currentAssignedBuildings;
if (!is_array($selectedBuildings)) {
    $selectedBuildings = [];
}

// Lấy danh sách tất cả các tòa nhà kèm số lượng phòng
$allBuildingsWithCount = $pdo->query('
    SELECT DiaChi, COUNT(*) as SoLuongPhong 
    FROM CanHo 
    WHERE DiaChi IS NOT NULL AND TRIM(DiaChi) <> "" 
    GROUP BY DiaChi 
    ORDER BY DiaChi ASC
')->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sửa Nhân Viên #<?= (int)$employee['MaNV'] ?>: <?= e($employee['HoTen']) ?></h1>
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
<?php elseif (!empty($errors)): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div>Vui lòng kiểm tra lại các trường thông tin có báo lỗi màu đỏ bên dưới.</div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.05rem; font-weight: 600;">
            Chỉnh Sửa Thông Tin
            <?php if ($isSelf): ?>
                <span style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; background-color: #dbeafe; color: #1d4ed8; margin-left: 6px;">(Tài khoản của bạn)</span>
            <?php endif; ?>
        </h3>
        <span style="font-size: 0.85rem; color: var(--text-muted);">Mã: <strong>#<?= (int)$employee['MaNV'] ?></strong></span>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                <!-- Upload Avatar Hiện Đại -->
                <div class="form-group" style="grid-column: span 2; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1.25rem; flex-wrap: wrap;">
                        <!-- Trái: Khung ảnh đại diện và thông tin -->
                        <div style="display: flex; align-items: center; gap: 1.25rem;">
                            <div style="position: relative; flex-shrink: 0;">
                                <div id="empAvatarWrapper" style="width: 76px; height: 76px; border-radius: 50%; overflow: hidden; border: 3px solid #ffffff; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); background: #e2e8f0; transition: all 0.25s ease;">
                                    <img id="empAvatarPreview" src="<?= !empty($employee['Avatar']) ? e(url($employee['Avatar'])) : '' ?>" 
                                         alt="Avatar" 
                                         style="width: 100%; height: 100%; object-fit: cover; <?= empty($employee['Avatar']) ? 'display: none;' : '' ?>">
                                    <div id="empAvatarPlaceholder" style="width: 100%; height: 100%; display: <?= !empty($employee['Avatar']) ? 'none' : 'flex' ?>; align-items: center; justify-content: center; background: <?= ($employee['VaiTro'] === 'Admin') ? 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)' : 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)' ?>; color: #ffffff; font-size: 1.5rem; font-weight: 700;">
                                        <?= e(getInitials($employee['HoTen'])) ?>
                                    </div>
                                </div>
                                <label for="empAvatarInput" title="Tải ảnh mới" style="position: absolute; bottom: -2px; right: -2px; width: 26px; height: 26px; border-radius: 50%; background: #0f172a; color: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: transform 0.15s ease;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                    <?= svgIcon('camera', '', 13) ?>
                                </label>
                            </div>

                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.2rem;">
                                    Ảnh Đại Diện Nhân Sự
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                    Hỗ trợ định dạng JPG, PNG hoặc WEBP (tối đa 5MB).
                                </div>
                                <?php if (isset($errors['avatar'])): ?>
                                    <small style="color: var(--danger-color); font-weight: 600; margin-top: 0.35rem; display: block;"><?= e($errors['avatar']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Phải: Nút bấm thao tác hiện đại -->
                        <div>
                            <label for="empAvatarInput" style="margin: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.55rem 1.1rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #0f172a; background: #ffffff; border: 1px solid #cbd5e1; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04);" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                                <?= svgIcon('upload', '', 15) ?>
                                <span>Tải ảnh mới</span>
                            </label>
                            <input type="file" id="empAvatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" style="display: none;" onchange="handleEmpAvatarFileSelect(this)">
                        </div>
                    </div>
                </div>

                <!-- Họ tên -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="HoTen" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Họ và tên <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="HoTen" 
                           name="HoTen" 
                           class="form-control" 
                           style="<?= isset($errors['HoTen']) ? 'border-color: var(--danger-color); background-color: #fef2f2;' : '' ?>"
                           placeholder="Ví dụ: Nguyễn Văn An" 
                           value="<?= e($formData['HoTen']) ?>" 
                           required>
                    <?php if (isset($errors['HoTen'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['HoTen']) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Tên đăng nhập -->
                <div class="form-group">
                    <label for="TenDangNhap" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Tên đăng nhập <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="TenDangNhap" 
                           name="TenDangNhap" 
                           class="form-control" 
                           style="<?= isset($errors['TenDangNhap']) ? 'border-color: var(--danger-color); background-color: #fef2f2;' : '' ?>"
                           value="<?= e($formData['TenDangNhap']) ?>" 
                           required>
                    <?php if (isset($errors['TenDangNhap'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['TenDangNhap']) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Mật khẩu -->
                <div class="form-group">
                    <label for="MatKhau" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Đổi mật khẩu mới <span style="font-weight: 400; color: var(--text-muted);">(Để trống nếu giữ nguyên)</span>
                    </label>
                    <input type="password" 
                           id="MatKhau" 
                           name="MatKhau" 
                           class="form-control" 
                           style="<?= isset($errors['MatKhau']) ? 'border-color: var(--danger-color); background-color: #fef2f2;' : '' ?>"
                           placeholder="Nhập mật khẩu mới nếu muốn đổi">
                    <?php if (isset($errors['MatKhau'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['MatKhau']) ?>
                        </small>
                    <?php else: ?>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            Chỉ nhập khi cần đặt lại mật khẩu cho nhân viên
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Vai trò -->
                <div class="form-group">
                    <label for="VaiTro" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Vai trò hệ thống <span class="required">*</span>
                    </label>
                    <?php if ($isSelf): ?>
                        <input type="text" class="form-control" value="Chủ Nhà" disabled style="background-color: #f1f5f9; cursor: not-allowed;">
                        <input type="hidden" name="VaiTro" value="Admin">
                        <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            Bạn đang đăng nhập bằng tài khoản này, không thể tự đổi vai trò.
                        </small>
                    <?php else: ?>
                        <select id="VaiTro" name="VaiTro" class="form-control">
                            <option value="NhanVien" <?= ($formData['VaiTro'] === 'NhanVien') ? 'selected' : '' ?>>
                                Nhân Viên
                            </option>
                            <option value="Admin" <?= ($formData['VaiTro'] === 'Admin') ? 'selected' : '' ?>>
                                Chủ Nhà
                            </option>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Trạng thái -->
                <div class="form-group">
                    <label for="TrangThai" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Trạng thái hoạt động <span class="required">*</span>
                    </label>
                    <?php if ($isSelf): ?>
                        <input type="text" class="form-control" value="Đang làm việc" disabled style="background-color: #f1f5f9; cursor: not-allowed;">
                        <input type="hidden" name="TrangThai" value="Đang làm việc">
                        <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            Bạn không thể tự khóa tài khoản của chính mình.
                        </small>
                    <?php else: ?>
                        <select id="TrangThai" name="TrangThai" class="form-control" style="<?= isset($errors['TrangThai']) ? 'border-color: var(--danger-color);' : '' ?>">
                            <option value="Đang làm việc" <?= ($formData['TrangThai'] === 'Đang làm việc') ? 'selected' : '' ?>>
                                Đang làm việc (Cho phép đăng nhập)
                            </option>
                            <option value="Nghỉ việc" <?= ($formData['TrangThai'] === 'Nghỉ việc') ? 'selected' : '' ?>>
                                Nghỉ việc (Khóa tài khoản, cấm đăng nhập)
                            </option>
                        </select>
                        <?php if (isset($errors['TrangThai'])): ?>
                            <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                                <?= e($errors['TrangThai']) ?>
                            </small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Số điện thoại -->
                <div class="form-group">
                    <label for="SoDienThoai" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Số điện thoại
                    </label>
                    <input type="text" 
                           id="SoDienThoai" 
                           name="SoDienThoai" 
                           class="form-control" 
                           style="<?= isset($errors['SoDienThoai']) ? 'border-color: var(--danger-color); background-color: #fef2f2;' : '' ?>"
                           placeholder="Ví dụ: 0912345678" 
                           value="<?= e($formData['SoDienThoai']) ?>">
                    <?php if (isset($errors['SoDienThoai'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['SoDienThoai']) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="Email" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Địa chỉ Email
                    </label>
                    <input type="email" 
                           id="Email" 
                           name="Email" 
                           class="form-control" 
                           style="<?= isset($errors['Email']) ? 'border-color: var(--danger-color); background-color: #fef2f2;' : '' ?>"
                           placeholder="Ví dụ: nhanvien@gmail.com" 
                           value="<?= e($formData['Email']) ?>">
                    <?php if (isset($errors['Email'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['Email']) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Chỉ định Tòa nhà quản lý (Chỉ áp dụng cho Nhân viên) -->
                <div id="building_assignment_section" style="grid-column: span 2; margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px dashed #cbd5e1; <?= ($formData['VaiTro'] === 'Admin') ? 'display: none;' : '' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <label style="font-weight: 700; color: #1e293b; font-size: 0.95rem; margin: 0; display: flex; align-items: center; gap: 6px;">
                                <span style="color: #2563eb; display: flex;"><?= svgIcon('building', '', 17) ?></span>
                                <span>Chỉ định Tòa nhà / Căn hộ quản lý</span>
                            </label>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                                Nhân viên này chỉ có quyền xem và xử lý các phòng, khách thuê, hợp đồng thuộc tòa nhà được chọn.
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.65rem; font-size: 0.78rem; font-weight: 600;" onclick="toggleAllBuildings(true)">Chọn tất cả</button>
                            <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.65rem; font-size: 0.78rem; font-weight: 600;" onclick="toggleAllBuildings(false)">Bỏ chọn</button>
                        </div>
                    </div>

                    <?php if (empty($allBuildingsWithCount)): ?>
                        <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem;">
                            Chưa có dữ liệu căn hộ / tòa nhà nào trong hệ thống.
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 0.75rem;">
                            <?php foreach ($allBuildingsWithCount as $bld): ?>
                                <?php $isChecked = in_array($bld['DiaChi'], $selectedBuildings, true); ?>
                                <label style="display: flex; align-items: flex-start; gap: 10px; padding: 0.85rem 1rem; border: 1px solid <?= $isChecked ? '#3b82f6' : '#e2e8f0' ?>; background: <?= $isChecked ? '#eff6ff' : '#ffffff' ?>; border-radius: 8px; cursor: pointer; transition: all 0.15s ease;" class="building-card-label">
                                    <input type="checkbox" name="buildings[]" value="<?= e($bld['DiaChi']) ?>" <?= $isChecked ? 'checked' : '' ?> class="building-checkbox" style="margin-top: 3px; accent-color: #2563eb; width: 16px; height: 16px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; font-size: 0.88rem; color: #1e293b; line-height: 1.35;">
                                            <?= e($bld['DiaChi']) ?>
                                        </div>
                                        <div style="font-size: 0.78rem; color: #64748b; margin-top: 3px; display: flex; align-items: center; gap: 4px;">
                                            <span style="font-weight: 700; color: #2563eb;"><?= (int)$bld['SoLuongPhong'] ?></span> phòng trực thuộc
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nút bấm thao tác -->
            <div style="margin-top: 2rem; display: flex; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600;">
                    Cập nhật nhân viên
                </button>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="padding: 0.65rem 1.5rem;">
                    Hủy bỏ
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('VaiTro')?.addEventListener('change', function() {
    const section = document.getElementById('building_assignment_section');
    if (section) {
        section.style.display = (this.value === 'Admin') ? 'none' : 'block';
    }
});
function toggleAllBuildings(checked) {
    document.querySelectorAll('.building-checkbox').forEach(cb => {
        cb.checked = checked;
        const card = cb.closest('.building-card-label');
        if (card) {
            card.style.borderColor = checked ? '#3b82f6' : '#e2e8f0';
            card.style.background = checked ? '#eff6ff' : '#ffffff';
        }
    });
}
document.querySelectorAll('.building-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const card = this.closest('.building-card-label');
        if (card) {
            card.style.borderColor = this.checked ? '#3b82f6' : '#e2e8f0';
            card.style.background = this.checked ? '#eff6ff' : '#ffffff';
        }
    });
});

function handleEmpAvatarFileSelect(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('empAvatarPreview');
            const placeholder = document.getElementById('empAvatarPlaceholder');
            const wrapper = document.getElementById('empAvatarWrapper');

            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
                img.style.opacity = '1';
                img.style.filter = 'none';
            }
            if (placeholder) placeholder.style.display = 'none';
            if (wrapper) wrapper.style.borderColor = '#0284c7';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
