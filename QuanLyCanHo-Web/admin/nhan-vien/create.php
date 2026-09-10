<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../src/Repositories/NhanVienRepository.php';
require_once __DIR__ . '/../../src/Validators/NhanVienValidator.php';

$errors = [];
$old = pullOldInput();
$repo = new NhanVienRepository($pdo);

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

    // 1. Kiểm tra validation quy chuẩn
    $errors = NhanVienValidator::validate($input, true);

    // 2. Kiểm tra trùng lặp Tên đăng nhập
    if (empty($errors['TenDangNhap'])) {
        $existingUser = $repo->findByUsername($input['TenDangNhap']);
        if ($existingUser) {
            $errors['TenDangNhap'] = 'Tên đăng nhập "' . $input['TenDangNhap'] . '" đã được sử dụng. Vui lòng chọn tên khác.';
        }
    }

    // 3. Kiểm tra trùng lặp Email (nếu có nhập)
    if (empty($errors['Email']) && $input['Email'] !== '') {
        $existingEmail = $repo->findByEmail($input['Email']);
        if ($existingEmail) {
            $errors['Email'] = 'Địa chỉ email này đã được đăng ký bởi nhân viên khác.';
        }
    }

    // 4. Lưu dữ liệu nếu không có lỗi
    if (!$errors) {
        try {
            $input['MatKhau'] = password_hash($input['MatKhau'], PASSWORD_DEFAULT);
            $repo->create($input);

            flash('success', 'Đã thêm nhân viên "' . $input['HoTen'] . '" thành công.');
            redirect('index.php');
        } catch (PDOException $e) {
            $errors['db'] = 'Không thể lưu nhân viên do lỗi cơ sở dữ liệu. Vui lòng kiểm tra lại.';
            setOldInput($input);
            $old = $input;
        }
    } else {
        setOldInput($input);
        $old = $input;
    }
}

require __DIR__ . '/form.php';
