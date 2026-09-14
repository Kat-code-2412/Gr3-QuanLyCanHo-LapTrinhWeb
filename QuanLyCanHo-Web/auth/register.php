<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/MailService.php';

use Services\MailService;

// Nếu đã đăng nhập -> chuyển hướng vào Dashboard
if (!empty($_SESSION['MaNV'])) {
    redirect(($_SESSION['VaiTro'] ?? '') === 'Admin' ? '/admin/index.php' : '/user/index.php');
}

$error = '';
$username = '';
$fullName = '';
$phone = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation nghiêm ngặt theo yêu cầu Section V
    if ($username === '' || $fullName === '' || $password === '' || ($email === '' && $phone === '')) {
        $error = 'Vui lòng điền đầy đủ các thông tin bắt buộc.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
        $error = 'Tên đăng nhập từ 4-30 ký tự, chỉ gồm chữ cái, số và dấu gạch dưới (_).';
    } elseif (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100) {
        $error = 'Họ và tên phải từ 3 đến 100 ký tự.';
    } elseif ($phone !== '' && !preg_match('/^(0[3|5|7|8|9])+([0-9]{8})$/', $phone)) {
        $error = 'Số điện thoại không đúng định dạng Việt Nam (VD: 0901234567).';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ Email không hợp lệ.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Mật khẩu phải có tối thiểu 8 ký tự, bao gồm cả chữ cái và chữ số.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        try {
            $pdo = require __DIR__ . '/../config/database.php';

            // Kiểm tra trùng lặp
            $checkSql = "SELECT MaNV, TrangThai, email_verified_at FROM NhanVien WHERE TenDangNhap = ? OR (Email != '' AND Email = ?) OR (SoDienThoai != '' AND SoDienThoai = ?)";
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute([$username, $email, $phone]);
            $existingRows = $stmtCheck->fetchAll();

            $isDuplicate = false;
            $unverifiedIds = [];
            foreach ($existingRows as $row) {
                if ($row['TrangThai'] !== 'Nghỉ việc' || !empty($row['email_verified_at'])) {
                    $isDuplicate = true;
                    break;
                } else {
                    $unverifiedIds[] = (int)$row['MaNV'];
                }
            }

            if ($isDuplicate) {
                $error = 'Tên đăng nhập, Email hoặc Số điện thoại đã tồn tại trên hệ thống.';
            } else {
                // Dọn dẹp bản ghi chưa kích hoạt cũ trước đó nếu có
                foreach ($unverifiedIds as $delId) {
                    $pdo->prepare("DELETE FROM phanquyen WHERE MaNV = ?")->execute([$delId]);
                    $pdo->prepare("DELETE FROM NhanVien WHERE MaNV = ?")->execute([$delId]);
                }

                $hashPass = password_hash($password, PASSWORD_DEFAULT);

                // Tạo tài khoản với trạng thái INACTIVE chờ xác minh OTP
                $stmtIns = $pdo->prepare("
                    INSERT INTO NhanVien (HoTen, TenDangNhap, MatKhau, VaiTro, SoDienThoai, Email, TrangThai)
                    VALUES (?, ?, ?, 'NhanVien', ?, ?, 'Nghỉ việc')
                ");
                $stmtIns->execute([$fullName, $username, $hashPass, $phone, $email]);
                $userId = (int)$pdo->lastInsertId();

                // Gán quyền cơ bản
                $defaultPerms = [1, 2, 3, 6];
                $stmtPerm = $pdo->prepare("INSERT INTO phanquyen (MaNV, MaQuyen) VALUES (?, ?)");
                foreach ($defaultPerms as $qp) {
                    $stmtPerm->execute([$userId, $qp]);
                }

                // Sinh mã OTP và gửi qua Email
                $target = $email !== '' ? $email : $phone;
                $otp = MailService::generateOtp($target, 'REGISTER');
                MailService::sendOtpEmail($target, $fullName, $otp, 'REGISTER');

                // Lưu session phục vụ trang xác minh
                $_SESSION['pending_verify_target'] = $target;
                $_SESSION['pending_verify_user_id'] = $userId;
                $_SESSION['pending_verify_type'] = 'REGISTER';
                $_SESSION['pending_verify_name'] = $fullName;

                redirect('/auth/verify-otp.php');
            }
        } catch (Throwable $ex) {
            error_log('Lỗi đăng ký: ' . $ex->getMessage());
            $error = 'Đã có lỗi xảy ra trong quá trình đăng ký. Vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1200, user-scalable=yes, maximum-scale=5.0">
    <title>Đăng ký tài khoản - Hệ Thống Quản Lý Căn Hộ Dịch Vụ</title>
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
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        }
        .auth-wrapper {
            display: flex;
            width: 100%;
            max-width: 980px;
            min-height: 620px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            margin: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .auth-brand-panel {
            flex: 1;
            background: linear-gradient(145deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
            color: #ffffff;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }
        .auth-form-panel {
            flex: 1.25;
            padding: 3rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }
        .brand-title {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1.3;
            margin-top: 1.25rem;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
            color: #ffffff;
        }
        .brand-desc {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            margin-bottom: 0.85rem;
            color: #cbd5e1;
            font-weight: 500;
        }
        .feature-icon-box {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .pwd-container {
            position: relative;
        }
        .pwd-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            user-select: none;
            color: #94a3b8;
            display: flex;
            align-items: center;
            transition: color 0.15s ease;
        }
        .pwd-toggle:hover {
            color: #2563eb;
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <!-- PANEL TRÁI: THƯƠNG HIỆU & GIỚI THIỆU -->
    <div class="auth-brand-panel">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);">
                    <?= svgIcon('building', '', 22) ?>
                </div>
                <div class="brand-pill brand-pill-dark">
                    <span class="pill-dot"></span> ĐĂNG KÝ VẬN HÀNH
                </div>
            </div>

            <h1 class="brand-title">Hệ Thống Quản Trị<br>& Vận Hành Căn Hộ</h1>
            <p class="brand-desc">
                Đăng ký tài khoản nhân viên để tham gia quy trình quản lý phòng, giám sát đồng hồ điện nước, tạo hóa đơn và xử lý sự cố.
            </p>
        </div>

        <div>
            <div class="feature-item">
                <div class="feature-icon-box"><?= svgIcon('check', '', 14) ?></div>
                <span>Quản lý tập trung căn hộ và tình trạng phòng</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon-box"><?= svgIcon('check', '', 14) ?></div>
                <span>Tự động hóa tính tiền điện nước và hóa đơn</span>
            </div>
            <div class="feature-item">
                <div class="feature-icon-box"><?= svgIcon('check', '', 14) ?></div>
                <span>Phân quyền bảo mật theo chuẩn doanh nghiệp</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid rgba(255, 255, 255, 0.08); font-size: 0.8rem; color: #64748b;">
                <span>© <?= date('Y') ?> SaaS Management Platform</span>
                <a href="<?= url('/index.php') ?>" style="color: #60a5fa; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                    <?= svgIcon('home', '', 14) ?> <span>Trang chủ</span>
                </a>
            </div>
        </div>
    </div>

    <!-- PANEL PHẢI: FORM ĐĂNG KÝ -->
    <div class="auth-form-panel">
        <div style="margin-bottom: 1.75rem;">
            <div class="brand-pill brand-pill-blue" style="margin-bottom: 0.75rem;">
                <span class="pill-dot"></span> TẠO TÀI KHOẢN MỚI
            </div>
            <h2 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem; letter-spacing: -0.02em;">
                Đăng ký tài khoản nhân viên
            </h2>
            <p style="font-size: 0.875rem; color: #64748b; margin: 0;">
                Nhập thông tin bên dưới để khởi tạo tài khoản quản lý
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= svgIcon('alert-triangle', '', 18) ?>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;" class="mb-2">
                <div class="form-group mb-0">
                    <label for="full_name">Họ và tên <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Nguyễn Văn A" value="<?= e($fullName) ?>" required autofocus>
                </div>
                <div class="form-group mb-0">
                    <label for="username">Tên đăng nhập <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="nhanvien_moi" value="<?= e($username) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;" class="mb-2">
                <div class="form-group mb-0">
                    <label for="email">Email nhận OTP <span style="color: #ef4444;">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="user@gmail.com" value="<?= e($email) ?>" required>
                </div>
                <div class="form-group mb-0">
                    <label for="phone">Số điện thoại</label>
                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="0901234567" value="<?= e($phone) ?>">
                </div>
            </div>

            <div class="form-group mb-2">
                <label for="password">Mật khẩu (tối thiểu 8 ký tự gồm chữ & số) <span style="color: #ef4444;">*</span></label>
                <div class="pwd-container">
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                    <span class="pwd-toggle" onclick="togglePasswordVisibility('password', this)" title="Ẩn/Hiện">
                        <?= svgIcon('eye', '', 16) ?>
                    </span>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="confirm_password">Xác nhận mật khẩu <span style="color: #ef4444;">*</span></label>
                <div class="pwd-container">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                    <span class="pwd-toggle" onclick="togglePasswordVisibility('confirm_password', this)" title="Ẩn/Hiện">
                        <?= svgIcon('eye', '', 16) ?>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-weight: 700; font-size: 0.95rem; letter-spacing: 0.02em;">
                ĐĂNG KÝ TÀI KHOẢN
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.875rem; color: #64748b;">
            Đã có tài khoản quản trị? <a href="<?= url('/auth/login.php') ?>" style="font-weight: 700; color: #2563eb;">Đăng nhập ngay</a>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(id, el) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        el.innerHTML = '<?= addslashes(svgIcon("eye-off", "", 16)) ?>';
        el.style.color = '#2563eb';
    } else {
        inp.type = 'password';
        el.innerHTML = '<?= addslashes(svgIcon("eye", "", 16)) ?>';
        el.style.color = '#94a3b8';
    }
}
</script>

</body>
</html>
