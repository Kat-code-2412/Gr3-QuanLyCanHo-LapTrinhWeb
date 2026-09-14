<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../auth/guard.php';

$maNv = $_SESSION['MaNV'] ?? null;
$vaiTro = $_SESSION['VaiTro'] ?? 'NhanVien';
$hoTen = $_SESSION['HoTen'] ?? 'Nhân viên';
$loggedIn = !empty($maNv);

$currentUri = $_SERVER['REQUEST_URI'] ?? '';

// Đếm số thông báo chưa đọc
$unreadCount = 0;
if ($loggedIn) {
    try {
        $pdoNotif = require __DIR__ . '/../config/database.php';
        $stmtNotif = $pdoNotif->prepare("SELECT COUNT(*) FROM thongbao WHERE (MaNV = ? OR MaNV IS NULL) AND DaDoc = 0");
        $stmtNotif->execute([(int)$maNv]);
        $unreadCount = (int)$stmtNotif->fetchColumn();
    } catch (Throwable $t) {
        $unreadCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Hệ Thống Quản Lý Căn Hộ Dịch Vụ') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css?v=' . (file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time())) ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/module2.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/module2-lightbox.css') ?>">
</head>
<body class="app-body">
    <div class="app-layout">
        <!-- OVERLAY BẢO VỆ CHO MOBILE SIDEBAR -->
        <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

        <!-- SIDEBAR DỌC BÊN TRÁI -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- KHU VỰC NỘI DUNG CHÍNH BÊN PHẢI -->
        <div class="app-main-wrapper">
            <!-- HEADER CỐ ĐỊNH TRÊN CÙNG -->
            <header class="app-header">
                <div class="header-left">
                    <button type="button" class="btn-sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" title="Ẩn/Hiện Sidebar">
                        <?= svgIcon('menu', '', 18) ?>
                    </button>
                    <div class="header-breadcrumb">
                        <span style="color: #64748b;">Hệ Thống</span>
                        <span style="color: #cbd5e1;">/</span>
                        <span style="font-weight: 600; color: #0f172a;"><?= e($title ?? 'Dashboard') ?></span>
                    </div>
                </div>

                <div class="header-right">
                    <!-- CHUÔNG THÔNG BÁO -->
                    <a href="<?= url('/admin/thong-bao/index.php') ?>" class="header-icon-btn" title="Trung tâm thông báo">
                        <?= svgIcon('bell', '', 18) ?>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge">
                                <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- USER INFO & ROLE BADGE WITH DROPDOWN -->
                    <div class="user-dropdown-container" style="position: relative;">
                        <div class="user-profile-badge" onclick="toggleUserDropdown(event)" style="cursor: pointer;" title="Tùy chọn tài khoản">
                            <div class="user-avatar-circle" style="<?= !empty($_SESSION['Avatar']) ? 'overflow: hidden; padding: 0; background: transparent;' : '' ?>">
                                <?php if (!empty($_SESSION['Avatar'])): ?>
                                    <img src="<?= e(url($_SESSION['Avatar'])) ?>" alt="<?= e($hoTen) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <?= mb_substr($hoTen, 0, 1, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-info-text">
                                <span class="user-name"><?= e($hoTen) ?></span>
                                <span class="user-role <?= ($vaiTro === 'Admin') ? 'role-admin' : 'role-staff' ?>">
                                    <?= ($vaiTro === 'Admin') ? 'Chủ nhà (Admin)' : 'Nhân viên' ?>
                                </span>
                            </div>
                            <span style="color: #94a3b8; margin-left: 0.25rem; display: flex; align-items: center;"><?= svgIcon('chevron-down', '', 14) ?></span>
                        </div>

                        <!-- DROPDOWN MENU -->
                        <div id="userDropdownMenu" class="user-dropdown-menu" style="display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #ffffff; border-radius: 10px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1); border: 1px solid #e2e8f0; min-width: 230px; z-index: 1000; overflow: hidden;">
                            <div style="padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc; display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #e2e8f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #1e293b;">
                                    <?php if (!empty($_SESSION['Avatar'])): ?>
                                        <img src="<?= e(url($_SESSION['Avatar'])) ?>" alt="<?= e($hoTen) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?= mb_substr($hoTen, 0, 1, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0; flex: 1;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($hoTen) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($_SESSION['TenDangNhap'] ?? '') ?> • <?= ($vaiTro === 'Admin') ? 'Chủ nhà' : 'Nhân viên' ?></div>
                                </div>
                            </div>
                            <div style="padding: 0.35rem 0;">
                                <a href="<?= url('/auth/profile.php') ?>" style="display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 1rem; color: #334155; text-decoration: none; font-size: 0.875rem; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                                    <?= svgIcon('user', '', 16) ?> <span>Cài đặt thông tin cá nhân</span>
                                </a>
                                <a href="<?= url('/auth/change-password.php') ?>" style="display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 1rem; color: #334155; text-decoration: none; font-size: 0.875rem; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                                    <?= svgIcon('lock', '', 16) ?> <span>Đổi mật khẩu</span>
                                </a>
                                <?php if ($vaiTro === 'Admin'): ?>
                                    <a href="<?= url('/admin/nhan-vien/index.php') ?>" style="display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 1rem; color: #334155; text-decoration: none; font-size: 0.875rem; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                                        <?= svgIcon('shield', '', 16) ?> <span>Quản lý nhân sự</span>
                                    </a>
                                    <a href="<?= url('/admin/audit-log/index.php') ?>" style="display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 1rem; color: #334155; text-decoration: none; font-size: 0.875rem; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                                        <?= svgIcon('audit', '', 16) ?> <span>Nhật ký Audit Log</span>
                                    </a>
                                <?php endif; ?>
                                <hr style="margin: 0.35rem 0; border: none; border-top: 1px solid #f1f5f9;">
                                <button type="button" onclick="confirmLogout()" style="width: 100%; display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 1rem; color: #ef4444; background: none; border: none; font-size: 0.875rem; cursor: pointer; text-align: left; transition: background 0.15s;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                    <?= svgIcon('logout', '', 16) ?> <span>Đăng xuất</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- NỘI DUNG CHÍNH CÓ SCROLL DỌC -->
            <main class="app-content-body">
                <?php if ($flashSuccess = getFlash('success')): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon"><?= svgIcon('check', '', 18) ?></span>
                        <div class="alert-text"><?= e($flashSuccess) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($flashError = getFlash('error')): ?>
                    <div class="alert alert-danger">
                        <span class="alert-icon"><?= svgIcon('x', '', 18) ?></span>
                        <div class="alert-text"><?= e($flashError) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($flashWarning = getFlash('warning')): ?>
                    <div class="alert alert-warning">
                        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 18) ?></span>
                        <div class="alert-text"><?= e($flashWarning) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($flashInfo = getFlash('info')): ?>
                    <div class="alert alert-info">
                        <span class="alert-icon"><?= svgIcon('info', '', 18) ?></span>
                        <div class="alert-text"><?= e($flashInfo) ?></div>
                    </div>
                <?php endif; ?>
