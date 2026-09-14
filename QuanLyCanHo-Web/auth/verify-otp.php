<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/MailService.php';

use Services\MailService;

$target = $_SESSION['pending_verify_target'] ?? '';
$type = $_SESSION['pending_verify_type'] ?? 'REGISTER';
$userId = $_SESSION['pending_verify_user_id'] ?? null;
$name = $_SESSION['pending_verify_name'] ?? 'Quý khách';

if (empty($target)) {
    setFlash('error', 'Không tìm thấy phiên xác minh OTP. Vui lòng đăng nhập hoặc đăng ký lại.');
    redirect('/auth/login.php');
}

$error = '';
$success = '';

// Xử lý Gửi lại mã OTP (Rate-limit 60s)
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    $lastResend = (int)($_SESSION['last_otp_resend_time'] ?? 0);
    if ((time() - $lastResend) < 60) {
        $wait = 60 - (time() - $lastResend);
        $error = "Vui lòng chờ {$wait} giây trước khi yêu cầu gửi lại mã mới.";
    } else {
        $_SESSION['last_otp_resend_time'] = time();
        $newOtp = MailService::generateOtp($target, $type);
        MailService::sendOtpEmail($target, $name, $newOtp, $type);
        setFlash('success', 'Mã OTP mới đã được gửi tới ' . e($target) . '.');
        redirect('/auth/verify-otp.php');
    }
}

// Xử lý Xác thực OTP POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $digit1 = trim($_POST['digit1'] ?? '');
    $digit2 = trim($_POST['digit2'] ?? '');
    $digit3 = trim($_POST['digit3'] ?? '');
    $digit4 = trim($_POST['digit4'] ?? '');
    $digit5 = trim($_POST['digit5'] ?? '');
    $digit6 = trim($_POST['digit6'] ?? '');

    $fullOtp = $digit1 . $digit2 . $digit3 . $digit4 . $digit5 . $digit6;

    if (strlen($fullOtp) !== 6 || !ctype_digit($fullOtp)) {
        $error = 'Vui lòng nhập đầy đủ 6 chữ số của mã OTP.';
    } else {
        $result = MailService::verifyOtp($target, $fullOtp, $type);
        if ($result['success']) {
            $pdo = require __DIR__ . '/../config/database.php';

            if ($type === 'REGISTER' && $userId) {
                // Kích hoạt tài khoản
                $stmtAct = $pdo->prepare("UPDATE NhanVien SET TrangThai = 'Đang làm việc', email_verified_at = NOW() WHERE MaNV = ?");
                $stmtAct->execute([$userId]);

                unset($_SESSION['pending_verify_target'], $_SESSION['pending_verify_user_id'], $_SESSION['pending_verify_type']);
                setFlash('success', 'Xác minh tài khoản thành công! Bạn có thể đăng nhập ngay.');
                redirect('/auth/login.php');
            } elseif ($type === 'FORGOT_PASSWORD') {
                $_SESSION['otp_verified_for_reset'] = $target;
                redirect('/auth/reset-password.php');
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh OTP - Hệ Thống Quản Lý Căn Hộ Dịch Vụ</title>
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
            max-width: 480px;
            width: 100%;
            margin: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .otp-inputs {
            display: flex;
            justify-content: center;
            gap: 0.6rem;
            margin: 1.75rem 0;
        }
        .otp-digit {
            width: 48px;
            height: 56px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            outline: none;
            transition: all 0.2s ease;
            background-color: rgba(15, 23, 42, 0.6);
            color: #ffffff;
            font-family: inherit;
        }
        .otp-digit:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            background-color: rgba(15, 23, 42, 0.9);
        }
        .timer-box {
            font-size: 0.875rem;
            color: #94a3b8;
            margin: 1.25rem 0;
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
    <div class="icon-circle">
        <?= svgIcon('shield', '', 26) ?>
    </div>
    <h2 style="font-size: 1.4rem; font-weight: 800; color: #ffffff; margin-bottom: 0.35rem; letter-spacing: -0.02em;">
        Xác minh tài khoản
    </h2>
    <p style="font-size: 0.875rem; color: #94a3b8; line-height: 1.5; margin: 0 0 1.25rem 0;">
        Vui lòng nhập mã OTP 6 số đã được gửi tới:<br>
        <strong style="color: #60a5fa;"><?= e($target) ?></strong>
    </p>

    <?php if ($error !== ''): ?>
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 0.75rem 1rem; color: #fca5a5; font-size: 0.875rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; text-align: left;">
            <?= svgIcon('alert-circle', '', 18) ?>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($flashSuccess = getFlash('success')): ?>
        <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 0.75rem 1rem; color: #6ee7b7; font-size: 0.875rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; text-align: left;">
            <?= svgIcon('check', '', 18) ?>
            <div><?= e($flashSuccess) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="otpForm">
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

        <div class="otp-inputs">
            <input type="text" maxlength="1" name="digit1" class="otp-digit" autofocus required autocomplete="off">
            <input type="text" maxlength="1" name="digit2" class="otp-digit" required autocomplete="off">
            <input type="text" maxlength="1" name="digit3" class="otp-digit" required autocomplete="off">
            <input type="text" maxlength="1" name="digit4" class="otp-digit" required autocomplete="off">
            <input type="text" maxlength="1" name="digit5" class="otp-digit" required autocomplete="off">
            <input type="text" maxlength="1" name="digit6" class="otp-digit" required autocomplete="off">
        </div>

        <div class="timer-box">
            Mã có hiệu lực trong: <strong id="countdown" style="color: #f87171;">05:00</strong>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-weight: 700; font-size: 0.95rem; border-radius: 10px; margin-bottom: 1.25rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
            Xác nhận mã OTP
        </button>
    </form>

    <div style="font-size: 0.875rem; color: #94a3b8; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 1.25rem;">
        Chưa nhận được mã? 
        <a href="<?= url('/auth/verify-otp.php?action=resend') ?>" id="resendLink" style="font-weight: 600; color: #60a5fa; text-decoration: none;">Gửi lại mã</a>
    </div>
</div>

<script>
// Auto focus next input on digit entry
const digits = document.querySelectorAll('.otp-digit');
digits.forEach((digit, index) => {
    digit.addEventListener('input', (e) => {
        if (digit.value.length === 1 && index < digits.length - 1) {
            digits[index + 1].focus();
        }
    });

    digit.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && digit.value === '' && index > 0) {
            digits[index - 1].focus();
        }
    });
});

// Countdown timer 5 minutes
let duration = 300;
const timerEl = document.getElementById('countdown');
const timerInterval = setInterval(() => {
    const minutes = Math.floor(duration / 60);
    const seconds = duration % 60;
    timerEl.textContent = `${minutes < 10 ? '0' : ''}${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    duration--;
    if (duration < 0) {
        clearInterval(timerInterval);
        timerEl.textContent = 'Đã hết hạn';
    }
}, 1000);
</script>

</body>
</html>
