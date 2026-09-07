<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * XSS Clean HTML Escape
 */
function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Thiết lập thông báo Flash (success, error, warning, info)
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Lấy và xóa thông báo Flash
 */
function getFlash(string $type): ?string
{
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Định dạng số tiền (VD: 4.500.000 đ)
 */
function formatMoney($amount): string
{
    if ($amount === null || $amount === '') {
        return '0 đ';
    }
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

/**
 * Định dạng ngày YYYY-MM-DD -> DD/MM/YYYY
 */
function formatDate(?string $dateStr): string
{
    if (empty($dateStr)) {
        return '-';
    }
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) {
        return $dateStr;
    }
    return date('d/m/Y', $timestamp);
}

/**
 * Định dạng ngày giờ YYYY-MM-DD HH:MM:SS -> DD/MM/YYYY HH:MM
 */
function formatDateTime(?string $dateTimeStr): string
{
    if (empty($dateTimeStr)) {
        return '-';
    }
    $timestamp = strtotime($dateTimeStr);
    if ($timestamp === false) {
        return $dateTimeStr;
    }
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Hiển thị Badge trạng thái Bảo trì hoặc Hợp đồng
 */
function renderStatusBadge(string $status): string
{
    $classMap = [
        'Đã tiếp nhận' => 'badge-info',
        'Đang xử lý'   => 'badge-warning',
        'Hoàn thành'    => 'badge-success',
        'Đang hiệu lực' => 'badge-success',
        'Hết hạn'       => 'badge-danger',
        'Đã thanh lý'   => 'badge-secondary',
        'Trống'         => 'badge-success',
        'Đang thuê'     => 'badge-info',
        'Bảo trì'       => 'badge-warning',
    ];

    $badgeClass = $classMap[$status] ?? 'badge-secondary';
    return sprintf('<span class="badge %s">%s</span>', e($badgeClass), e($status));
}

/**
 * Chuyển hướng URL nhanh
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
