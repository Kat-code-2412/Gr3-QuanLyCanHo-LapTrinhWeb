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

/**
 * Kiểm tra xem nhân viên có quyền theo mã quyen hay không
 */
function hasPermission(string $permissionCode): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    if (($_SESSION['VaiTro'] ?? '') === 'Admin') {
        return true;
    }

    $maNV = (int)($_SESSION['MaNV'] ?? 0);
    if ($maNV <= 0) {
        return false;
    }

    if (!isset($_SESSION['user_permissions'])) {
        try {
            $pdo = require __DIR__ . '/../config/database.php';
            $stmt = $pdo->prepare("
                SELECT q.MaCode 
                FROM phanquyen pq 
                JOIN quyen q ON pq.MaQuyen = q.MaQuyen 
                WHERE pq.MaNV = ?
            ");
            $stmt->execute([$maNV]);
            $_SESSION['user_permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $t) {
            $_SESSION['user_permissions'] = [];
        }
    }

    return in_array($permissionCode, $_SESSION['user_permissions'] ?? [], true);
}

/**
 * Yêu cầu phải có quyền cụ thể, nếu không có sẽ từ chối truy cập
 */
function requirePermission(string $permissionCode): void
{
    requireLogin();
    if (!hasPermission($permissionCode)) {
        setFlash('error', 'Bạn không có quyền truy cập chức năng này (' . e($permissionCode) . ').');
        if (($_SESSION['VaiTro'] ?? '') === 'Admin') {
            redirect('/admin/index.php');
        } else {
            redirect('/user/index.php');
        }
    }
}

/**
 * Ghi nhật ký hệ thống Audit Log
 */
function logAudit(string $action, string $module, ?string $objectId = null, ?string $detail = null): void
{
    try {
        $pdo = require __DIR__ . '/../config/database.php';
        $maNV = isset($_SESSION['MaNV']) ? (int)$_SESSION['MaNV'] : null;
        $username = $_SESSION['TenDangNhap'] ?? 'System';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (MaNV, TenDangNhap, HanhDong, Module, DoiTuongId, ChiTiet, IPAddress, ThoiGian)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$maNV, $username, $action, $module, $objectId, $detail, $ip]);
    } catch (Throwable $t) {
        error_log('Lỗi logAudit: ' . $t->getMessage());
    }
}

/**
 * Thêm thông báo tự động vào hệ thống
 */
function addNotification(?int $maNV, string $title, string $content, string $type = 'Info', ?string $link = null): void
{
    try {
        $pdo = require __DIR__ . '/../config/database.php';
        $stmt = $pdo->prepare("
            INSERT INTO thongbao (MaNV, TieuDe, NoiDung, LoaiThongBao, LienKet, DaDoc, NgayTao)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$maNV, $title, $content, $type, $link]);
    } catch (Throwable $t) {
        error_log('Lỗi addNotification: ' . $t->getMessage());
    }
}

/**
 * Lấy danh sách tất cả các Tòa nhà / Địa chỉ hiện có
 */
function getAllBuildings(): array
{
    static $buildings = null;
    if ($buildings !== null) {
        return $buildings;
    }
    try {
        $pdo = require __DIR__ . '/../config/database.php';
        $buildings = $pdo->query('
            SELECT DISTINCT DiaChi 
            FROM CanHo 
            WHERE DiaChi IS NOT NULL AND DiaChi <> "" 
            ORDER BY DiaChi ASC
        ')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $t) {
        $buildings = [];
    }
    return $buildings;
}

/**
 * Lấy danh sách các Tòa nhà / Địa chỉ được phân công cho nhân viên
 * - Trả về null nếu là Admin (toàn quyền, xem tất cả tòa nhà)
 * - Trả về mảng các địa chỉ nếu là Nhân viên (mảng rỗng nếu chưa được phân công)
 */
function getStaffAssignedBuildings(?int $maNv = null): ?array
{
    if (!isLoggedIn()) {
        return [];
    }

    $currentId = currentUserId();
    $isTargetSelf = ($maNv === null || $maNv === $currentId);
    $targetId = $maNv ?? $currentId;

    if ($targetId === null) {
        return [];
    }

    // Nếu kiểm tra chính mình và là Admin -> toàn quyền
    if ($isTargetSelf && currentUserRole() === 'Admin') {
        return null;
    }

    // Kiểm tra trong session nếu là chính mình
    if ($isTargetSelf && isset($_SESSION['user_assigned_buildings'])) {
        return $_SESSION['user_assigned_buildings'];
    }

    try {
        $pdo = require __DIR__ . '/../config/database.php';

        // Kiểm tra vai trò của target user nếu không phải self
        if (!$isTargetSelf) {
            $roleStmt = $pdo->prepare('SELECT VaiTro FROM NhanVien WHERE MaNV = ?');
            $roleStmt->execute([$targetId]);
            $targetRole = (string)$roleStmt->fetchColumn();
            if ($targetRole === 'Admin') {
                return null;
            }
        }

        $stmt = $pdo->prepare('SELECT DiaChi FROM nhanvien_toanha WHERE MaNV = ? ORDER BY DiaChi ASC');
        $stmt->execute([$targetId]);
        $buildings = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if ($isTargetSelf) {
            $_SESSION['user_assigned_buildings'] = $buildings;
        }

        return $buildings;
    } catch (Throwable $t) {
        return [];
    }
}

/**
 * Làm mới cache session các tòa nhà được phân công
 */
function refreshStaffBuildingSession(): void
{
    unset($_SESSION['user_assigned_buildings']);
}

/**
 * Kiểm tra nhân viên có được phân công quản lý tòa nhà này hay không
 */
function isStaffAssignedBuilding(string $diaChi, ?int $maNv = null): bool
{
    $assigned = getStaffAssignedBuildings($maNv);
    if ($assigned === null) {
        return true; // Admin có toàn quyền
    }
    return in_array(trim($diaChi), $assigned, true);
}

/**
 * Xây dựng điều kiện SQL WHERE theo các tòa nhà nhân viên được giao
 * Trả về ['sql' => string, 'params' => array]
 */
function buildStaffBuildingCondition(string $diaChiColumn = 'ch.DiaChi', ?int $maNv = null): array
{
    $assigned = getStaffAssignedBuildings($maNv);
    if ($assigned === null) {
        return ['sql' => '1=1', 'params' => []];
    }

    if (empty($assigned)) {
        return ['sql' => '1=0', 'params' => []];
    }

    $placeholders = implode(',', array_fill(0, count($assigned), '?'));
    return [
        'sql' => "{$diaChiColumn} IN ({$placeholders})",
        'params' => $assigned,
    ];
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

    // 1. Tuyến đường quản trị /admin/ -> Chỉ Admin hoặc Nhân viên đã đăng nhập
    if (str_contains($script, '/admin/')) {
        requireLogin();
    }
    // 2. Tuyến đường tác nghiệp /user/ -> Nhân viên và Admin
    elseif (str_contains($script, '/user/')) {
        requireLogin();
    }
}

// Tự động bảo vệ tất cả URL hệ thống
checkRoutePermission();


