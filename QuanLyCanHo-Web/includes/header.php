<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../auth/guard.php';

$maNv = $_SESSION['MaNV'] ?? null;
$vaiTro = $_SESSION['VaiTro'] ?? null;
$loggedIn = !empty($maNv);

$adminMenus = [
    ['label' => 'Dashboard', 'url' => '/admin/index.php'],
    ['label' => 'Căn hộ', 'url' => '/admin/can-ho/index.php'],
    ['label' => 'Khách thuê', 'url' => '/admin/khach-thue/index.php'],
    ['label' => 'Hợp đồng', 'url' => '/admin/hop-dong/index.php'],
    ['label' => 'Hóa đơn', 'url' => '/admin/hoa-don/index.php'],
    ['label' => 'Bảo trì', 'url' => '/admin/bao-tri/index.php'],
];

$staffMenus = [
    ['label' => 'Dashboard', 'url' => '/user/index.php'],
    ['label' => 'Căn hộ', 'url' => '/user/can-ho/index.php'],
    ['label' => 'Khách thuê', 'url' => '/user/khach-thue/index.php'],
    ['label' => 'Hợp đồng', 'url' => '/user/hop-dong/index.php'],
    ['label' => 'Hóa đơn', 'url' => '/user/hoa-don/index.php'],
    ['label' => 'Bảo trì', 'url' => '/user/bao-tri/index.php'],
];

$menuItems = ($vaiTro === 'Admin') ? $adminMenus : $staffMenus;
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Quản lý căn dịch vụ') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="navbar-header">
        <nav class="container nav-container">
            <div class="brand">
                <a href="<?= ($vaiTro === 'Admin') ? '/admin/index.php' : '/user/index.php' ?>">
                    🏢 <span>Quản Lý Căn Dịch Vụ</span>
                </a>
            </div>

            <?php if ($loggedIn): ?>
                <ul class="menu-list">
                    <?php foreach ($menuItems as $item): 
                        $isActive = (str_contains($currentUri, parse_url($item['url'], PHP_URL_PATH)));
                    ?>
                        <li>
                            <a href="<?= e($item['url']) ?>" class="<?= $isActive ? 'active' : '' ?>">
                                <?= e($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="user-box">
                    <span class="user-role-badge role-<?= strtolower(e((string)$vaiTro)) ?>">
                        👤 <?= e((string)$vaiTro) ?>
                    </span>
                    <a href="/auth/logout.php" class="btn-logout">Đăng xuất</a>
                </div>
            <?php else: ?>
                <div class="guest-box">
                    <a href="/auth/login.php" class="btn-login">Đăng nhập</a>
                </div>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container main-content">
        <?php if ($flashSuccess = getFlash('success')): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✓</span>
                <div class="alert-text"><?= e($flashSuccess) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($flashError = getFlash('error')): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">✕</span>
                <div class="alert-text"><?= e($flashError) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($flashWarning = getFlash('warning')): ?>
            <div class="alert alert-warning">
                <span class="alert-icon">⚠️</span>
                <div class="alert-text"><?= e($flashWarning) ?></div>
            </div>
        <?php endif; ?>
