<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../src/Repositories/NhanVienRepository.php';
require_once __DIR__ . '/../../src/Validators/NhanVienValidator.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$repo = new NhanVienRepository($pdo);
$employee = $repo->find($id);

if (!$employee) {
    http_response_code(404);
    $title = '404 - Không tìm thấy nhân viên';
    require_once __DIR__ . '/../../includes/header.php';
    ?>
    <section class="error-card">
        <h1>404 - Không tìm thấy nhân viên</h1>
        <p>Tài khoản nhân viên với mã #<?= $id ?> không tồn tại hoặc đã bị xóa.</p>
        <div class="actions" style="justify-content: center;">
            <a class="button" href="index.php">Quay lại danh sách</a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$errors = [];
$old = pullOldInput();
$currentUserId = (int)($_SESSION['MaNV'] ?? 0);
$isEditingSelf = ($id === $currentUserId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'HoTen'       => trim((string)($_POST['HoTen'] ?? '')),
        'TenDangNhap' => trim((string)($_POST['TenDangNhap'] ?? '')),
        'MatKhau'     => (string)($_POST['MatKhau'] ?? ''),
        'VaiTro'      => (string)($_POST['VaiTro'] ?? ''),
        'SoDienThoai' => trim((string)($_POST['SoDienThoai'] ?? '')),
        'Email'       => trim((string)($_POST['Email'] ?? '')),
        'TrangThai'   => (string)($_POST['TrangThai'] ?? ''),
    ];

    // Ngăn chặn admin tự hạ quyền hoặc tự khóa tài khoản của chính mình
    if ($isEditingSelf) {
        $input['VaiTro'] = 'Admin';
        $input['TrangThai'] = 'Đang làm việc';
    }

    // 1. Kiểm tra validation quy chuẩn (chế độ cập nhật creating = false)
    $errors = NhanVienValidator::validate($input, false);

    // 2. Kiểm tra trùng lặp Tên đăng nhập (ngoại trừ chính nhân viên này)
    if (empty($errors['TenDangNhap'])) {
        $existingUser = $repo->findByUsername($input['TenDangNhap'], $id);
        if ($existingUser) {
            $errors['TenDangNhap'] = 'Tên đăng nhập "' . $input['TenDangNhap'] . '" đã được sử dụng bởi người khác.';
        }
    }

    // 3. Kiểm tra trùng lặp Email (ngoại trừ chính nhân viên này)
    if (empty($errors['Email']) && $input['Email'] !== '') {
        $existingEmail = $repo->findByEmail($input['Email'], $id);
        if ($existingEmail) {
            $errors['Email'] = 'Địa chỉ email này đã được sử dụng bởi nhân viên khác.';
        }
    }

    // 4. Cập nhật dữ liệu nếu không có lỗi
    if (!$errors) {
        try {
            if ($input['MatKhau'] !== '') {
                $input['MatKhau'] = password_hash($input['MatKhau'], PASSWORD_DEFAULT);
            }

            $repo->update($id, $input);

            // Nếu sửa thông tin của chính mình, cập nhật lại phiên làm việc
            if ($isEditingSelf) {
                $_SESSION['HoTen'] = $input['HoTen'];
                $_SESSION['TenDangNhap'] = $input['TenDangNhap'];
            }

            flash('success', 'Đã cập nhật thông tin nhân viên "' . $input['HoTen'] . '" thành công.');
            redirect('index.php');
        } catch (PDOException $e) {
            $errors['db'] = 'Không thể cập nhật do lỗi cơ sở dữ liệu. Vui lòng kiểm tra lại.';
            $old = $input;
        }
    } else {
        $old = $input;
    }
}

require __DIR__ . '/form.php';
