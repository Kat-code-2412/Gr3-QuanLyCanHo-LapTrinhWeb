<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kiểm tra xem người dùng đã đăng nhập chưa
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['MaNV']);
}

/**
 * Bắt buộc người dùng phải đăng nhập. Nếu chưa đăng nhập sẽ chuyển hướng tới trang login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Vui lòng đăng nhập để truy cập hệ thống.');
        redirect('/auth/login.php');
    }
}

/**
 * Bắt buộc người dùng có vai trò cụ thể (VD: 'Admin', 'NhanVien')
 */
function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['VaiTro'] ?? '') !== $role) {
        setFlash('error', 'Bạn không có quyền thực hiện thao tác này (Chỉ dành cho ' . e($role) . ').');
        redirect('/user/index.php');
    }
}

/**
 * Bắt buộc người dùng có vai trò Admin. Nếu không phải Admin sẽ chuyển hướng hoặc thông báo lỗi.
 */
function requireAdmin(): void
{
    requireRole('Admin');
}

/**
 * Lấy Mã Nhân Viên đang đăng nhập
 */
function currentUserId(): ?int
{
    return isset($_SESSION['MaNV']) ? (int)$_SESSION['MaNV'] : null;
}

/**
 * Lấy Vai Trò đang đăng nhập ('Admin' hoặc 'NhanVien')
 */
function currentUserRole(): string
{
    return $_SESSION['VaiTro'] ?? '';
}

function isCustomerLoggedIn(): bool
{
    return !empty($_SESSION['MaKhach']);
}

function requireCustomerLogin(): void
{
    if (!isCustomerLoggedIn()) {
        setFlash('error', 'Vui lòng đăng nhập tài khoản khách hàng.');
        redirect('/auth/customer-login.php');
    }
}

function currentCustomerId(): ?int
{
    return isset($_SESSION['MaKhach']) ? (int)$_SESSION['MaKhach'] : null;
}

/**
 * Tự động kiểm tra quyền truy cập theo từng Route URL trên Server
 */
function checkRoutePermission(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // 1. Tuyến đường quản trị /admin/ -> Chỉ duy nhất Admin được phép
    if (str_contains($script, '/admin/')) {
        requireAdmin();
    }
    // 2. Tuyến đường tác nghiệp /user/ -> Nhân viên và Admin
    elseif (str_contains($script, '/user/')) {
        requireLogin();
        // Chặn khách hàng không thể dùng session khách để vào khu vực nhân viên
        if (isCustomerLoggedIn() && !isLoggedIn()) {
            setFlash('error', 'Tài khoản khách hàng không có quyền truy cập khu vực nội bộ.');
            redirect('/khach-hang/index.php');
        }
    }
    // 3. Tuyến đường khách thuê /khach-hang/ -> Bắt buộc tài khoản khách hàng
    elseif (str_contains($script, '/khach-hang/')) {
        requireCustomerLogin();
    }
}

// Tự động bảo vệ tất cả URL hệ thống
checkRoutePermission();
