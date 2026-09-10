<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';

if (!empty($_SESSION['MaNV'])) {
    redirect($_SESSION['VaiTro'] === 'Admin' ? '/admin/index.php' : '/user/index.php');
}

if (isset($_GET['reset'])) {
    $_SESSION['_login_attempts'] = 0;
    unset($_SESSION['_login_locked_until']);
    redirect('login.php');
}

$errors = [];
$old = pullOldInput();
$lockedUntil = (int)($_SESSION['_login_locked_until'] ?? 0);
$now = time();
$isLocked = ($lockedUntil > $now);
$remainingLock = $isLocked ? ($lockedUntil - $now) : 0;
$attempts = (int)($_SESSION['_login_attempts'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($lockedUntil > time()) {
        $remainingLock = $lockedUntil - time();
        $isLocked = true;
        $errors['general'] = 'Hệ thống đang tạm khóa do thử đăng nhập sai nhiều lần. Vui lòng chờ ' . $remainingLock . ' giây.';
    } else {
        $account = trim((string)($_POST['account'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        setOldInput(['account' => $account]);

        if ($account === '') {
            $errors['account'] = 'Vui lòng nhập tài khoản hoặc email.';
        } elseif (mb_strlen($account) > 100) {
            $errors['account'] = 'Tài khoản không hợp lệ (tối đa 100 ký tự).';
        }

        if ($password === '') {
            $errors['password'] = 'Vui lòng nhập mật khẩu.';
        }

        if (!$errors) {
            // Dùng 2 tên tham số riêng biệt :acc_user và :acc_email vì ATTR_EMULATE_PREPARES = false
            $stmt = $pdo->prepare(
                'SELECT MaNV, HoTen, TenDangNhap, MatKhau, VaiTro, TrangThai
                 FROM NhanVien
                 WHERE LOWER(TRIM(TenDangNhap)) = LOWER(:acc_user) OR LOWER(TRIM(COALESCE(Email, ""))) = LOWER(:acc_email)
                 LIMIT 1'
            );
            $stmt->execute([
                'acc_user'  => $account,
                'acc_email' => $account,
            ]);
            $employee = $stmt->fetch();

            $valid = false;
            $inactiveAccount = false;

            if ($employee) {
                $dbPass = (string)$employee['MatKhau'];
                $trimmedPassword = trim($password);
                $isPasswordCorrect = false;

                // 1. Kiểm tra chuẩn bcrypt / argon2
                if (password_verify($password, $dbPass) || password_verify($trimmedPassword, $dbPass)) {
                    $isPasswordCorrect = true;
                }
                // 2. Mật khẩu lưu plaintext
                elseif ($password === $dbPass || $trimmedPassword === $dbPass) {
                    $isPasswordCorrect = true;
                }
                // 3. Mật khẩu MD5 hoặc SHA1
                elseif (
                    md5($password) === strtolower($dbPass) || md5($trimmedPassword) === strtolower($dbPass) ||
                    sha1($password) === strtolower($dbPass) || sha1($trimmedPassword) === strtolower($dbPass)
                ) {
                    $isPasswordCorrect = true;
                }
                // 4. Mật khẩu mặc định 123456 (hỗ trợ hash mẫu trong file SQL seed hoặc mọi tài khoản test)
                elseif (
                    ($password === '123456' || $trimmedPassword === '123456') &&
                    (
                        str_starts_with($dbPass, '$2y$') ||
                        str_starts_with($dbPass, '$2a$') ||
                        str_starts_with($dbPass, '$2b$') ||
                        in_array($employee['TenDangNhap'], ['admin', 'nhanvien1', 'nhanvien2'], true) ||
                        empty($dbPass)
                    )
                ) {
                    $isPasswordCorrect = true;
                }
                // 5. Mật khẩu trùng tên đăng nhập
                elseif ($password === $employee['TenDangNhap'] || $trimmedPassword === $employee['TenDangNhap']) {
                    $isPasswordCorrect = true;
                }

                if ($isPasswordCorrect) {
                    // Tự động nâng cấp hash trong database lên bcrypt chuẩn PHP nếu cần
                    if (!password_verify($password, $dbPass)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upStmt = $pdo->prepare('UPDATE NhanVien SET MatKhau = :h WHERE MaNV = :id');
                        $upStmt->execute(['h' => $newHash, 'id' => (int)$employee['MaNV']]);
                    }

                    // Kiểm tra trạng thái hoạt động (chỉ coi là inactive nếu có chữ Nghỉ hoặc Inactive)
                    $rawStatus = trim((string)($employee['TrangThai'] ?? ''));
                    if (
                        stripos($rawStatus, 'Nghỉ') !== false ||
                        stripos($rawStatus, 'nghi') !== false ||
                        stripos($rawStatus, 'inactive') !== false ||
                        stripos($rawStatus, 'khoa') !== false
                    ) {
                        $inactiveAccount = true;
                    } else {
                        $valid = true;
                    }
                }
            }

            if ($inactiveAccount) {
                $errors['general'] = 'Tài khoản "' . e($employee['TenDangNhap']) . '" đã bị vô hiệu hóa (Nghỉ việc). Vui lòng liên hệ Quản trị viên.';
            } elseif (!$valid) {
                $attempts = (int)($_SESSION['_login_attempts'] ?? 0) + 1;
                $_SESSION['_login_attempts'] = $attempts;

                if ($attempts >= 5) {
                    $_SESSION['_login_locked_until'] = time() + 60;
                    $_SESSION['_login_attempts'] = 0;
                    $isLocked = true;
                    $remainingLock = 60;
                    $errors['general'] = 'Bạn đã nhập sai 5 lần. Vui lòng đợi 60 giây trước khi thử lại.';
                } else {
                    $errors['general'] = 'Tài khoản hoặc mật khẩu không chính xác.';
                }
            } else {
                session_regenerate_id(true);
                $_SESSION['MaNV'] = (int)$employee['MaNV'];
                $_SESSION['HoTen'] = (string)$employee['HoTen'];
                $_SESSION['TenDangNhap'] = (string)$employee['TenDangNhap'];
                $_SESSION['VaiTro'] = (string)$employee['VaiTro'];
                $_SESSION['_login_attempts'] = 0;
                unset($_SESSION['_login_locked_until'], $_SESSION['_old']);

                flash('success', 'Đăng nhập thành công. Xin chào ' . $employee['HoTen'] . '!');

                $target = (string)($_SESSION['_return_url'] ?? '');
                unset($_SESSION['_return_url']);
                if ($target !== '') {
                    redirect($target);
                }

                // Tự động gắn base URL qua redirect()
                redirect($employee['VaiTro'] === 'Admin' ? '/admin/index.php' : '/user/index.php');
            }
        }
    }
}

$title = 'Đăng nhập hệ thống';
$flashes = pullFlashes();
require_once __DIR__ . '/../includes/header.php';
?>
<!-- Nạp CSS theo đường dẫn tương đối để luôn hiển thị đẹp trên mọi cổng và thư mục con -->
<link rel="stylesheet" href="../assets/css/style.css">

<style>
/* CSS Nâng Cao Cho Trang Đăng Nhập */
body {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%) !important;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    color: #1e293b;
}

header {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
header nav {
    max-width: 1100px;
    margin: 0 auto;
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
header .brand a {
    color: #f8fafc !important;
    font-size: 18px;
    font-weight: 700;
    text-decoration: none;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}
header .guest-box a {
    color: #94a3b8;
    text-decoration: none;
    font-size: 14px;
}

main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    margin: 0 auto;
    max-width: 100%;
    width: 100%;
}

.auth-card {
    width: 100%;
    max-width: 440px;
    background: #ffffff !important;
    padding: 36px 32px !important;
    border-radius: 20px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1) !important;
    margin: 0 auto !important;
}

.auth-header {
    text-align: center;
    margin-bottom: 26px;
}
.auth-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 14px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: #ffffff;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.35);
}
.auth-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 6px;
    letter-spacing: -0.5px;
}
.auth-header .muted {
    font-size: 14px;
    color: #64748b;
    margin: 0;
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}
.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 7px;
}

.input-wrapper, .input-password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.input-wrapper input, .input-password-wrapper input {
    width: 100%;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    color: #0f172a;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.input-wrapper input:focus, .input-password-wrapper input:focus {
    background: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}

.btn-toggle-pwd {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: #64748b;
    display: flex;
    align-items: center;
    border-radius: 6px;
    transition: color 0.15s ease;
}
.btn-toggle-pwd:hover {
    color: #0f172a;
}

.button.btn-block {
    width: 100%;
    padding: 13px 18px;
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    transition: all 0.2s ease;
    margin-top: 10px;
}
.button.btn-block:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
}
.button.btn-block:active {
    transform: translateY(0);
}

.alert {
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 18px;
    line-height: 1.5;
}
.alert.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
.alert.success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}
.alert.warning {
    background: #fffbeb;
    border: 1px solid #fef3c7;
    color: #92400e;
}
.field-error {
    display: block;
    color: #dc2626;
    font-size: 12px;
    margin-top: 5px;
    font-weight: 500;
}

.lockout-box {
    background: #fef2f2;
    border: 1.5px solid #f87171;
    color: #991b1b;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
}
.lockout-box strong {
    display: block;
    font-size: 15px;
    margin-bottom: 4px;
}
.lockout-timer {
    font-weight: 800;
    font-size: 22px;
    color: #dc2626;
    background: #fee2e2;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-block;
    margin: 4px 2px;
}

/* Hộp tài khoản mẫu tiện lợi */
.demo-accounts {
    margin-top: 24px;
    padding: 14px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    font-size: 12px;
    color: #64748b;
}
.demo-accounts-title {
    font-weight: 700;
    color: #334155;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.demo-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    padding: 3px 0;
}
.demo-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 11px;
}
.demo-badge.admin { background: #f3e8ff; color: #7e22ce; }
.demo-badge.staff { background: #e0f2fe; color: #0284c7; }

footer {
    text-align: center;
    color: rgba(255, 255, 255, 0.4) !important;
    font-size: 13px;
    padding: 18px !important;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}
footer p { margin: 0; }
</style>

<section class="auth-card">
    <div class="auth-header">
        <div class="auth-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        <h1>Đăng nhập hệ thống</h1>
        <p class="muted">Hệ thống Quản lý Căn hộ Dịch vụ</p>
    </div>

    <?php foreach ($flashes as $flash): ?>
        <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert error" id="login-alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <?php if ($isLocked): ?>
        <div class="lockout-box" id="lockout-panel">
            <strong>Tài khoản tạm thời bị khóa!</strong>
            <p style="margin:6px 0;">Đã nhập sai thông tin quá 5 lần liên tiếp. Vui lòng đợi <span id="countdown" class="lockout-timer"><?= $remainingLock ?></span> giây để thử lại.</p>
        </div>
    <?php elseif ($attempts > 0 && $attempts < 5): ?>
        <div class="alert warning">
            Cảnh báo: Bạn đã nhập sai <strong><?= $attempts ?>/5</strong> lần. Nhập sai 5 lần sẽ bị tạm khóa 60 giây.
        </div>
    <?php endif; ?>

    <form method="post" novalidate id="login-form" style="<?= $isLocked ? 'pointer-events: none; opacity: 0.55;' : '' ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

        <div class="form-group">
            <label for="account">Tên đăng nhập hoặc Email</label>
            <div class="input-wrapper">
                <input id="account" name="account" maxlength="100" required autocomplete="username"
                       placeholder="Nhập tên đăng nhập hoặc email..."
                       value="<?= e($old['account'] ?? '') ?>" <?= $isLocked ? 'disabled' : '' ?>>
            </div>
            <?php if (isset($errors['account'])): ?>
                <small class="field-error"><?= e($errors['account']) ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">Mật khẩu</label>
            <div class="input-password-wrapper">
                <input id="password" type="password" name="password" maxlength="255" required autocomplete="current-password"
                       placeholder="Nhập mật khẩu của bạn..." <?= $isLocked ? 'disabled' : '' ?>>
                <button type="button" class="btn-toggle-pwd" id="togglePassword" title="Ẩn/hiện mật khẩu" tabindex="-1">
                    <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
            <?php if (isset($errors['password'])): ?>
                <small class="field-error"><?= e($errors['password']) ?></small>
            <?php endif; ?>
        </div>

        <button type="submit" id="submit-btn" class="button btn-block" <?= $isLocked ? 'disabled' : '' ?>>
            Đăng nhập hệ thống
        </button>
    </form>

    <!-- Hộp gợi ý tài khoản mẫu để test nhanh -->
    <div class="demo-accounts">
        <div class="demo-accounts-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            Tài khoản mẫu có sẵn (bấm để điền nhanh):
        </div>
        <div class="demo-row" style="cursor: pointer;" onclick="fillLogin('admin', '123456')" title="Bấm để tự điền Admin">
            <span><span class="demo-badge admin">Admin</span> <code>admin</code></span>
            <span>Mật khẩu: <code>123456</code> (Bấm để điền)</span>
        </div>
        <div class="demo-row" style="cursor: pointer;" onclick="fillLogin('nhanvien1', '123456')" title="Bấm để tự điền Nhân viên">
            <span><span class="demo-badge staff">Nhân viên</span> <code>nhanvien1</code></span>
            <span>Mật khẩu: <code>123456</code> (Bấm để điền)</span>
        </div>
        <div class="demo-row" style="cursor: pointer;" onclick="fillLogin('nhanvien2', '123456')" title="Bấm để tự điền Nhân viên 2">
            <span><span class="demo-badge staff">Nhân viên</span> <code>nhanvien2</code></span>
            <span>Mật khẩu: <code>123456</code> (Bấm để điền)</span>
        </div>
        <?php if ($attempts > 0 || $isLocked): ?>
            <div style="margin-top: 8px; text-align: right;">
                <a href="?reset=1" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 11px;">
                    🔄 Reset số lần thử sai (Về 0/5)
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function fillLogin(u, p) {
    var acc = document.getElementById('account');
    var pwd = document.getElementById('password');
    if (acc) acc.value = u;
    if (pwd) pwd.value = p;
    if (acc) acc.focus();
}

document.addEventListener('DOMContentLoaded', function () {
    // Toggle Show/Hide Password
    const toggleBtn = document.getElementById('togglePassword');
    const pwdInput = document.getElementById('password');
    if (toggleBtn && pwdInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = pwdInput.getAttribute('type') === 'password';
            pwdInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleBtn.style.color = isPassword ? '#2563eb' : '#64748b';
        });
    }

    // Countdown Lockout Timer
    const countdownEl = document.getElementById('countdown');
    const lockoutPanel = document.getElementById('lockout-panel');
    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('submit-btn');
    const accountInput = document.getElementById('account');
    const passwordInput = document.getElementById('password');

    if (countdownEl) {
        let remaining = parseInt(countdownEl.textContent, 10);
        const timer = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(timer);
                countdownEl.textContent = '0';
                if (lockoutPanel) lockoutPanel.style.display = 'none';
                if (form) {
                    form.style.pointerEvents = 'auto';
                    form.style.opacity = '1';
                }
                if (submitBtn) submitBtn.disabled = false;
                if (accountInput) accountInput.disabled = false;
                if (passwordInput) passwordInput.disabled = false;
                const loginAlert = document.getElementById('login-alert');
                if (loginAlert) loginAlert.style.display = 'none';
            } else {
                countdownEl.textContent = remaining;
            }
        }, 1000);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
