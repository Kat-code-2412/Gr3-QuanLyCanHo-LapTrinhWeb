<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!ob_get_level()) {
    ob_start();
}

/**
 * XSS Clean HTML Escape
 */
function e(string|int|float|null $value): string
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
        return "0\u{00A0}đ";
    }
    return number_format((float)$amount, 0, ',', '.') . "\u{00A0}đ";
}

/**
 * Chuẩn hóa đơn giá dịch vụ (Điện, Nước, Xe máy, Ô tô, Wifi, Vệ sinh)
 * Nếu người dùng nhập hoặc dữ liệu lưu theo hệ nghìn đồng (VD: 4 thay vì 4.000, 100 thay vì 100.000, 50 thay vì 50.000)
 * thì tự động nhân với 1.000 để chuẩn hóa về đơn vị VNĐ.
 */
function normalizeServiceFee(float|int|string|null $fee, string $type = 'other'): float
{
    if ($fee === null || $fee === '') {
        return 0.0;
    }

    if (is_numeric($fee)) {
        $val = (float)$fee;
    } elseif (is_string($fee)) {
        $clean = trim($fee);
        // Nếu chuỗi là định dạng số thập phân từ MySQL (VD: "4000.00", "100000.00")
        if (preg_match('/^\d+\.\d{1,2}$/', $clean)) {
            $val = (float)$clean;
        } else {
            // Chuỗi do người dùng nhập (VD: "4.000", "100.000", "1.200.000", "3,5")
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            $val = (float)$clean;
        }
    } else {
        $val = (float)$fee;
    }

    if ($val <= 0) {
        return 0.0;
    }

    $type = strtolower($type);
    if ($type === 'dien' || $type === 'giadien') {
        // Điện: thông thường từ 2.000 - 10.000 đ/kWh. Nếu < 100 thì nhập theo hệ nghìn (VD: 4 -> 4.000)
        if ($val < 100) {
            $val *= 1000;
        }
    } elseif ($type === 'oto' || $type === 'giaoto') {
        // Ô tô: thông thường từ 500.000 - 3.000.000 đ/tháng. Nếu < 5000 thì nhập theo hệ nghìn (VD: 1200 -> 1.200.000)
        if ($val < 5000) {
            $val *= 1000;
        }
    } else {
        // Nước, Xe máy, Internet, Vệ sinh: thông thường từ 10.000 - 500.000 đ/tháng.
        // Nếu < 1000 thì nhập theo hệ nghìn (VD: 100 -> 100.000, 50 -> 50.000, 40 -> 40.000)
        if ($val < 1000) {
            $val *= 1000;
        }
    }

    return $val;
}


/**
 * Định dạng ngày YYYY-MM-DD -> DD/MM/YYYY
 */
function formatDate(?string $dateStr): string
{
    if (empty($dateStr) || str_starts_with($dateStr, '0000-00-00') || $dateStr === '-') {
        return '-';
    }
    $timestamp = strtotime($dateStr);
    if ($timestamp === false || $timestamp <= 0) {
        return '-';
    }
    return date('d/m/Y', $timestamp);
}

/**
 * Định dạng ngày giờ YYYY-MM-DD HH:MM:SS -> DD/MM/YYYY HH:MM
 */
function formatDateTime(?string $dateTimeStr): string
{
    if (empty($dateTimeStr) || str_starts_with($dateTimeStr, '0000-00-00') || $dateTimeStr === '-') {
        return '-';
    }
    $timestamp = strtotime($dateTimeStr);
    if ($timestamp === false || $timestamp <= 0) {
        return '-';
    }
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Chuẩn hóa số phòng (loại bỏ tiền tố PP trùng lặp, đảm bảo hiển thị đúng dạng P101)
 */
function formatSoPhong(?string $soPhong): string
{
    $sp = trim((string)$soPhong);
    if ($sp === '') {
        return '';
    }
    // Nếu bắt đầu bằng PP... thì thu gọn về 1 chữ P (ví dụ PP101 -> P101)
    return preg_replace('/^P+/i', 'P', $sp);
}

/**
 * Đồng bộ trạng thái các hóa đơn chưa thanh toán đã quá 30 ngày.
 */
function markOverdueInvoices(PDO $pdo): int
{
    $stmt = $pdo->prepare("UPDATE HoaDon
        SET TrangThai = 'Quá hạn'
        WHERE TrangThai = 'Chưa TT'
          AND NgayTao < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();

    return $stmt->rowCount();
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
        'Đã TT'           => 'badge-success',
        'Chưa TT'         => 'badge-warning',
        'Quá hạn'         => 'badge-danger',
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
    $targetUrl = url($path);
    if (!headers_sent()) {
        header('Location: ' . $targetUrl);
        exit;
    }
    echo '<script>window.location.href=' . json_encode($targetUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . e($targetUrl) . '"></noscript>';
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
        $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = (string)($_SESSION['_csrf'] ?? $_SESSION['csrf_token'] ?? '');
        if (!is_string($token) || $token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
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

/**
 * Render clean SVG Icons (Lucide/Heroicons standard) - No AI emojis
 */
function svgIcon(string $name, string $class = '', int $size = 18): string
{
    $icons = [
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'building' => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M16 14h.01"/>',
        'door' => '<path d="M18 20V6a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v14"/><path d="M2 20h20"/><path d="M14 12v.01"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'contract' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'electric' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'water' => '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
        'motorcycle' => '<circle cx="5" cy="16" r="3"/><circle cx="19" cy="16" r="3"/><path d="M5 16h2l3-6 4 1 2 5h3"/><path d="M12 7h2"/>',
        'invoice' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
        'payment' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
        'tool' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'chart' => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
        'trend-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'trend-down' => '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'audit' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/>',
        'plus' => '<line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>',
        'edit' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/>',
        'arrow-right' => '<line x1="5" x2="19" y1="12" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'arrow-left' => '<line x1="19" x2="5" y1="12" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
        'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'menu' => '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'x' => '<line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'sparkles' => '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'refresh' => '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
        'key' => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
        'briefcase' => '<rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'id-card' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>'
    ];

    $inner = $icons[$name] ?? $icons['info'];
    $cls = $class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '';
    return sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon%s">%s</svg>',
        $size,
        $size,
        $cls,
        $inner
    );
}

/**
 * Lấy 2 chữ cái đầu viết hoa từ họ tên (VD: Vũ Thị Bích Ngọc -> VN)
 */
function getInitials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'KT';
    }
    $words = preg_split('/\s+/u', $name);
    if (empty($words)) {
        return 'KT';
    }
    if (count($words) === 1) {
        return mb_strtoupper(mb_substr($words[0], 0, 2, 'UTF-8'), 'UTF-8');
    }
    $first = mb_substr($words[0], 0, 1, 'UTF-8');
    $last = mb_substr(end($words), 0, 1, 'UTF-8');
    return mb_strtoupper($first . $last, 'UTF-8');
}