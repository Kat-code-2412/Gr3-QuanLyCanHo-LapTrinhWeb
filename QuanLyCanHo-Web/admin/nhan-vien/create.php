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

    // Nếu hợp lệ -> Thêm vào CSDL
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($formData['MatKhau'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO NhanVien (HoTen, TenDangNhap, MatKhau, VaiTro, SoDienThoai, Email, TrangThai)
                VALUES (:hoTen, :tenDangNhap, :matKhau, :vaiTro, :sdt, :email, :trangThai)
            ');
            $stmt->execute([
                ':hoTen'       => $formData['HoTen'],
                ':tenDangNhap' => $formData['TenDangNhap'],
                ':matKhau'     => $hashedPassword,
                ':vaiTro'      => $formData['VaiTro'],
                ':sdt'         => ($formData['SoDienThoai'] !== '') ? $formData['SoDienThoai'] : null,
                ':email'       => ($formData['Email'] !== '') ? $formData['Email'] : null,
                ':trangThai'   => $formData['TrangThai'],
            ]);

            setFlash('success', 'Thêm mới nhân viên "' . $formData['HoTen'] . '" thành công! Mật khẩu đã được mã hóa Bcrypt.');
            redirect('/admin/nhan-vien/index.php');
        } catch (Throwable $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Thêm Nhân Viên Mới</h1>
        <p class="page-subtitle">Tạo mới tài khoản và phân quyền cho nhân sự đăng nhập</p>
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
<?php elseif (!empty($errors)): ?>
    <div class="alert alert-danger mb-3">
        <span class="alert-icon">✕</span>
        <div>Vui lòng kiểm tra lại các trường dữ liệu có thông báo lỗi màu đỏ bên dưới.</div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3 style="font-size: 1.05rem; font-weight: 600;">👤 Thông Tin Nhân Viên</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
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
                            Nhân viên (Quyền vận hành, không quản lý tài khoản)
                        </option>
                        <option value="Admin" <?= ($formData['VaiTro'] === 'Admin') ? 'selected' : '' ?>>
                            Admin (Chủ nhà - Toàn quyền hệ thống)
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
            </div>

            <!-- Nút bấm thao tác -->
            <div style="margin-top: 2rem; display: flex; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600;">
                    💾 Lưu nhân viên
                </button>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="padding: 0.65rem 1.5rem;">
                    Hủy bỏ
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
