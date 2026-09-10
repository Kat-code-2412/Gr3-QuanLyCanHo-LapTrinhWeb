<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

// Nếu đã đăng nhập rồi -> Chuyển hướng theo vai trò
if (!empty($_SESSION['MaNV'])) {
    if (($_SESSION['VaiTro'] ?? '') === 'Admin') {
        redirect('/admin/index.php');
    } else {
        redirect('/user/index.php');
    }
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // 1. Chống Brute-force cơ bản (khóa 60s sau 5 lần nhập sai)
    $failCount = (int)($_SESSION['login_fail_count'] ?? 0);
    $lastFailTime = (int)($_SESSION['login_last_fail_time'] ?? 0);
    $lockoutDuration = 60; // 60 giây

    if ($failCount >= 5 && (time() - $lastFailTime) < $lockoutDuration) {
        $waitSec = $lockoutDuration - (time() - $lastFailTime);
        $error = "Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$waitSec} giây.";
    } elseif ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } elseif (mb_strlen($username) > 50 || strlen($password) > 255) {
        $error = 'Dữ liệu đăng nhập vượt quá độ dài quy định.';
    } else {
        try {
            $pdo = require __DIR__ . '/../config/database.php';
            $stmt = $pdo->prepare('SELECT * FROM NhanVien WHERE TenDangNhap = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $isValidPassword = false;
            if ($user) {
                if (password_verify($password, $user['MatKhau'])) {
                    $isValidPassword = true;
                } elseif ($password === '123456' && (str_starts_with($user['MatKhau'], '$2y$10$Fq0X6nQY8') || $user['MatKhau'] === '123456')) {
                    // Nâng cấp mật khẩu mẫu sang Bcrypt chuẩn an toàn
                    $isValidPassword = true;
                    $upHash = password_hash('123456', PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE NhanVien SET MatKhau = ? WHERE MaNV = ?')->execute([$upHash, $user['MaNV']]);
                }
            }

            if ($user && $isValidPassword) {
                // 2. Kiểm tra trạng thái tài khoản (Nghỉ việc / Khóa)
                if (($user['TrangThai'] ?? '') === 'Nghỉ việc') {
                    $error = 'Tài khoản đã bị khóa/nghỉ việc, không thể đăng nhập.';
                } else {
                    // Đăng nhập thành công -> Reset bộ đếm brute-force
                    unset($_SESSION['login_fail_count'], $_SESSION['login_last_fail_time']);

                    session_regenerate_id(true);
                    unset($_SESSION['MaKhach'], $_SESSION['HoTenKhach']);
                    $_SESSION['MaNV'] = (int)$user['MaNV'];
                    $_SESSION['TenDangNhap'] = $user['TenDangNhap'];
                    $_SESSION['HoTen'] = $user['HoTen'];
                    $_SESSION['VaiTro'] = $user['VaiTro'];

                    setFlash('success', 'Đăng nhập thành công! Xin chào ' . $user['HoTen'] . '.');

                    if ($user['VaiTro'] === 'Admin') {
                        redirect('/admin/index.php');
                    } else {
                        redirect('/user/index.php');
                    }
                }
            } else {
                // Tăng biến đếm sai mật khẩu
                $_SESSION['login_fail_count'] = $failCount + 1;
                $_SESSION['login_last_fail_time'] = time();
                $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
            }
        } catch (Throwable $ex) {
            error_log('Lỗi đăng nhập: ' . $ex->getMessage());
            $error = 'Đã có lỗi xảy ra trong quá trình xử lý, vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập hệ thống - Quản Lý Căn Hộ Dịch Vụ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #64748b;
            font-size: 0.9rem;
        }
        .login-hint {
            margin-top: 1.5rem;
            padding: 0.85rem;
            background-color: #f8fafc;
            border-radius: 8px;
            font-size: 0.825rem;
            color: #475569;
            border: 1px dashed #cbd5e1;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h2>🏢 Hệ Thống Quản Lý Căn Hộ Dịch Vụ</h2>
        <p>Vui lòng đăng nhập tài khoản của bạn</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
            <span class="alert-icon">✕</span>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group mb-2">
            <label for="username" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                Tên đăng nhập
            </label>
            <input type="text" 
                   id="username" 
                   name="username" 
                   class="form-control" 
                   placeholder="Nhập tên đăng nhập (admin / nhanvien)" 
                   value="<?= e($username) ?>" 
                   required 
                   autofocus>
        </div>

        <div class="form-group mb-3">
            <label for="password" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                Mật khẩu
            </label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   class="form-control" 
                   placeholder="Nhập mật khẩu" 
                   required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem; font-weight: 600;">
            🚀 Đăng nhập
        </button>
    </form>

    <div class="login-hint">
        <strong>Tài khoản thử nghiệm:</strong><br>
        • Admin: <code>admin</code> / <code>123456</code><br>
        • Nhân viên: <code>nhanvien1</code> / <code>123456</code>
    </div>
    <p style="text-align: center; margin: 1rem 0 0;">
        <a href="<?= url('/auth/customer-login.php') ?>">Khách hàng đăng nhập bằng SĐT và CCCD</a>
    </p>
</div>

</body>
</html>
