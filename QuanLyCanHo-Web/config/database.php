<?php

declare(strict_types=1);

$envFile = __DIR__ . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
$dbUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'quanlycanho';
$dbCharset = $_ENV['DB_CHARSET'] ?? $_SERVER['DB_CHARSET'] ?? 'utf8mb4';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbHost, $dbName, $dbCharset);

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Chạy kiểm tra cấu trúc bảng 1 lần duy nhất để tối ưu tốc độ
    static $migrationDone = false;
    if (!$migrationDone) {
        $migrationDone = true;
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM KhachThue")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('NgheNghiep', $columns, true)) {
                $pdo->exec("ALTER TABLE KhachThue ADD COLUMN NgheNghiep VARCHAR(100) NULL AFTER DiaChiThuongTru");
            }
            if (!in_array('GhiChu', $columns, true)) {
                $pdo->exec("ALTER TABLE KhachThue ADD COLUMN GhiChu VARCHAR(255) NULL AFTER NgheNghiep");
            }

            $hdColumns = $pdo->query("SHOW COLUMNS FROM HopDong")->fetchAll(PDO::FETCH_COLUMN);
            $alterQueries = [
                'NgayKy'           => "ALTER TABLE HopDong ADD COLUMN NgayKy DATE NULL AFTER MaNV",
                'ThoiHanThang'     => "ALTER TABLE HopDong ADD COLUMN ThoiHanThang INT NULL DEFAULT 6 AFTER NgayKetThuc",
                'NgayCheckIn'      => "ALTER TABLE HopDong ADD COLUMN NgayCheckIn DATE NULL AFTER GhiChu",
                'GioCheckIn'       => "ALTER TABLE HopDong ADD COLUMN GioCheckIn VARCHAR(10) NULL AFTER NgayCheckIn",
                'NgayCheckOut'     => "ALTER TABLE HopDong ADD COLUMN NgayCheckOut DATE NULL AFTER GioCheckIn",
                'GioCheckOut'      => "ALTER TABLE HopDong ADD COLUMN GioCheckOut VARCHAR(10) NULL AFTER NgayCheckOut",
                'AnhKhachThue'     => "ALTER TABLE HopDong ADD COLUMN AnhKhachThue VARCHAR(255) NULL AFTER FileHopDong",
                'AnhCCCD'          => "ALTER TABLE HopDong ADD COLUMN AnhCCCD VARCHAR(255) NULL AFTER AnhKhachThue",
                'AnhCCCDMatTruoc'  => "ALTER TABLE HopDong ADD COLUMN AnhCCCDMatTruoc VARCHAR(255) NULL AFTER AnhCCCD",
                'AnhCCCDMatSau'    => "ALTER TABLE HopDong ADD COLUMN AnhCCCDMatSau VARCHAR(255) NULL AFTER AnhCCCDMatTruoc",
                'AnhBienBan'       => "ALTER TABLE HopDong ADD COLUMN AnhBienBan VARCHAR(255) NULL AFTER AnhCCCDMatSau",
            ];

            foreach ($alterQueries as $colName => $sqlCmd) {
                if (!in_array($colName, $hdColumns, true)) {
                    $pdo->exec($sqlCmd);
                }
            }
        } catch (Throwable $t) {
            // Ignore if tables don't exist yet
        }
    }
} catch (PDOException $e) {
    throw new RuntimeException('Kết nối database thất bại: ' . $e->getMessage());
}

return $pdo;
