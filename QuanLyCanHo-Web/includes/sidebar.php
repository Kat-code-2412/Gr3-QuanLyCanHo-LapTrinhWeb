<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/guard.php';

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$userRole = $_SESSION['VaiTro'] ?? 'NhanVien';
$isAdmin = ($userRole === 'Admin');

function isMenuActive(string $path, string $currentUri): bool {
    return str_contains($currentUri, $path);
}
?>

<!-- SIDEBAR DỌC BÊN TRÁI -->
<aside class="app-sidebar" id="appSidebar">
    <!-- LOGO & TÊN HỆ THỐNG -->
    <div class="sidebar-brand">
        <a href="<?= url($isAdmin ? '/admin/index.php' : '/user/index.php') ?>" class="brand-link">
            <div class="brand-icon-box">
                <?= svgIcon('building', '', 20) ?>
            </div>
            <div class="brand-text">
                <span class="brand-name">Căn Hộ Dịch Vụ</span>
                <span class="brand-sub">Quản Lý & Vận Hành</span>
            </div>
        </a>
    </div>

    <!-- MENU ĐIỀU HƯỚNG DỌC -->
    <nav class="sidebar-nav">
        <!-- DASHBOARD -->
        <div class="nav-section-title">TỔNG QUAN</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="<?= url($isAdmin ? '/admin/index.php' : '/user/index.php') ?>" 
                   class="nav-link <?= (str_ends_with(parse_url($currentUri, PHP_URL_PATH), '/index.php') && (str_contains($currentUri, '/admin/index.php') || str_contains($currentUri, '/user/index.php'))) ? 'active' : '' ?>">
                    <?= svgIcon('home', 'nav-icon', 18) ?>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- NHÓM QUẢN LÝ -->
        <?php 
        $canCanHo = $isAdmin || hasPermission('CANHO_MANAGE');
        $canKhachThue = $isAdmin || hasPermission('KHACHTHUE_MANAGE');
        $canHopDong = $isAdmin || hasPermission('HOPDONG_MANAGE');
        if ($canCanHo || $canKhachThue || $canHopDong): 
        ?>
        <div class="nav-section-title">QUẢN LÝ</div>
        <ul class="nav-menu">
            <?php if ($canCanHo): ?>
            <li class="nav-item">
                <a href="<?= url('/admin/can-ho/index.php') ?>" class="nav-link <?= isMenuActive('/admin/can-ho/', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('door', 'nav-icon', 18) ?>
                    <span class="nav-label">Hệ Thống Căn Hộ</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($canKhachThue): ?>
            <li class="nav-item">
                <a href="<?= url('/admin/khach-thue/index.php') ?>" class="nav-link <?= isMenuActive('/admin/khach-thue/', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('users', 'nav-icon', 18) ?>
                    <span class="nav-label">Khách thuê</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($canHopDong): ?>
            <li class="nav-item">
                <a href="<?= url('/admin/hop-dong/index.php') ?>" class="nav-link <?= isMenuActive('/admin/hop-dong/', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('contract', 'nav-icon', 18) ?>
                    <span class="nav-label">Hợp đồng</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <!-- NHÓM TÀI CHÍNH -->
        <?php 
        $canDienNuoc = $isAdmin || hasPermission('DIENNUOC_MANAGE');
        $canHoaDon = $isAdmin || hasPermission('HOADON_MANAGE');
        if ($canDienNuoc || $canHoaDon): 
        ?>
        <div class="nav-section-title">TÀI CHÍNH</div>
        <ul class="nav-menu">
            <?php if ($canDienNuoc): ?>
            <li class="nav-item">
                <a href="<?= url('/admin/dien-nuoc/index.php') ?>" class="nav-link <?= isMenuActive('/admin/dien-nuoc/', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('electric', 'nav-icon', 18) ?>
                    <span class="nav-label">Điện nước</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($canHoaDon): ?>
            <li class="nav-item">
                <a href="<?= url('/admin/hoa-don/index.php') ?>" class="nav-link <?= isMenuActive('/admin/hoa-don/', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('invoice', 'nav-icon', 18) ?>
                    <span class="nav-label">Hóa đơn</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <!-- NHÓM VẬN HÀNH -->
        <div class="nav-section-title">VẬN HÀNH</div>
        <ul class="nav-menu">
            <?php if ($isAdmin || hasPermission('BAOTRI_MANAGE')): ?>
            <li class="nav-item">
                <a href="<?= url('/admin/bao-tri/index.php') ?>" class="nav-link <?= isMenuActive('/admin/bao-tri/', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('tool', 'nav-icon', 18) ?>
                    <span class="nav-label">Bảo trì sự cố</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="<?= url('/admin/thong-bao/index.php') ?>" class="nav-link <?= isMenuActive('/admin/thong-bao/', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('bell', 'nav-icon', 18) ?>
                    <span class="nav-label">Thông báo</span>
                </a>
            </li>
        </ul>

        <!-- NHÓM BÁO CÁO -->
        <?php if ($isAdmin || hasPermission('BAOCAO_VIEW')): ?>
        <div class="nav-section-title">BÁO CÁO & THỐNG KÊ</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="<?= url('/admin/bao-cao/doanh-thu.php') ?>" class="nav-link <?= isMenuActive('/admin/bao-cao/doanh-thu.php', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('trend-up', 'nav-icon', 18) ?>
                    <span class="nav-label">Báo cáo doanh thu</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('/admin/bao-cao/cong-no.php') ?>" class="nav-link <?= isMenuActive('/admin/bao-cao/cong-no.php', $currentUri) ? 'active' : '' ?>">
                    <?= svgIcon('trend-down', 'nav-icon', 18) ?>
                    <span class="nav-label">Báo cáo công nợ</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- NHÓM HỆ THỐNG (DÀNH CHO ADMIN) -->
        <?php if ($isAdmin): ?>
            <div class="nav-section-title">HỆ THỐNG</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="<?= url('/admin/nhan-vien/index.php') ?>" class="nav-link <?= isMenuActive('/admin/nhan-vien/', $currentUri) ? 'active' : '' ?>">
                        <?= svgIcon('shield', 'nav-icon', 18) ?>
                        <span class="nav-label">Quản lý nhân viên</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= url('/admin/audit-log/index.php') ?>" class="nav-link <?= isMenuActive('/admin/audit-log/', $currentUri) ? 'active' : '' ?>">
                        <?= svgIcon('audit', 'nav-icon', 18) ?>
                        <span class="nav-label">Nhật ký Audit Log</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>

    <!-- FOOTER SIDEBAR VỚI NÚT ĐĂNG XUẤT -->
    <div class="sidebar-footer">
        <button type="button" class="btn-sidebar-logout" onclick="confirmLogout()">
            <?= svgIcon('logout', 'nav-icon', 18) ?>
            <span>Đăng xuất</span>
        </button>
    </div>
</aside>

<!-- MODAL XÁC NHẬN ĐĂNG XUẤT -->
<div id="logoutModal" class="logout-modal" style="display: none;">
    <div class="logout-modal-backdrop" onclick="closeLogoutModal()"></div>
    <div class="logout-modal-content">
        <div style="width: 48px; height: 48px; margin: 0 auto 1rem; border-radius: 12px; background: #fef2f2; color: #ef4444; display: flex; align-items: center; justify-content: center;">
            <?= svgIcon('logout', '', 24) ?>
        </div>
        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">
            Xác nhận đăng xuất
        </h3>
        <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.5;">
            Bạn có chắc chắn muốn kết thúc phiên làm việc hiện tại trên hệ thống?
        </p>
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
            <button type="button" class="btn btn-outline" onclick="closeLogoutModal()" style="padding: 0.5rem 1.25rem;">
                Hủy bỏ
            </button>
            <a href="<?= url('/auth/logout.php') ?>" class="btn btn-danger" style="padding: 0.5rem 1.25rem;">
                Đăng xuất
            </a>
        </div>
    </div>
</div>

<script>
function confirmLogout() {
    document.getElementById('logoutModal').style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}
</script>
