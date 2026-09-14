<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../config/database.php';
$maNv = (int)($_SESSION['MaNV'] ?? 0);

if ($maNv <= 0) {
    setFlash('error', 'Phiên đăng nhập không hợp lệ.');
    redirect('/auth/login.php');
}

// Lấy thông tin tài khoản hiện tại từ database
$stmt = $pdo->prepare('SELECT * FROM NhanVien WHERE MaNV = ?');
$stmt->execute([$maNv]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'Không tìm thấy thông tin tài khoản trong hệ thống.');
    redirect('/auth/logout.php');
}

$isAdmin = ($user['VaiTro'] === 'Admin');

$errors = [];
$formData = [
    'HoTen'       => $user['HoTen'] ?? '',
    'TenDangNhap' => $user['TenDangNhap'] ?? '',
    'SoDienThoai' => $user['SoDienThoai'] ?? '',
    'Email'       => $user['Email'] ?? '',
    'VaiTro'      => $user['VaiTro'] ?? 'NhanVien',
    'TrangThai'   => $user['TrangThai'] ?? 'Đang làm việc',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();

    $formData['HoTen']       = trim((string)($_POST['HoTen'] ?? ''));
    $formData['TenDangNhap'] = trim((string)($_POST['TenDangNhap'] ?? ''));
    $formData['SoDienThoai'] = trim((string)($_POST['SoDienThoai'] ?? ''));
    $formData['Email']       = trim((string)($_POST['Email'] ?? ''));

    // Chỉ Admin mới được thay đổi VaiTro và TrangThai
    if ($isAdmin) {
        $postVaiTro = trim((string)($_POST['VaiTro'] ?? 'Admin'));
        if (in_array($postVaiTro, ['Admin', 'NhanVien'], true)) {
            $formData['VaiTro'] = $postVaiTro;
        }
        $postTrangThai = trim((string)($_POST['TrangThai'] ?? 'Đang làm việc'));
        if (in_array($postTrangThai, ['Đang làm việc', 'Nghỉ việc'], true)) {
            $formData['TrangThai'] = $postTrangThai;
        }
    }

    // 1. Kiểm tra Họ và tên
    if ($formData['HoTen'] === '') {
        $errors['HoTen'] = 'Họ và tên không được để trống.';
    } elseif (mb_strlen($formData['HoTen']) < 2) {
        $errors['HoTen'] = 'Họ và tên phải có ít nhất 2 ký tự.';
    }

    // 2. Kiểm tra Tên đăng nhập
    if ($formData['TenDangNhap'] === '') {
        $errors['TenDangNhap'] = 'Tên đăng nhập không được để trống.';
    } elseif (strlen($formData['TenDangNhap']) < 3) {
        $errors['TenDangNhap'] = 'Tên đăng nhập phải có ít nhất 3 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $formData['TenDangNhap'])) {
        $errors['TenDangNhap'] = 'Tên đăng nhập chỉ được chứa chữ cái không dấu, chữ số và các ký tự ., _, -';
    } else {
        // Kiểm tra trùng lặp Tên đăng nhập
        $checkUser = $pdo->prepare('SELECT COUNT(*) FROM NhanVien WHERE TenDangNhap = ? AND MaNV <> ?');
        $checkUser->execute([$formData['TenDangNhap'], $maNv]);
        if ((int)$checkUser->fetchColumn() > 0) {
            $errors['TenDangNhap'] = 'Tên đăng nhập "' . $formData['TenDangNhap'] . '" đã có người sử dụng. Vui lòng chọn tên khác.';
        }
    }

    // 3. Kiểm tra Số điện thoại
    if ($formData['SoDienThoai'] !== '') {
        if (!preg_match('/^[0-9]{9,11}$/', $formData['SoDienThoai'])) {
            $errors['SoDienThoai'] = 'Số điện thoại không đúng định dạng (9 đến 11 chữ số).';
        }
    }

    // 4. Kiểm tra Email
    if ($formData['Email'] !== '') {
        if (!filter_var($formData['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors['Email'] = 'Địa chỉ Email không đúng định dạng.';
        } else {
            // Kiểm tra trùng Email
            $checkEmail = $pdo->prepare('SELECT COUNT(*) FROM NhanVien WHERE Email = ? AND MaNV <> ?');
            $checkEmail->execute([$formData['Email'], $maNv]);
            if ((int)$checkEmail->fetchColumn() > 0) {
                $errors['Email'] = 'Email này đã được sử dụng cho một tài khoản khác.';
            }
        }
    }

    // Xử lý Upload Avatar
    $newAvatarPath = $user['Avatar'] ?? null;
    if (!empty($_POST['remove_avatar'])) {
        if (!empty($user['Avatar']) && file_exists(__DIR__ . '/../' . $user['Avatar'])) {
            @unlink(__DIR__ . '/../' . $user['Avatar']);
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
                $uploadDir = __DIR__ . '/../uploads/avatars';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                $fileName = 'avatar_' . $maNv . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetFile = $uploadDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    // Xóa ảnh cũ nếu có
                    if (!empty($user['Avatar']) && file_exists(__DIR__ . '/../' . $user['Avatar'])) {
                        @unlink(__DIR__ . '/../' . $user['Avatar']);
                    }
                    $newAvatarPath = 'uploads/avatars/' . $fileName;
                } else {
                    $errors['avatar'] = 'Không thể lưu file ảnh vào máy chủ.';
                }
            }
        }
    }

    // Nếu không có lỗi -> cập nhật cơ sở dữ liệu
    if (empty($errors)) {
        try {
            $updateSql = '
                UPDATE NhanVien 
                SET HoTen = :hoTen, 
                    TenDangNhap = :tenDangNhap, 
                    SoDienThoai = :sdt, 
                    Email = :email,
                    VaiTro = :vaiTro,
                    TrangThai = :trangThai,
                    Avatar = :avatar
                WHERE MaNV = :id
            ';
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':hoTen'       => $formData['HoTen'],
                ':tenDangNhap' => $formData['TenDangNhap'],
                ':sdt'         => ($formData['SoDienThoai'] !== '') ? $formData['SoDienThoai'] : null,
                ':email'       => ($formData['Email'] !== '') ? $formData['Email'] : null,
                ':vaiTro'      => $formData['VaiTro'],
                ':trangThai'   => $formData['TrangThai'],
                ':avatar'      => $newAvatarPath,
                ':id'          => $maNv,
            ]);

            // Cập nhật lại session
            $_SESSION['HoTen'] = $formData['HoTen'];
            $_SESSION['TenDangNhap'] = $formData['TenDangNhap'];
            $_SESSION['Email'] = $formData['Email'];
            $_SESSION['VaiTro'] = $formData['VaiTro'];
            $_SESSION['Avatar'] = $newAvatarPath;

            logAudit('UPDATE_PROFILE', 'NhanVien', (string)$maNv, "Cập nhật thông tin cá nhân & avatar: {$formData['HoTen']} ({$formData['TenDangNhap']})");
            setFlash('success', 'Cập nhật thông tin cá nhân và ảnh đại diện thành công!');
            redirect('/auth/profile.php');
        } catch (PDOException $ex) {
            $errors['general'] = 'Lỗi hệ thống: ' . $ex->getMessage();
        }
    }
}

$title = 'Cài Đặt Thông Tin Cá Nhân';
require_once __DIR__ . '/../includes/header.php';

$avatarInitial = mb_substr($user['HoTen'] ?? 'U', 0, 1, 'UTF-8');
$dashboardUrl = url($isAdmin ? '/admin/index.php' : '/user/index.php');
?>

<div class="page-header" style="margin-bottom: 1.5rem;">
    <div>
        <h1 class="page-title" style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
            Cài Đặt Thông Tin Cá Nhân
        </h1>
        <p class="text-muted" style="margin: 0.35rem 0 0; font-size: 0.875rem; color: #64748b;">
            Quản lý và cập nhật hồ sơ định danh của bạn trên hệ thống.
        </p>
    </div>
    <div>
        <a href="<?= $dashboardUrl ?>" class="btn btn-outline" style="font-weight: 600; border-radius: 8px;">
            <?= svgIcon('arrow-left', '', 15) ?> Quay lại Tổng quan
        </a>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 18) ?></span>
        <div class="alert-text"><?= e($errors['general']) ?></div>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start;">
    <!-- CỘT TRÁI: THẺ TỔNG QUAN TÀI KHOẢN -->
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div class="card" style="padding: 1.5rem; text-align: center; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <!-- AVATAR CIRCLE -->
            <div style="width: 88px; height: 88px; margin: 0 auto 1rem; border-radius: 50%; overflow: hidden; border: 3px solid #ffffff; box-shadow: 0 4px 14px rgba(0,0,0,0.15); background: <?= $isAdmin ? 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)' : 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)' ?>; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800;">
                <?php if (!empty($user['Avatar'])): ?>
                    <img src="<?= e(url($user['Avatar'])) ?>" alt="<?= e($user['HoTen']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?= e($avatarInitial) ?>
                <?php endif; ?>
            </div>

            <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; line-height: 1.3;">
                <?= e($user['HoTen']) ?>
            </h2>
            <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem; font-family: monospace;">
                @<?= e($user['TenDangNhap']) ?>
            </div>

            <div style="margin-top: 0.85rem; display: flex; justify-content: center; gap: 0.4rem; flex-wrap: wrap;">
                <span class="badge" style="<?= ($user['VaiTro'] === 'Admin') ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;' : 'background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;' ?> font-weight: 700; font-size: 0.8rem; padding: 0.3rem 0.75rem; border-radius: 20px;">
                    <?= ($user['VaiTro'] === 'Admin') ? 'Chủ nhà' : 'Nhân Viên' ?>
                </span>
                <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 600; font-size: 0.8rem; padding: 0.3rem 0.75rem; border-radius: 20px;">
                    <?= e($user['TrangThai'] ?? 'Đang làm việc') ?>
                </span>
            </div>

            <hr style="margin: 1.25rem 0; border: none; border-top: 1px solid #f1f5f9;">

            <div style="text-align: left; font-size: 0.825rem; color: #475569; display: flex; flex-direction: column; gap: 0.65rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Mã nhân sự:</span>
                    <strong style="color: #0f172a; font-family: monospace;">#NV-<?= str_pad((string)$user['MaNV'], 4, '0', STR_PAD_LEFT) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Ngày khởi tạo:</span>
                    <span style="color: #0f172a; font-weight: 500;"><?= !empty($user['created_at']) ? formatDate($user['created_at']) : 'Hệ thống ban đầu' ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Lần cập nhật:</span>
                    <span style="color: #0f172a; font-weight: 500;"><?= !empty($user['updated_at']) ? formatDateTime($user['updated_at']) : '-' ?></span>
                </div>
            </div>
        </div>

        <!-- THẺ BẢO MẬT & MẬT KHẨU -->
        <div class="card" style="padding: 1.25rem; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.85rem;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center;">
                    <?= svgIcon('lock', '', 16) ?>
                </div>
                <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.9rem;">Mật Khẩu & Bảo Mật</div>
                    <div style="font-size: 0.75rem; color: #64748b;">Bảo vệ quyền truy cập tài khoản</div>
                </div>
            </div>
            <a href="<?= url('/auth/change-password.php') ?>" class="btn btn-outline" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 600; font-size: 0.85rem; border-radius: 8px; padding: 0.5rem 0.75rem;">
                <?= svgIcon('key', '', 15) ?> Đổi Mật Khẩu Ngay
            </a>
        </div>
    </div>

    <!-- CỘT PHẢI: FORM CHỈNH SỬA THÔNG TIN CÁ NHÂN -->
    <div class="card" style="padding: 0; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden;">
        <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 1.1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 700; font-size: 1rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                <?= svgIcon('edit', '', 16) ?> Chỉnh Sửa Thông Tin Cá Nhân
            </div>
            <span style="font-size: 0.8rem; color: #64748b;">
                <span class="required" style="color: var(--danger-color);">*</span> Bắt buộc nhập
            </span>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" style="padding: 1.5rem;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                <!-- UPLOAD ẢNH ĐẠI DIỆN -->
                <div class="form-group" style="grid-column: span 2; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1.25rem; flex-wrap: wrap;">
                        <!-- Trái: Khung ảnh đại diện và thông tin -->
                        <div style="display: flex; align-items: center; gap: 1.25rem;">
                            <div style="position: relative; flex-shrink: 0;">
                                <div id="avatarWrapper" style="width: 76px; height: 76px; border-radius: 50%; overflow: hidden; border: 3px solid #ffffff; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); background: #e2e8f0; transition: all 0.25s ease;">
                                    <img id="avatarPreview" src="<?= !empty($user['Avatar']) ? e(url($user['Avatar'])) : '' ?>" 
                                         alt="Preview" 
                                         style="width: 100%; height: 100%; object-fit: cover; <?= empty($user['Avatar']) ? 'display: none;' : '' ?>">
                                    <div id="avatarPlaceholder" style="width: 100%; height: 100%; display: <?= !empty($user['Avatar']) ? 'none' : 'flex' ?>; align-items: center; justify-content: center; background: <?= $isAdmin ? 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)' : 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)' ?>; color: #ffffff; font-size: 1.6rem; font-weight: 800;">
                                        <?= e($avatarInitial) ?>
                                    </div>
                                </div>
                                <label for="avatarInput" title="Tải ảnh mới" style="position: absolute; bottom: -2px; right: -2px; width: 26px; height: 26px; border-radius: 50%; background: #0f172a; color: #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: transform 0.15s ease;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                    <?= svgIcon('camera', '', 13) ?>
                                </label>
                            </div>

                            <div>
                                <div style="font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.2rem;">
                                    Ảnh Đại Diện
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                    Hỗ trợ định dạng JPG, PNG hoặc WEBP (tối đa 5MB).
                                </div>
                                <?php if (isset($errors['avatar'])): ?>
                                    <small style="color: var(--danger-color); font-weight: 600; margin-top: 0.35rem; display: block;"><?= e($errors['avatar']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Phải: Nút bấm Tải ảnh mới -->
                        <div>
                            <label for="avatarInput" style="margin: 0; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.55rem 1.1rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #0f172a; background: #ffffff; border: 1px solid #cbd5e1; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.04);" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#ffffff'">
                                <?= svgIcon('upload', '', 15) ?>
                                <span>Tải ảnh mới</span>
                            </label>
                            <input type="file" id="avatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" style="display: none;" onchange="handleAvatarFileSelect(this)">
                        </div>
                    </div>
                </div>

                <!-- HỌ VÀ TÊN -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="HoTen" style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Họ và tên <span class="required" style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="text" 
                           id="HoTen" 
                           name="HoTen" 
                           class="form-control" 
                           value="<?= e($formData['HoTen']) ?>" 
                           required
                           style="height: 42px; border-radius: 8px; font-size: 0.925rem; font-weight: 500;">
                    <?php if (isset($errors['HoTen'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; margin-top: 0.3rem; display: block;"><?= e($errors['HoTen']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- TÊN ĐĂNG NHẬP -->
                <div class="form-group">
                    <label for="TenDangNhap" style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Tên đăng nhập <span class="required" style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="text" 
                           id="TenDangNhap" 
                           name="TenDangNhap" 
                           class="form-control" 
                           value="<?= e($formData['TenDangNhap']) ?>" 
                           required
                           style="height: 42px; border-radius: 8px; font-size: 0.925rem; font-weight: 600; color: #0f172a;">
                    <?php if (isset($errors['TenDangNhap'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; margin-top: 0.3rem; display: block;"><?= e($errors['TenDangNhap']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- SỐ ĐIỆN THOẠI -->
                <div class="form-group">
                    <label for="SoDienThoai" style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Số điện thoại liên hệ
                    </label>
                    <input type="text" 
                           id="SoDienThoai" 
                           name="SoDienThoai" 
                           class="form-control" 
                           value="<?= e($formData['SoDienThoai']) ?>" 
                           style="height: 42px; border-radius: 8px; font-size: 0.925rem;">
                    <?php if (isset($errors['SoDienThoai'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; margin-top: 0.3rem; display: block;"><?= e($errors['SoDienThoai']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- EMAIL -->
                <div class="form-group" style="grid-column: span 2;">
                    <label for="Email" style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Địa chỉ Email
                    </label>
                    <input type="email" 
                           id="Email" 
                           name="Email" 
                           class="form-control" 
                           value="<?= e($formData['Email']) ?>" 
                           style="height: 42px; border-radius: 8px; font-size: 0.925rem;">
                    <?php if (isset($errors['Email'])): ?>
                        <small style="color: var(--danger-color); font-weight: 500; margin-top: 0.3rem; display: block;"><?= e($errors['Email']) ?></small>
                    <?php endif; ?>
                </div>

                <!-- VAI TRÒ HỆ THỐNG -->
                <div class="form-group">
                    <label for="VaiTro" style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Vai trò hệ thống
                    </label>
                    <?php if ($isAdmin): ?>
                        <select id="VaiTro" name="VaiTro" class="form-control" style="height: 42px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
                            <option value="Admin" <?= ($formData['VaiTro'] === 'Admin') ? 'selected' : '' ?>>Chủ nhà</option>
                            <option value="NhanVien" <?= ($formData['VaiTro'] === 'NhanVien') ? 'selected' : '' ?>>Nhân Viên</option>
                        </select>
                    <?php else: ?>
                        <input type="text" 
                               class="form-control" 
                               value="Nhân Viên" 
                               disabled 
                               style="height: 42px; border-radius: 8px; background: #f8fafc; color: #475569; font-weight: 600;">
                    <?php endif; ?>
                </div>

                <!-- TRẠNG THÁI TÀI KHOẢN -->
                <div class="form-group">
                    <label for="TrangThai" style="font-weight: 600; font-size: 0.875rem; color: #334155; margin-bottom: 0.4rem; display: block;">
                        Trạng thái tài khoản
                    </label>
                    <?php if ($isAdmin): ?>
                        <select id="TrangThai" name="TrangThai" class="form-control" style="height: 42px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; color: #059669;">
                            <option value="Đang làm việc" <?= ($formData['TrangThai'] === 'Đang làm việc') ? 'selected' : '' ?>>Đang làm việc</option>
                            <option value="Nghỉ việc" <?= ($formData['TrangThai'] === 'Nghỉ việc') ? 'selected' : '' ?>>Nghỉ việc</option>
                        </select>
                    <?php else: ?>
                        <input type="text" 
                               class="form-control" 
                               value="<?= e($user['TrangThai'] ?? 'Đang làm việc') ?>" 
                               disabled 
                               style="height: 42px; border-radius: 8px; background: #f8fafc; color: #059669; font-weight: 600;">
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center;">
                <a href="<?= $dashboardUrl ?>" class="btn btn-outline" style="font-weight: 600; border-radius: 8px; height: 42px; display: inline-flex; align-items: center; padding: 0 1.25rem;">
                    Hủy bỏ
                </a>
                <button type="submit" class="btn btn-primary" style="font-weight: 700; border-radius: 8px; height: 42px; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0 1.5rem; background: #0284c7; border: none; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
                    <?= svgIcon('check', '', 16) ?> Lưu Thay Đổi
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@media (max-width: 860px) {
    div[style*="grid-template-columns: 320px 1fr"] {
        grid-template-columns: 1fr !important;
    }
</style>

<script>
function handleAvatarFileSelect(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('avatarPreview');
            const placeholder = document.getElementById('avatarPlaceholder');
            const wrapper = document.getElementById('avatarWrapper');

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
