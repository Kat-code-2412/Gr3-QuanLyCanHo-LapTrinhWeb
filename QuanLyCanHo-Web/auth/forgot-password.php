<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/MailService.php';

use Services\MailService;

$error = '';
$accountInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $accountInput = trim($_POST['account'] ?? '');

    if ($accountInput === '') {
        $error = 'Vui lòng nhập Email hoặc Số điện thoại của bạn.';
    } else {
        try {
            $pdo = require __DIR__ . '/../config/database.php';

            // Tìm nhân viên
            $stmt = $pdo->prepare("SELECT * FROM NhanVien WHERE Email = ? OR SoDienThoai = ? LIMIT 1");
            $stmt->execute([$accountInput, $accountInput]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Không tìm thấy tài khoản nào khớp với thông tin cung cấp.';
            } else {
                $target = !empty($user['Email']) ? $user['Email'] : $user['SoDienThoai'];
                $otp = MailService::generateOtp($target, 'FORGOT_PASSWORD');
                MailService::sendOtpEmail($target, $user['HoTen'], $otp, 'FORGOT_PASSWORD');

                $_SESSION['pending_verify_target'] = $target;
                $_SESSION['pending_verify_type'] = 'FORGOT_PASSWORD';
                $_SESSION['pending_verify_name'] = $user['HoTen'];

                redirect('/auth/verify-otp.php');
            }
        } catch (Throwable $t) {
            error_log('Lỗi quên mật khẩu: ' . $t->getMessage());
            $error = 'Đã có lỗi xảy ra. Vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1200, user-scalable=yes, maximum-scale=5.0">
    <title>Khôi phục mật khẩu - Hệ Thống Quản Lý Căn Hộ Dịch Vụ</title>
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
            min-width: 1200px;
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
            background: rgba(37, 99, 235, 0.15);
            border: 1px solid rgba(37, 99, 235, 0.3);
            color: #60a5fa;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div style="text-align: center; margin-bottom: 1.75rem;">
        <div class="icon-circle">
            <?= svgIcon('lock', '', 26) ?>
        </div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: #ffffff; margin-bottom: 0.35rem; letter-spacing: -0.02em;">
            Khôi phục mật khẩu
        </h2>
        <p style="font-size: 0.875rem; color: #94a3b8; margin: 0; line-height: 1.5;">
            Nhập Email hoặc Số điện thoại để nhận mã xác minh OTP
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
            <label for="account" style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 0.4rem; color: #cbd5e1;">
                Email hoặc Số điện thoại
            </label>
            <input type="text" 
                   id="account" 
                   name="account" 
                   class="form-control-dark" 
                   placeholder="Nhập email hoặc số điện thoại đã đăng ký" 
                   value="<?= e($accountInput) ?>" 
                   required 
                   autofocus>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-weight: 700; font-size: 0.95rem; border-radius: 10px; margin-top: 0.5rem; margin-bottom: 1.25rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
            Gửi mã xác minh OTP
        </button>
    </form>

    <div style="text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 1.25rem; font-size: 0.875rem;">
        <a href="<?= url('/auth/login.php') ?>" style="font-weight: 600; color: #60a5fa; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
            &larr; Quay lại đăng nhập
        </a>
    </div>
</div>

</body>
</html>
