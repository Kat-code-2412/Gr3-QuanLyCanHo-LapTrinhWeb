<?php

declare(strict_types=1);

$title = 'Thêm Nhân Viên Mới';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url('/admin/nhan-vien');

$errors = [];
$formData = [
    'HoTen'       => '',
    'TenDangNhap' => '',
    'MatKhau'     => '',
    'VaiTro'      => 'NhanVien',
    'SoDienThoai' => '',
    'Email'       => '',
    'TrangThai'   => 'Đang làm việc',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $formData['HoTen']       = trim((string)($_POST['HoTen'] ?? ''));
    $formData['TenDangNhap'] = trim((string)($_POST['TenDangNhap'] ?? ''));
    $formData['MatKhau']     = (string)($_POST['MatKhau'] ?? '');
    $formData['VaiTro']      = trim((string)($_POST['VaiTro'] ?? 'NhanVien'));
    $formData['SoDienThoai'] = trim((string)($_POST['SoDienThoai'] ?? ''));
    $formData['Email']       = trim((string)($_POST['Email'] ?? ''));
    $formData['TrangThai']   = trim((string)($_POST['TrangThai'] ?? 'Đang làm việc'));

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
        // Kiểm tra trùng lặp Tên đăng nhập
        $checkUser = $pdo->prepare('SELECT COUNT(*) FROM NhanVien WHERE TenDangNhap = ?');
        $checkUser->execute([$formData['TenDangNhap']]);
        if ((int)$checkUser->fetchColumn() > 0) {
            $errors['TenDangNhap'] = 'Tên đăng nhập "' . $formData['TenDangNhap'] . '" đã được sử dụng. Vui lòng chọn tên khác.';
        }
    }

    // 3. Kiểm tra Mật khẩu
    if ($formData['MatKhau'] === '') {
        $errors['MatKhau'] = 'Mật khẩu không được để trống.';
    } elseif (strlen($formData['MatKhau']) < 6) {
        $errors['MatKhau'] = 'Mật khẩu phải có độ dài tối thiểu 6 ký tự.';
    }

    // 4. Kiểm tra Vai trò
    if (!in_array($formData['VaiTro'], ['Admin', 'NhanVien'], true)) {
        $errors['VaiTro'] = 'Vai trò không hợp lệ (chỉ chấp nhận Admin hoặc NhanVien).';
    }

    // 5. Kiểm tra Số điện thoại (nếu có nhập)
    if ($formData['SoDienThoai'] !== '') {
        if (!preg_match('/^[0-9]{9,11}$/', $formData['SoDienThoai'])) {
            $errors['SoDienThoai'] = 'Số điện thoại không đúng định dạng (9 đến 11 chữ số).';
        }
    }

    // 6. Kiểm tra Email (nếu có nhập)
    if ($formData['Email'] !== '') {
        if (!filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors['Email'] = 'Địa chỉ Email không đúng định dạng.';
        } else {
            // Kiểm tra trùng Email
            $checkEmail = $pdo->prepare('SELECT COUNT(*) FROM NhanVien WHERE Email = ?');
            $checkEmail->execute([$formData['Email']]);
            if ((int)$checkEmail->fetchColumn() > 0) {
                $errors['Email'] = 'Email này đã được sử dụng cho một tài khoản khác.';
            }
        }
    }

    // 7. Kiểm tra Trạng thái
    if (!in_array($formData['TrangThai'], ['Đang làm việc', 'Nghỉ việc'], true)) {
        $formData['TrangThai'] = 'Đang làm việc';
    }

    // Xử lý upload Avatar
    $avatarPath = null;
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

                $fileName = 'avatar_new_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetFile = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $avatarPath = 'uploads/avatars/' . $fileName;
                } else {
                    $errors['avatar'] = 'Không thể lưu file ảnh lên máy chủ.';
                }
            }
        }
    }

    // Nếu hợp lệ -> Thêm vào CSDL
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $hashedPassword = password_hash($formData['MatKhau'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO NhanVien (HoTen, TenDangNhap, MatKhau, VaiTro, SoDienThoai, Email, TrangThai, Avatar)
                VALUES (:hoTen, :tenDangNhap, :matKhau, :vaiTro, :sdt, :email, :trangThai, :avatar)
            ');
            $stmt->execute([
                ':hoTen'       => $formData['HoTen'],
                ':tenDangNhap' => $formData['TenDangNhap'],
                ':matKhau'     => $hashedPassword,
                ':vaiTro'      => $formData['VaiTro'],
                ':sdt'         => ($formData['SoDienThoai'] !== '') ? $formData['SoDienThoai'] : null,
                ':email'       => ($formData['Email'] !== '') ? $formData['Email'] : null,
                ':trangThai'   => $formData['TrangThai'],
                ':avatar'      => $avatarPath,
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Nếu là Nhân viên, lưu các tòa nhà được phân công
            if ($formData['VaiTro'] === 'NhanVien') {
                $selectedBuildings = $_POST['buildings'] ?? [];
                if (is_array($selectedBuildings) && !empty($selectedBuildings)) {
                    $stmtInsBld = $pdo->prepare('INSERT INTO nhanvien_toanha (MaNV, DiaChi) VALUES (?, ?)');
                    foreach ($selectedBuildings as $bld) {
                        $bld = trim((string)$bld);
                        if ($bld !== '') {
                            $stmtInsBld->execute([$newId, $bld]);
                        }
                    }
                }
            }

            $pdo->commit();
            refreshStaffBuildingSession();

            setFlash('success', 'Thêm mới nhân viên "' . $formData['HoTen'] . '" thành công! Mật khẩu đã được mã hóa Bcrypt.');
            redirect('/admin/nhan-vien/index.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}

// Lấy danh sách tất cả các tòa nhà kèm số lượng phòng
$allBuildingsWithCount = $pdo->query('
    SELECT DiaChi, COUNT(*) as SoLuongPhong 
    FROM CanHo 
    WHERE DiaChi IS NOT NULL AND TRIM(DiaChi) <> "" 
    GROUP BY DiaChi 
    ORDER BY DiaChi ASC
')->fetchAll();
$selectedBuildings = $_POST['buildings'] ?? [];
if (!is_array($selectedBuildings)) {
    $selectedBuildings = [];
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Thêm Nhân Viên Mới</h1>
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
        <div>Vui lòng kiểm tra lại các trường dữ liệu có thông báo lỗi màu đỏ bên dưới.</div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3 style="font-size: 1.05rem; font-weight: 600;">Thông Tin Nhân Viên</h3>
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
                                <div id="createAvatarWrapper" style="width: 76px; height: 76px; border-radius: 50%; overflow: hidden; border: 3px solid #ffffff; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); background: #e2e8f0; transition: all 0.25s ease;">
                                    <img id="createAvatarPreview" src="" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                    <div id="createAvatarPlaceholder" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; font-size: 1.5rem; font-weight: 700;">
                                        <?= svgIcon('user', '', 28) ?>
                                    </div>
                                </div>
                                <label for="createAvatarInput" title="Tải ảnh mới" style="position: absolute; bottom: -2px; right: -2px; width: 26px; height: 26px; border-radius: 50%; background: #0f172a; color: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: transform 0.15s ease;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                    <?= svgIcon('camera', '', 13) ?>
                                </label>
                            </div>

                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.2rem;">
                                    Ảnh Đại Diện Nhân Sự
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                    Tùy chọn tải ảnh ngay khi tạo tài khoản (JPG, PNG, WEBP, tối đa 5MB).
                                </div>
                                <div id="createAvatarStatusText" style="font-size: 0.8rem; font-weight: 600; margin-top: 0.35rem; display: none;"></div>
                                <?php if (isset($errors['avatar'])): ?>
                                    <small style="color: var(--danger-color); font-weight: 600; margin-top: 0.35rem; display: block;"><?= e($errors['avatar']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Phải: Nút bấm thao tác hiện đại -->
                        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                            <label for="createAvatarInput" style="margin: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; color: #0f172a; background: #ffffff; border: 1px solid #cbd5e1; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04);" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                                <?= svgIcon('upload', '', 15) ?>
                                <span>Tải ảnh đại diện</span>
                            </label>
                            <input type="file" id="createAvatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" style="display: none;" onchange="previewCreateAvatar(this)">
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
                           placeholder="Ví dụ: nvan_staff" 
                           value="<?= e($formData['TenDangNhap']) ?>" 
                           required>
                    <?php if (isset($errors['TenDangNhap'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['TenDangNhap']) ?>
                        </small>
                    <?php else: ?>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            Tối thiểu 3 ký tự (chữ cái, số, ., _, -)
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Mật khẩu -->
                <div class="form-group">
                    <label for="MatKhau" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Mật khẩu khởi tạo <span class="required">*</span>
                    </label>
                    <input type="password" 
                           id="MatKhau" 
                           name="MatKhau" 
                           class="form-control" 
                           style="<?= isset($errors['MatKhau']) ? 'border-color: var(--danger-color); background-color: #fef2f2;' : '' ?>"
                           placeholder="Nhập mật khẩu (tối thiểu 6 ký tự)" 
                           required>
                    <?php if (isset($errors['MatKhau'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['MatKhau']) ?>
                        </small>
                    <?php else: ?>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            Mật khẩu tự động băm an toàn chuẩn Bcrypt khi lưu
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Vai trò -->
                <div class="form-group">
                    <label for="VaiTro" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Vai trò hệ thống <span class="required">*</span>
                    </label>
                    <select id="VaiTro" name="VaiTro" class="form-control" style="<?= isset($errors['VaiTro']) ? 'border-color: var(--danger-color);' : '' ?>">
                        <option value="NhanVien" <?= ($formData['VaiTro'] === 'NhanVien') ? 'selected' : '' ?>>
                            Nhân Viên
                        </option>
                        <option value="Admin" <?= ($formData['VaiTro'] === 'Admin') ? 'selected' : '' ?>>
                            Chủ Nhà
                        </option>
                    </select>
                    <?php if (isset($errors['VaiTro'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; display: block; margin-top: 0.25rem;">
                            <?= e($errors['VaiTro']) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Trạng thái -->
                <div class="form-group">
                    <label for="TrangThai" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                        Trạng thái hoạt động <span class="required">*</span>
                    </label>
                    <select id="TrangThai" name="TrangThai" class="form-control">
                        <option value="Đang làm việc" <?= ($formData['TrangThai'] === 'Đang làm việc') ? 'selected' : '' ?>>
                            Đang làm việc (Cho phép đăng nhập)
                        </option>
                        <option value="Nghỉ việc" <?= ($formData['TrangThai'] === 'Nghỉ việc') ? 'selected' : '' ?>>
                            Nghỉ việc (Khóa tài khoản, cấm đăng nhập)
                        </option>
                    </select>
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
                    Lưu nhân viên
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

function previewCreateAvatar(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('createAvatarPreview');
            const placeholder = document.getElementById('createAvatarPlaceholder');
            const wrapper = document.getElementById('createAvatarWrapper');
            const status = document.getElementById('createAvatarStatusText');

            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            if (placeholder) placeholder.style.display = 'none';
            if (wrapper) wrapper.style.borderColor = '#38bdf8';

            if (status) {
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                status.style.display = 'block';
                status.style.color = '#0284c7';
                status.innerHTML = '✓ Đã chọn ảnh: <strong>' + file.name + '</strong> (' + sizeMb + ' MB)';
            }
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
