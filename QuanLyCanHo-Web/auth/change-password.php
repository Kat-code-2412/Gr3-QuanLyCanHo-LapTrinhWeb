<?php

declare(strict_types=1);

$title = 'Đổi Mật Khẩu Tài Khoản';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../config/database.php';
$maNv = (int)($_SESSION['MaNV'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Vui lòng điền đầy đủ các trường thông tin.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu mới và xác nhận mật khẩu không khớp nhau.';
    } elseif (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $error = 'Mật khẩu mới phải có tối thiểu 8 ký tự, bao gồm cả chữ cái và chữ số.';
    } else {
        $stmt = $pdo->prepare('SELECT MatKhau FROM NhanVien WHERE MaNV = ?');
        $stmt->execute([$maNv]);
        $currentHash = (string)$stmt->fetchColumn();

        if (!password_verify($oldPassword, $currentHash) && !($oldPassword === '123456' && $currentHash === '123456')) {
            $error = 'Mật khẩu hiện tại không chính xác.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUp = $pdo->prepare('UPDATE NhanVien SET MatKhau = ? WHERE MaNV = ?');
            $stmtUp->execute([$newHash, $maNv]);

            logAudit('CHANGE_PASSWORD', 'NhanVien', (string)$maNv, 'Đổi mật khẩu tài khoản thành công');
            setFlash('success', 'Đổi mật khẩu tài khoản thành công!');
            redirect(($_SESSION['VaiTro'] ?? '') === 'Admin' ? '/admin/index.php' : '/user/index.php');
        }
    }
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h1 class="page-title" style="margin: 0;">Đổi Mật Khẩu</h1>
        <p class="text-muted" style="margin: 0.25rem 0 0;">Cập nhật mật khẩu định kỳ để bảo vệ tài khoản quản trị.</p>
    </div>
    <div>
        <a href="<?= url('/auth/profile.php') ?>" class="btn btn-outline" style="font-weight: 600; border-radius: 8px;">
            <?= svgIcon('user', '', 15) ?> Hồ Sơ Cá Nhân
        </a>
    </div>
</div>

<div class="card" style="max-width: 550px; margin: 0 auto;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3 style="margin: 0;">Bảo Mật Tài Khoản</h3>
    </div>
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger mb-3">
                <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group mb-3">
                <label for="old_password" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                    Mật khẩu hiện tại <span class="required" style="color: var(--danger-color);">*</span>
                </label>
                <div style="position: relative;">
                    <input type="password" 
                           id="old_password" 
                           name="old_password" 
                           class="form-control" 
                           placeholder="Nhập mật khẩu đang dùng" 
                           required>
                    <span onclick="togglePasswordVisibility('old_password', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b;"><?= svgIcon('eye', '', 16) ?></span>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="new_password" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                    Mật khẩu mới <span class="required" style="color: var(--danger-color);">*</span>
                </label>
                <div style="position: relative;">
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-control" 
                           placeholder="Tối thiểu 8 ký tự, có chữ và số" 
                           required>
                    <span onclick="togglePasswordVisibility('new_password', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b;"><?= svgIcon('eye', '', 16) ?></span>
                </div>
                <small style="color: var(--text-muted);">Mật khẩu nên chứa cả chữ hoa, chữ thường và ký tự đặc biệt.</small>
            </div>

            <div class="form-group mb-3">
                <label for="confirm_password" style="font-weight: 600; display: block; margin-bottom: 0.35rem;">
                    Xác nhận mật khẩu mới <span class="required" style="color: var(--danger-color);">*</span>
                </label>
                <div style="position: relative;">
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control" 
                           placeholder="Nhập lại mật khẩu mới" 
                           required>
                    <span onclick="togglePasswordVisibility('confirm_password', this)" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b;"><?= svgIcon('eye', '', 16) ?></span>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <a href="<?= url(($_SESSION['VaiTro'] ?? '') === 'Admin' ? '/admin/index.php' : '/user/index.php') ?>" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">Cập Nhật Mật Khẩu</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, iconEl) {
    const input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        iconEl.style.color = '#2563eb';
    } else {
        input.type = 'password';
        iconEl.style.color = '#64748b';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
