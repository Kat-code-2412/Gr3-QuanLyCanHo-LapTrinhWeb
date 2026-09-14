<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

$target = $_SESSION['otp_verified_for_reset'] ?? '';

if (empty($target)) {
    setFlash('error', 'Phiên đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
    redirect('/auth/forgot-password.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $error = 'Mật khẩu mới phải có tối thiểu 8 ký tự, bao gồm cả chữ cái và chữ số.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        try {
            $pdo = require __DIR__ . '/../config/database.php';

            // Kiểm tra mật khẩu mới không được trùng với mật khẩu cũ
            $stmtCheck = $pdo->prepare("SELECT MatKhau FROM NhanVien WHERE Email = ? OR SoDienThoai = ? LIMIT 1");
            $stmtCheck->execute([$target, $target]);
            $currentHash = (string)$stmtCheck->fetchColumn();

            if ($currentHash !== '' && (password_verify($newPassword, $currentHash) || $newPassword === $currentHash)) {
                $error = 'Trùng với mật khẩu cũ. Vui lòng đặt lại mật khẩu !';
            } else {
                $hashPass = password_hash($newPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("UPDATE NhanVien SET MatKhau = ? WHERE Email = ? OR SoDienThoai = ?");
                $stmt->execute([$hashPass, $target, $target]);

                unset($_SESSION['otp_verified_for_reset']);
                setFlash('success', 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập bằng mật khẩu mới.');
                redirect('/auth/login.php');
            }
        } catch (Throwable $t) {
            error_log('Lỗi reset mật khẩu: ' . $t->getMessage());
            $error = 'Đã có lỗi xảy ra. Vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu - Hệ Thống Quản Lý Căn Hộ Dịch Vụ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <style>
        body {
            background-color: #0b1329;
            background-image: 
                radial-gradient(at 10% 20%, rgba(37, 99, 235, 0.18) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(30, 58, 138, 0.25) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.9) 0px, transparent 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #f8fafc;
        }
        .auth-card {
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 440px;
            width: 100%;
            margin: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .form-control-dark {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-control-dark:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            background: rgba(15, 23, 42, 0.85);
        }
        .icon-circle {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        @media (max-width: 540px) {
            .auth-card {
                padding: 1.5rem 1.15rem !important;
                margin: 0.75rem auto !important;
                width: calc(100% - 1.25rem) !important;
                border-radius: 16px !important;
            }
            .form-control-dark {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div style="text-align: center; margin-bottom: 1.75rem;">
        <div class="icon-circle">
            <?= svgIcon('shield', '', 26) ?>
        </div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: #ffffff; margin-bottom: 0.35rem; letter-spacing: -0.02em;">
            Đặt lại mật khẩu mới
        </h2>
        <p style="font-size: 0.875rem; color: #94a3b8; margin: 0;">
            Cho tài khoản: <strong style="color: #60a5fa;"><?= e($target) ?></strong>
        </p>
    </div>

    <?php if ($error !== ''): ?>
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 0.75rem 1rem; color: #fca5a5; font-size: 0.875rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
            <?= svgIcon('alert-circle', '', 18) ?>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

        <div class="form-group mb-3">
            <label for="new_password" style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.4rem; color: #cbd5e1;">
                Mật khẩu mới (tối thiểu 8 ký tự, có chữ & số)
            </label>
            <input type="password" 
                   id="new_password" 
                   name="new_password" 
                   class="form-control-dark" 
                   placeholder="••••••••" 
                   required 
                   autofocus>
        </div>

        <div class="form-group mb-4">
            <label for="confirm_password" style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.4rem; color: #cbd5e1;">
                Xác nhận mật khẩu mới
            </label>
            <input type="password" 
                   id="confirm_password" 
                   name="confirm_password" 
                   class="form-control-dark" 
                   placeholder="••••••••" 
                   required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-weight: 700; font-size: 0.95rem; border-radius: 10px; margin-bottom: 0.75rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
            Cập nhật mật khẩu
        </button>

        <a href="<?= url('/auth/login.php') ?>" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; width: 100%; padding: 0.75rem; font-weight: 600; font-size: 0.9rem; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.15); background: rgba(255, 255, 255, 0.04); color: #cbd5e1; text-decoration: none; margin-bottom: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'; this.style.color='#ffffff';" onmouseout="this.style.background='rgba(255, 255, 255, 0.04)'; this.style.color='#cbd5e1';">
            <?= svgIcon('arrow-left', '', 15) ?>
            <span>Quay lại đăng nhập</span>
        </a>
    </form>
</div>

</body>
</html>
