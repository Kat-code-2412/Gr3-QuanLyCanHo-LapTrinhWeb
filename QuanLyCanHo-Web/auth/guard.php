<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function requireLogin(): void
{
    if (empty($_SESSION['MaNV'])) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SESSION['_return_url'] = (string)$_SERVER['REQUEST_URI'];
        }
        flash('error', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('/auth/login.php');
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    $role = (string)($_SESSION['VaiTro'] ?? '');
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        $title = '403 - Không có quyền truy cập';
        $userRole = $role ?: 'Chưa xác định';
        $dashboardUrl = ($role === 'Admin') ? url('/admin/index.php') : url('/user/index.php');

        require_once __DIR__ . '/../includes/header.php';
        ?>
        <section class="error-card">
            <div class="error-badge">403 FORBIDDEN</div>
            <h1>Không có quyền truy cập</h1>
            <p class="error-desc">
                Bạn đang đăng nhập với vai trò <strong><?= e($userRole) ?></strong>.
                Chức năng này yêu cầu quyền: <strong><?= e(implode(' hoặc ', $roles)) ?></strong>.
            </p>
            <div class="actions" style="justify-content: center;">
                <a class="button" href="<?= e($dashboardUrl) ?>">Về trang làm việc của bạn</a>
                <a class="button secondary" href="<?= url('/auth/logout.php') ?>">Đăng xuất</a>
            </div>
        </section>
        <?php
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
}

function currentUser(): ?array
{
    if (empty($_SESSION['MaNV'])) {
        return null;
    }

    return [
        'MaNV' => (int)$_SESSION['MaNV'],
        'HoTen' => (string)($_SESSION['HoTen'] ?? ''),
        'VaiTro' => (string)($_SESSION['VaiTro'] ?? ''),
        'TenDangNhap' => (string)($_SESSION['TenDangNhap'] ?? ''),
    ];
}
