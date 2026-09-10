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

    // Nếu hợp lệ -> Cập nhật CSDL
    if (empty($errors)) {
        try {
            if ($formData['MatKhau'] !== '') {
                $hashed = password_hash($formData['MatKhau'], PASSWORD_DEFAULT);
                $updateSql = '
                    UPDATE NhanVien 
                    SET HoTen = :hoTen, TenDangNhap = :tenDangNhap, MatKhau = :matKhau, 
                        VaiTro = :vaiTro, SoDienThoai = :sdt, Email = :email, TrangThai = :trangThai
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
                    ':id'          => $id,
                ];
            } else {
                $updateSql = '
                    UPDATE NhanVien 
                    SET HoTen = :hoTen, TenDangNhap = :tenDangNhap, 
                        VaiTro = :vaiTro, SoDienThoai = :sdt, Email = :email, TrangThai = :trangThai
                    WHERE MaNV = :id
                ';
                $params = [
                    ':hoTen'       => $formData['HoTen'],
                    ':tenDangNhap' => $formData['TenDangNhap'],
                    ':vaiTro'      => $formData['VaiTro'],
                    ':sdt'         => ($formData['SoDienThoai'] !== '') ? $formData['SoDienThoai'] : null,
                    ':email'       => ($formData['Email'] !== '') ? $formData['Email'] : null,
                    ':trangThai'   => $formData['TrangThai'],
                    ':id'          => $id,
                ];
            }

            $stmtUpdate = $pdo->prepare($updateSql);
            $stmtUpdate->execute($params);

            // Cập nhật lại session nếu tự sửa họ tên chính mình
            if ($isSelf) {
                $_SESSION['HoTen'] = $formData['HoTen'];
                $_SESSION['TenDangNhap'] = $formData['TenDangNhap'];
            }

            setFlash('success', 'Cập nhật thông tin nhân viên "' . $formData['HoTen'] . '" thành công!');
            redirect('/admin/nhan-vien/index.php');
        } catch (Throwable $ex) {
            $errors['general'] = 'Lỗi CSDL: ' . $ex->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sửa Nhân Viên #<?= (int)$employee['MaNV'] ?>: <?= e($employee['HoTen']) ?></h1>
        <p class="page-subtitle">Cập nhật thông tin cá nhân, quyền hạn và trạng thái hoạt động</p>
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
        <div>Vui lòng kiểm tra lại các trường thông tin có báo lỗi màu đỏ bên dưới.</div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.05rem; font-weight: 600;">
            ✏️ Chỉnh Sửa Thông Tin
            <?php if ($isSelf): ?>
                <span style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; background-color: #dbeafe; color: #1d4ed8; margin-left: 6px;">(Tài khoản của bạn)</span>
            <?php endif; ?>
        </h3>
        <span style="font-size: 0.85rem; color: var(--text-muted);">Mã: <strong>#<?= (int)$employee['MaNV'] ?></strong></span>
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
                        <input type="text" class="form-control" value="Admin (Chủ nhà)" disabled style="background-color: #f1f5f9; cursor: not-allowed;">
                        <input type="hidden" name="VaiTro" value="Admin">
                        <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            🔒 Bạn đang đăng nhập bằng tài khoản này, không thể tự hạ quyền.
                        </small>
                    <?php else: ?>
                        <select id="VaiTro" name="VaiTro" class="form-control">
                            <option value="NhanVien" <?= ($formData['VaiTro'] === 'NhanVien') ? 'selected' : '' ?>>
                                Nhân viên (Quyền vận hành)
                            </option>
                            <option value="Admin" <?= ($formData['VaiTro'] === 'Admin') ? 'selected' : '' ?>>
                                Admin (Chủ nhà - Toàn quyền)
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
                            🔒 Bạn không thể tự khóa tài khoản của chính mình.
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
            </div>

            <!-- Nút bấm thao tác -->
            <div style="margin-top: 2rem; display: flex; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600;">
                    💾 Cập nhật nhân viên
                </button>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="padding: 0.65rem 1.5rem;">
                    Hủy bỏ
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
