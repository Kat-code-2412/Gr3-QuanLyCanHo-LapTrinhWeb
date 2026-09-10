<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('appUrl')) {
    function appUrl(string $path = ''): string {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#^(.*?)(?:/(?:admin|user|auth|khach-hang))(?:/|$)#i', $script, $matches)) {
            $base = rtrim($matches[1], '/');
        } else {
            $base = rtrim(dirname($script), '/');
        }
        return ($base === '/' || $base === '.') ? '/' . ltrim($path, '/') : $base . '/' . ltrim($path, '/');
    }
}

function module2_csrf_token(): string {
    if (empty($_SESSION['module2_csrf'])) $_SESSION['module2_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['module2_csrf'];
}

function module2_require_csrf(): void {
    $token = (string)($_POST['csrf_token'] ?? '');
    $saved = (string)($_SESSION['module2_csrf'] ?? '');
    if ($saved === '' || $token === '' || !hash_equals($saved, $token)) {
        http_response_code(419);
        exit('CSRF token không hợp lệ.');
    }
}

function module2_clean_short_html(string $html): string {
    $html = strip_tags($html, '<b><strong><i><em><u><br><p><ul><ol><li>');
    $html = preg_replace('/\s+/', ' ', $html) ?? $html;
    return trim($html);
}

function module2_pagination(int $page, int $totalPages, array $params = []): string {
    if ($totalPages <= 1) return '';
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $q = $params; $q['page'] = $i;
        $html .= '<a class="' . ($i === $page ? 'active' : '') . '" href="' . e('?' . http_build_query($q)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

function module2_upload_images(array $files, int $maCanHo, PDO $pdo, string $uploadDir, string $publicPrefix): array {
    if (!isset($files['name']) || !is_array($files['name'])) return [];
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Không tạo được thư mục upload.');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploaded = [];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Có ảnh upload bị lỗi.');
        if (!is_uploaded_file($tmp)) throw new RuntimeException('File upload không hợp lệ.');
        if ((int)$files['size'][$i] > 5 * 1024 * 1024) throw new RuntimeException('Mỗi ảnh tối đa 5MB.');
        $mime = $finfo->file($tmp);
        if (!isset($allowed[$mime])) throw new RuntimeException('Chỉ hỗ trợ JPG, PNG, WEBP.');
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('Không lưu được ảnh.');
        $stmt = $pdo->prepare('INSERT INTO CanHo_Anh (MaCanHo, DuongDan, LaAnhDaiDien, ThuTu) VALUES (:canho,:path,0,0)');
        $stmt->execute([':canho' => $maCanHo, ':path' => rtrim($publicPrefix, '/') . '/' . $name]);
        $uploaded[] = (int)$pdo->lastInsertId();
    }
    return $uploaded;
}

function module2_repair_cover(PDO $pdo, int $maCanHo): void {
    $stmt = $pdo->prepare('SELECT MaAnh FROM CanHo_Anh WHERE MaCanHo=:id ORDER BY LaAnhDaiDien DESC, ThuTu ASC, MaAnh ASC');
    $stmt->execute([':id' => $maCanHo]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return;
    $hasCover = $pdo->prepare('SELECT MaAnh FROM CanHo_Anh WHERE MaCanHo=:id AND LaAnhDaiDien=1 LIMIT 1');
    $hasCover->execute([':id' => $maCanHo]);
    $cover = $hasCover->fetchColumn();
    if ($cover !== false) {
        $pdo->prepare('UPDATE CanHo_Anh SET LaAnhDaiDien=0 WHERE MaCanHo=:id AND MaAnh<>:cover')->execute([':id' => $maCanHo, ':cover' => (int)$cover]);
        return;
    }
    $pdo->prepare('UPDATE CanHo_Anh SET LaAnhDaiDien=0 WHERE MaCanHo=:id')->execute([':id' => $maCanHo]);
    $pdo->prepare('UPDATE CanHo_Anh SET LaAnhDaiDien=1 WHERE MaAnh=:id')->execute([':id' => (int)$ids[0]]);
}

function module2_image_path(string $relative): string {
    return dirname(__DIR__) . '/' . ltrim($relative, '/');
}
