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
 * Bắt buộc người dùng có vai trò Admin. Nếu không phải Admin sẽ chuyển hướng hoặc thông báo lỗi.
 */
function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['VaiTro'] ?? '') !== 'Admin') {
        setFlash('error', 'Bạn không có quyền thực hiện thao tác này (Chỉ dành cho Admin).');
        redirect('/user/index.php');
    }
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
