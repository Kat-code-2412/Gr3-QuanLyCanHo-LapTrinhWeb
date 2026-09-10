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
        'Đã tiếp nhận'   => 'badge-info',
        'Đang xử lý'     => 'badge-warning',
        'Hoàn thành'      => 'badge-success',
        'Đang hiệu lực'   => 'badge-success',
        'Sắp hết hạn'    => 'badge-warning',
        'Hết hạn'         => 'badge-danger',
        'Đã thanh lý'     => 'badge-secondary',
        'Chưa check-in'   => 'badge-warning',
        'Đã check-out'    => 'badge-secondary',
        'Trống'           => 'badge-success',
        'Đang thuê'       => 'badge-success',
        'Đã trả phòng'   => 'badge-secondary',
        'Chưa thuê'       => 'badge-warning',
        'Bảo trì'         => 'badge-warning',
    ];

    $badgeClass = $classMap[$status] ?? 'badge-secondary';
    return sprintf('<span class="badge %s">%s</span>', e($badgeClass), e($status));
}

/**
 * Xử lý upload file an toàn
 */
function uploadFile(array $file, string $subFolder = 'files'): ?string
{
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/' . trim($subFolder, '/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return '/uploads/' . trim($subFolder, '/') . '/' . $fileName;
    }

    return null;
}

/**
 * Tạo URL chuẩn theo môi trường (XAMPP subfolder hoặc standalone host)
 */
function url(string $path = ''): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    $path = '/' . ltrim($path, '/');
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $projectFolder = '/QuanLyCanHo-Web';
    $projectPosition = strpos($scriptName, $projectFolder);

    if ($projectPosition !== false && !str_starts_with($path, $projectFolder)) {
        $basePath = substr($scriptName, 0, $projectPosition + strlen($projectFolder));
        return $basePath . $path;
    }

    return $path;
}

/**
 * Chuyển hướng URL nhanh
 */
function redirect(string $path): void 
{
    header('Location: ' . url($path));
    exit;
}

/**
 * CSRF Protection Token
 */
if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

/**
 * Xác thực CSRF Token cho form POST
 */
if (!function_exists('verifyCsrf')) {
    function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!is_string($token) || empty($_SESSION['_csrf']) || !hash_equals((string)$_SESSION['_csrf'], $token)) {
            http_response_code(419);
            exit('Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.');
        }
    }
}

/**
 * Alias cho setFlash
 */
if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        setFlash($type, $message);
    }
}