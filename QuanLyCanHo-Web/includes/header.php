<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$maNv = $_SESSION['MaNV'] ?? null;
$vaiTro = $_SESSION['VaiTro'] ?? null;
$loggedIn = !empty($maNv);

$adminMenus = [
    ['label' => 'Dashboard', 'url' => '/admin/index.php'],
    ['label' => 'Nhân viên', 'url' => '/admin/nhan-vien/index.php'],
    ['label' => 'Căn hộ', 'url' => '/admin/can-ho/index.php'],
    ['label' => 'Khách thuê', 'url' => '/admin/khach-thue/index.php'],
    ['label' => 'Hợp đồng', 'url' => '/admin/hop-dong/index.php'],
    ['label' => 'Hóa đơn', 'url' => '/admin/hoa-don/index.php'],
    ['label' => 'Bảo trì', 'url' => '/admin/bao-tri/index.php'],
    ['label' => 'Báo cáo', 'url' => '/admin/index.php#reports'],
];

$staffMenus = [
    ['label' => 'Dashboard', 'url' => '/user/index.php'],
    ['label' => 'Căn hộ', 'url' => '/user/can-ho/index.php'],
    ['label' => 'Hợp đồng', 'url' => '/user/hop-dong/index.php'],
    ['label' => 'Hóa đơn', 'url' => '/user/hoa-don/index.php'],
    ['label' => 'Khách thuê', 'url' => '/user/khach-thue/index.php'],
    ['label' => 'Bảo trì', 'url' => '/user/bao-tri/index.php'],
];

$menuItems = ($vaiTro === 'Admin') ? $adminMenus : $staffMenus;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Quản lý căn dịch vụ', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="brand">
                <a href="/">Quản lý căn dịch vụ</a>
            </div>

            <?php if ($loggedIn): ?>
                <ul class="menu">
                    <?php foreach ($menuItems as $item): ?>
                        <li>
                            <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="user-box">
                    <span><?= htmlspecialchars((string)($vaiTro ?? 'User'), ENT_QUOTES, 'UTF-8') ?></span>
                    <a href="/auth/logout.php">Đăng xuất</a>
                </div>
            <?php else: ?>
                <div class="guest-box">
                    <a href="/auth/login.php">Đăng nhập</a>
                </div>
            <?php endif; ?>
        </nav>
    </header>

    <main>
