<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function appBaseUrl(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
    $appDir = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));

    if ($docRoot !== '' && stripos($appDir, $docRoot) === 0) {
        $base = rtrim(substr($appDir, strlen($docRoot)), '/');
        return $base;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $marker = '/QuanLyCanHo-Web';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        $base = substr($scriptName, 0, $pos + strlen($marker));
        return $base;
    }

    $base = '';
    return $base;
}

function url(string $path = ''): string
{
    $base = appBaseUrl();
    if ($path === '' || $path === '/') {
        return ($base === '') ? '/' : $base . '/';
    }
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

function redirect(string $url): never
{
    if (str_starts_with($url, '/')) {
        $base = appBaseUrl();
        if ($base !== '' && !str_starts_with($url, $base . '/')) {
            $url = $base . $url;
        }
    }
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pullFlashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verifyCsrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals((string)($_SESSION['_csrf'] ?? ''), $token)) {
        http_response_code(419);
        exit('Yêu cầu không hợp lệ (CSRF).');
    }
}

function setOldInput(array $input): void
{
    $_SESSION['_old'] = $input;
}

function pullOldInput(): array
{
    $old = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
    return is_array($old) ? $old : [];
}
