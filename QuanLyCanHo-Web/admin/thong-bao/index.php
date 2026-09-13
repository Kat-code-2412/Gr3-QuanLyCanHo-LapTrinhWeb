<?php

declare(strict_types=1);

$title = 'Trung Tâm Thông Báo Hệ Thống';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$maNV = (int)($_SESSION['MaNV'] ?? 0);

// 1. Tự động quét hợp đồng sắp hết hạn trong vòng 30 ngày để sinh thông báo mới (nếu chưa có)
try {
    $expiringContracts = $pdo->query("
        SELECT hd.MaHopDong, c.SoPhong, c.DiaChi, hd.NgayKetThuc, DATEDIFF(hd.NgayKetThuc, CURDATE()) AS SoNgayConLai
        FROM HopDong hd
        JOIN CanHo c ON hd.MaCanHo = c.MaCanHo
        WHERE hd.TrangThai = 'Đang hiệu lực' 
          AND hd.NgayKetThuc BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ")->fetchAll();

    foreach ($expiringContracts as $exp) {
        $checkTitle = "Hợp đồng phòng " . $exp['SoPhong'] . " sắp hết hạn";
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM thongbao WHERE TieuDe = ? AND NgayTao >= CURDATE()");
        $stmtCheck->execute([$checkTitle]);
        if ((int)$stmtCheck->fetchColumn() === 0) {
            addNotification(
                null,
                $checkTitle,
                "Hợp đồng thuê phòng {$exp['SoPhong']} ({$exp['DiaChi']}) sẽ hết hạn sau {$exp['SoNgayConLai']} ngày nữa (Ngày hết hạn: " . date('d/m/Y', strtotime($exp['NgayKetThuc'])) . "). Vui lòng liên hệ khách thuê để gia hạn hoặc chuẩn bị thủ tục thanh lý.",
                'HopDong',
                "/admin/hop-dong/index.php"
            );
        }
    }
} catch (Throwable $t) {
    // Ignore error scanning notifications
}

// 2. Xử lý các thao tác POST (đánh dấu đã đọc, xóa đã chọn, xóa tất cả)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $idNotif = (int)($_POST['id'] ?? 0);
        if ($idNotif > 0) {
            $stmtMark = $pdo->prepare("UPDATE thongbao SET DaDoc = 1 WHERE MaThongBao = ? AND (MaNV = ? OR MaNV IS NULL)");
            $stmtMark->execute([$idNotif, $maNV]);
            setFlash('success', 'Đã đánh dấu thông báo là đã đọc.');
        }
    } elseif ($action === 'mark_all_read') {
        $stmtMarkAll = $pdo->prepare("UPDATE thongbao SET DaDoc = 1 WHERE (MaNV = ? OR MaNV IS NULL)");
        $stmtMarkAll->execute([$maNV]);
        setFlash('success', 'Đã đánh dấu tất cả thông báo là đã đọc thành công.');
    } elseif ($action === 'delete_single') {
        $idNotif = (int)($_POST['id'] ?? 0);
        if ($idNotif > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM thongbao WHERE MaThongBao = ? AND (MaNV = ? OR MaNV IS NULL)");
            $stmtDel->execute([$idNotif, $maNV]);
            setFlash('success', 'Đã xóa thông báo thành công.');
        }
    } elseif ($action === 'delete_selected') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids) && !empty($ids)) {
            $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
            if (!empty($cleanIds)) {
                $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                $params = array_merge($cleanIds, [$maNV]);
                $stmtDel = $pdo->prepare("DELETE FROM thongbao WHERE MaThongBao IN ({$placeholders}) AND (MaNV = ? OR MaNV IS NULL)");
                $stmtDel->execute($params);
                $countDeleted = $stmtDel->rowCount();
                setFlash('success', "Đã xóa thành công {$countDeleted} thông báo đã chọn.");
            } else {
                setFlash('warning', 'Vui lòng chọn ít nhất một thông báo hợp lệ để xóa.');
            }
        } else {
            setFlash('warning', 'Vui lòng tích chọn ít nhất một thông báo để xóa.');
        }
    } elseif ($action === 'delete_all') {
        $stmtDelAll = $pdo->prepare("DELETE FROM thongbao WHERE (MaNV = ? OR MaNV IS NULL)");
        $stmtDelAll->execute([$maNV]);
        setFlash('success', 'Đã xóa toàn bộ thông báo trong hệ thống thành công.');
    }
    
    // Giữ nguyên query string khi chuyển hướng
    $redirectQuery = http_build_query([
        'type'    => $_GET['type'] ?? '',
        'read'    => $_GET['read'] ?? '',
        'keyword' => $_GET['keyword'] ?? '',
        'page'    => $_GET['page'] ?? 1,
    ]);
    redirect('/admin/thong-bao/index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
}

// 3. Danh mục thông báo chuẩn xác trong hệ thống
$categories = [
    'DienNuoc' => [
        'name'      => 'Điện Nước',
        'icon'      => 'electric',
        'badge_bg'  => '#eff6ff',
        'badge_clr' => '#0284c7',
        'badge_bd'  => '#bae6fd',
        'icon_bg'   => '#e0f2fe',
        'icon_clr'  => '#0284c7',
    ],
    'HopDong' => [
        'name'      => 'Hợp Đồng',
        'icon'      => 'contract',
        'badge_bg'  => '#fef3c7',
        'badge_clr' => '#b45309',
        'badge_bd'  => '#fde68a',
        'icon_bg'   => '#fef3c7',
        'icon_clr'  => '#b45309',
    ],
    'BaoTri' => [
        'name'      => 'Bảo Trì',
        'icon'      => 'tool',
        'badge_bg'  => '#ede9fe',
        'badge_clr' => '#6d28d9',
        'badge_bd'  => '#ddd6fe',
        'icon_bg'   => '#ede9fe',
        'icon_clr'  => '#7c3aed',
    ],
    'HoaDon' => [
        'name'      => 'Hóa Đơn',
        'icon'      => 'invoice',
        'badge_bg'  => '#fee2e2',
        'badge_clr' => '#b91c1c',
        'badge_bd'  => '#fecaca',
        'icon_bg'   => '#fee2e2',
        'icon_clr'  => '#dc2626',
    ],
    'Info' => [
        'name'      => 'Hệ Thống',
        'icon'      => 'bell',
        'badge_bg'  => '#f1f5f9',
        'badge_clr' => '#475569',
        'badge_bd'  => '#cbd5e1',
        'icon_bg'   => '#f1f5f9',
        'icon_clr'  => '#475569',
    ],
];

// Helper lấy metadata loại thông báo
function getCategoryMeta(string $type, array $categories): array {
    return $categories[$type] ?? $categories['Info'];
}

// 4. Thống kê số lượng tổng & theo danh mục
$countsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN DaDoc = 0 THEN 1 ELSE 0 END) AS unread,
        SUM(CASE WHEN LoaiThongBao = 'DienNuoc' THEN 1 ELSE 0 END) AS diennuoc,
        SUM(CASE WHEN LoaiThongBao = 'HopDong' THEN 1 ELSE 0 END) AS hopdong,
        SUM(CASE WHEN LoaiThongBao = 'HoaDon' THEN 1 ELSE 0 END) AS hoadon,
        SUM(CASE WHEN LoaiThongBao = 'BaoTri' THEN 1 ELSE 0 END) AS baotri,
        SUM(CASE WHEN LoaiThongBao NOT IN ('DienNuoc','HopDong','HoaDon','BaoTri') THEN 1 ELSE 0 END) AS hethong
    FROM thongbao
    WHERE (MaNV = ? OR MaNV IS NULL)
");
$countsStmt->execute([$maNV]);
$stats = $countsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalNotifs  = (int)($stats['total'] ?? 0);
$unreadNotifs = (int)($stats['unread'] ?? 0);

// 5. Bộ lọc tìm kiếm
$keyword    = trim((string)($_GET['keyword'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));
$readFilter = trim((string)($_GET['read'] ?? ''));

$whereClauses = ["(MaNV = ? OR MaNV IS NULL)"];
$params = [$maNV];

if ($keyword !== '') {
    $whereClauses[] = "(TieuDe LIKE ? OR NoiDung LIKE ?)";
    $k = '%' . $keyword . '%';
    $params[] = $k;
    $params[] = $k;
}

if ($typeFilter !== '') {
    if ($typeFilter === 'Info') {
        $whereClauses[] = "LoaiThongBao NOT IN ('DienNuoc','HopDong','HoaDon','BaoTri')";
    } else {
        $whereClauses[] = "LoaiThongBao = ?";
        $params[] = $typeFilter;
    }
}

if ($readFilter !== '') {
    $whereClauses[] = "DaDoc = ?";
    $params[] = (int)$readFilter;
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

// 6. Phân trang
$countQueryStmt = $pdo->prepare("SELECT COUNT(*) FROM thongbao {$whereSql}");
$countQueryStmt->execute($params);
$totalRows = (int)$countQueryStmt->fetchColumn();

$perPage = 15;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM thongbao {$whereSql} ORDER BY NgayTao DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$notifications = $stmt->fetchAll();
?>

<style>
/* Style riêng biệt cao cấp cho Trung tâm thông báo */
.notif-hub-container {
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #1e293b;
}

.notif-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.notif-page-title {
    font-size: 1.55rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.025em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

/* Category Filter Chips */
.notif-chips-bar {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.4rem;
    margin-bottom: 1.25rem;
    align-items: center;
}

.notif-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.95rem;
    border-radius: 9999px;
    font-size: 0.825rem;
    font-weight: 600;
    text-decoration: none;
    background: #ffffff;
    color: #475569;
    border: 1px solid #e2e8f0;
    transition: all 0.18s ease;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.notif-chip:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}

.notif-chip.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 3px 10px rgba(37, 99, 235, 0.28);
}

.notif-chip .chip-count {
    padding: 1px 6px;
    border-radius: 9999px;
    font-size: 0.725rem;
    font-weight: 700;
    background: #f1f5f9;
    color: #475569;
}

.notif-chip.active .chip-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

.notif-chip .chip-count.badge-unread {
    background: #ef4444;
    color: #ffffff;
}

/* Filter Toolbar */
.notif-filter-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.9rem 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.notif-filter-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
}

.notif-input-search-wrapper {
    flex: 2;
    min-width: 240px;
    position: relative;
}

.notif-input-search-wrapper svg {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.notif-input-search {
    width: 100%;
    padding: 0.55rem 0.85rem 0.55rem 2.35rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.875rem;
    outline: none;
    background: #f8fafc;
    transition: all 0.2s ease;
}

.notif-input-search:focus {
    background: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.notif-select {
    padding: 0.55rem 0.85rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.875rem;
    background: #f8fafc;
    color: #334155;
    outline: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.notif-select:focus {
    background: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Notification Card Items */
.notif-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.notif-card {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.18s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.notif-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}

/* Checkbox container */
.notif-checkbox-col {
    display: flex;
    align-items: center;
    padding-top: 0.75rem;
    padding-right: 0.25rem;
}

.notif-checkbox {
    width: 19px;
    height: 19px;
    accent-color: #2563eb;
    cursor: pointer;
    border-radius: 4px;
}

/* Trạng thái được chọn checkbox */
.notif-card.is-checked {
    background: #f0f7ff !important;
    border-color: #93c5fd !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2) !important;
}

/* Trạng thái chưa đọc: Accent viền xanh sang trọng */
.notif-card.is-unread {
    background: #f8faff;
    border-color: #bfdbfe;
    border-left: 4px solid #2563eb;
}

.notif-card.is-read {
    border-left: 4px solid #e2e8f0;
}

/* Icon box */
.notif-icon-box {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Category Pill */
.notif-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.2;
}

/* Pulsing 'Mới' badge */
.notif-new-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 9999px;
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
}

.notif-new-tag .pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ef4444;
    display: inline-block;
    animation: pulseDot 1.8s infinite;
}

@keyframes pulseDot {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.3); }
    100% { opacity: 1; transform: scale(1); }
}

.notif-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.35;
    margin: 0;
}

.notif-body-text {
    font-size: 0.9rem;
    color: #475569;
    line-height: 1.55;
    margin-top: 0.35rem;
    white-space: pre-line;
}

.notif-meta-footer {
    display: flex;
    align-items: center;
    gap: 1.15rem;
    font-size: 0.785rem;
    color: #64748b;
    margin-top: 0.55rem;
    flex-wrap: wrap;
}

.notif-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
    transition: gap 0.15s ease, color 0.15s ease;
}

.notif-link-btn:hover {
    color: #1d4ed8;
    gap: 7px;
    text-decoration: underline;
}

.notif-card-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

/* Nút đánh dấu đã đọc */
.btn-mark-read {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    background: #ffffff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
    white-space: nowrap;
    text-decoration: none;
}

.btn-mark-read:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 3px 8px rgba(37, 99, 235, 0.25);
}

/* Nút xóa từng thông báo */
.btn-single-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #ffffff;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.18s ease;
}

.btn-single-delete:hover {
    background: #fef2f2;
    color: #ef4444;
    border-color: #fecaca;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.18);
}

.notif-read-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.775rem;
    color: #94a3b8;
    font-weight: 500;
    white-space: nowrap;
    padding: 4px 8px;
}

@media (max-width: 768px) {
    .notif-card {
        flex-direction: column;
        align-items: stretch;
    }
    .notif-card-actions {
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid #f1f5f9;
        padding-top: 0.75rem;
        margin-top: 0.5rem;
    }
}
</style>

<div class="notif-hub-container">
    <!-- TIÊU ĐỀ & NÚT ĐÁNH DẤU TẤT CẢ ĐÃ ĐỌC -->
    <div class="notif-page-header">
        <div>
            <h1 class="notif-page-title">
                <?= svgIcon('bell', '', 24) ?>
                <span>Trung Tâm Thông Báo Hệ Thống</span>
            </h1>
        </div>
        <div>
            <?php if ($unreadNotifs > 0): ?>
                <form method="POST" action="" style="display: inline; margin: 0;">
                    <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                        <?= svgIcon('check', '', 16) ?>
                        <span>Đánh dấu tất cả đã đọc (<?= $unreadNotifs ?>)</span>
                    </button>
                </form>
            <?php else: ?>
                <span style="font-size: 0.85rem; color: #10b981; display: inline-flex; align-items: center; gap: 5px; font-weight: 600; padding: 6px 12px; background: #ecfdf5; border-radius: 8px; border: 1px solid #a7f3d0;">
                    <?= svgIcon('check', '', 15) ?> Tất cả thông báo đã được đọc
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- QUICK CATEGORY CHIPS BAR -->
    <div class="notif-chips-bar">
        <!-- Tất cả -->
        <a href="?type=&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>" 
           class="notif-chip <?= ($typeFilter === '') ? 'active' : '' ?>">
            <span>Tất cả</span>
            <span class="chip-count"><?= $totalNotifs ?></span>
        </a>

        <!-- Chưa đọc -->
        <a href="?type=<?= urlencode($typeFilter) ?>&read=0&keyword=<?= urlencode($keyword) ?>" 
           class="notif-chip <?= ($readFilter === '0') ? 'active' : '' ?>">
            <span>Chưa đọc</span>
            <span class="chip-count <?= ($unreadNotifs > 0) ? 'badge-unread' : '' ?>"><?= $unreadNotifs ?></span>
        </a>

        <!-- Điện nước -->
        <a href="?type=DienNuoc&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>" 
           class="notif-chip <?= ($typeFilter === 'DienNuoc') ? 'active' : '' ?>">
            <?= svgIcon('electric', '', 14) ?>
            <span>Điện Nước</span>
            <span class="chip-count"><?= (int)($stats['diennuoc'] ?? 0) ?></span>
        </a>

        <!-- Hợp đồng -->
        <a href="?type=HopDong&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>" 
           class="notif-chip <?= ($typeFilter === 'HopDong') ? 'active' : '' ?>">
            <?= svgIcon('contract', '', 14) ?>
            <span>Hợp Đồng</span>
            <span class="chip-count"><?= (int)($stats['hopdong'] ?? 0) ?></span>
        </a>

        <!-- Bảo trì -->
        <a href="?type=BaoTri&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>" 
           class="notif-chip <?= ($typeFilter === 'BaoTri') ? 'active' : '' ?>">
            <?= svgIcon('tool', '', 14) ?>
            <span>Bảo Trì</span>
            <span class="chip-count"><?= (int)($stats['baotri'] ?? 0) ?></span>
        </a>

        <!-- Hóa đơn -->
        <a href="?type=HoaDon&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>" 
           class="notif-chip <?= ($typeFilter === 'HoaDon') ? 'active' : '' ?>">
            <?= svgIcon('invoice', '', 14) ?>
            <span>Hóa Đơn</span>
            <span class="chip-count"><?= (int)($stats['hoadon'] ?? 0) ?></span>
        </a>

        <!-- Hệ thống -->
        <a href="?type=Info&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>" 
           class="notif-chip <?= ($typeFilter === 'Info') ? 'active' : '' ?>">
            <?= svgIcon('bell', '', 14) ?>
            <span>Hệ Thống</span>
            <span class="chip-count"><?= (int)($stats['hethong'] ?? 0) ?></span>
        </a>
    </div>

    <!-- BỘ LỌC TÌM KIẾM NGANG GỌN GÀNG -->
    <div class="notif-filter-box">
        <form method="GET" action="" class="notif-filter-form">
            <!-- Tìm kiếm từ khóa -->
            <div class="notif-input-search-wrapper">
                <?= svgIcon('search', '', 16) ?>
                <input type="text" 
                       name="keyword" 
                       class="notif-input-search" 
                       placeholder="Tìm kiếm theo tiêu đề hoặc nội dung thông báo..." 
                       value="<?= e($keyword) ?>">
            </div>

            <!-- Lọc theo loại thông báo -->
            <div>
                <select name="type" class="notif-select" onchange="this.form.submit()">
                    <option value="">-- Tất cả phân loại --</option>
                    <option value="DienNuoc" <?= ($typeFilter === 'DienNuoc') ? 'selected' : '' ?>>⚡ Điện Nước</option>
                    <option value="HopDong" <?= ($typeFilter === 'HopDong') ? 'selected' : '' ?>>📄 Hợp Đồng</option>
                    <option value="BaoTri" <?= ($typeFilter === 'BaoTri') ? 'selected' : '' ?>>🛠️ Bảo Trì & Sự Cố</option>
                    <option value="HoaDon" <?= ($typeFilter === 'HoaDon') ? 'selected' : '' ?>>💳 Hóa Đơn</option>
                    <option value="Info" <?= ($typeFilter === 'Info') ? 'selected' : '' ?>>🔔 Hệ Thống / Khác</option>
                </select>
            </div>

            <!-- Lọc theo trạng thái đọc -->
            <div>
                <select name="read" class="notif-select" onchange="this.form.submit()">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="0" <?= ($readFilter === '0') ? 'selected' : '' ?>>Chưa đọc</option>
                    <option value="1" <?= ($readFilter === '1') ? 'selected' : '' ?>>Đã đọc</option>
                </select>
            </div>

            <!-- Nút bấm -->
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.15rem; border-radius: 8px; font-weight: 600;">
                    Lọc
                </button>
                <?php if ($keyword !== '' || $typeFilter !== '' || $readFilter !== ''): ?>
                    <a href="index.php" class="btn btn-secondary" style="padding: 0.55rem 0.85rem; border-radius: 8px;" title="Xóa bộ lọc">
                        Đặt lại
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- FORM XỬ LÝ HÀNH ĐỘNG HÀNG LOẠT (XÓA ĐÃ CHỌN / XÓA TẤT CẢ) -->
    <form id="bulkActionForm" method="POST" action="">
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" id="bulkActionInput" value="delete_selected">

        <!-- DANH SÁCH THÔNG BÁO -->
        <div class="card" style="border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; overflow: hidden;">
            <div class="card-header" style="background: #ffffff; border-bottom: 1px solid #f1f5f9; padding: 0.95rem 1.35rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <!-- Bên trái Header: Checkbox Chọn tất cả + Tiêu đề + Nút Xóa đã chọn -->
                <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                    <?php if (!empty($notifications)): ?>
                        <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; margin: 0; user-select: none;" title="Chọn tất cả trên trang này">
                            <input type="checkbox" id="checkAllNotifs" onchange="toggleSelectAll(this)" style="width: 18px; height: 18px; accent-color: #2563eb; cursor: pointer; border-radius: 4px;">
                            <span>Chọn tất cả</span>
                        </label>
                        <span style="color: #cbd5e1;">|</span>
                    <?php endif; ?>

                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <span>Danh Sách Thông Báo</span>
                        <span style="font-size: 0.8rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 9999px;">
                            <?= $totalRows ?> thông báo
                        </span>
                    </h3>

                    <!-- Nút Xóa các mục đã tick chọn (ẩn mặc định, hiện khi tick >= 1) -->
                    <button type="button" 
                            id="btnDeleteSelected" 
                            class="btn btn-danger btn-sm" 
                            style="display: none; align-items: center; gap: 5px; font-weight: 600; padding: 5px 12px; border-radius: 8px;"
                            onclick="submitDeleteSelected()">
                        <?= svgIcon('trash', '', 14) ?>
                        <span id="deleteSelectedText">Xóa đã chọn (0)</span>
                    </button>
                </div>

                <!-- Bên phải Header: Nút Xóa tất cả thông báo + Số trang -->
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <?php if ($totalNotifs > 0): ?>
                        <button type="button" 
                                class="btn btn-outline btn-sm" 
                                style="color: #dc2626; border-color: #fca5a5; background: #fff5f5; font-weight: 600; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px;"
                                onclick="confirmDeleteAll()" 
                                title="Xóa toàn bộ tất cả thông báo trong hệ thống">
                            <?= svgIcon('trash', '', 14) ?>
                            <span>Xóa tất cả thông báo</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 500;">
                            Trang <?= $page ?> / <?= $totalPages ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body" style="padding: 1.25rem;">
                <?php if (empty($notifications)): ?>
                    <div class="text-center" style="padding: 4rem 1rem; color: #94a3b8;">
                        <div style="margin-bottom: 1rem; color: #cbd5e1;"><?= svgIcon('bell', '', 48) ?></div>
                        <strong style="font-size: 1.05rem; color: #475569; display: block; margin-bottom: 0.25rem;">
                            Không có thông báo nào phù hợp
                        </strong>
                        <span style="font-size: 0.875rem;">
                            Bạn có thể thử điều chỉnh bộ lọc hoặc xóa từ khóa tìm kiếm để xem tất cả thông báo.
                        </span>
                        <?php if ($keyword !== '' || $typeFilter !== '' || $readFilter !== ''): ?>
                            <div style="margin-top: 1rem;">
                                <a href="index.php" class="btn btn-outline" style="border-radius: 8px;">
                                    Đặt lại bộ lọc
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="notif-list">
                        <?php foreach ($notifications as $n): ?>
                            <?php
                            $isUnread = ((int)$n['DaDoc'] === 0);
                            $cat = getCategoryMeta($n['LoaiThongBao'], $categories);
                            $notifId = (int)$n['MaThongBao'];
                            ?>
                            <div class="notif-card <?= $isUnread ? 'is-unread' : 'is-read' ?>" id="notif-card-<?= $notifId ?>">
                                <!-- Checkbox chọn để xóa -->
                                <div class="notif-checkbox-col">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center;" title="Chọn để xóa">
                                        <input type="checkbox" 
                                               name="ids[]" 
                                               value="<?= $notifId ?>" 
                                               class="notif-checkbox" 
                                               onchange="onCheckboxChange(this)">
                                    </label>
                                </div>

                                <!-- Icon Box Trái -->
                                <div class="notif-icon-box" style="background-color: <?= $cat['icon_bg'] ?>; color: <?= $cat['icon_clr'] ?>;">
                                    <?= svgIcon($cat['icon'], '', 22) ?>
                                </div>

                                <!-- Nội dung Trung tâm -->
                                <div style="flex: 1; min-width: 0;">
                                    <!-- Dòng tiêu đề + Nhãn phân loại + Huy hiệu Mới -->
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
                                        <span class="notif-cat-badge" style="background: <?= $cat['badge_bg'] ?>; color: <?= $cat['badge_clr'] ?>; border: 1px solid <?= $cat['badge_bd'] ?>;">
                                            <?= $cat['name'] ?>
                                        </span>
                                        
                                        <h4 class="notif-title">
                                            <?= e($n['TieuDe']) ?>
                                        </h4>

                                        <?php if ($isUnread): ?>
                                            <span class="notif-new-tag">
                                                <span class="pulse-dot"></span> Mới
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Nội dung chi tiết -->
                                    <div class="notif-body-text">
                                        <?= nl2br(e($n['NoiDung'])) ?>
                                    </div>

                                    <!-- Footer: Thời gian + Link xem chi tiết -->
                                    <div class="notif-meta-footer">
                                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                                            <?= svgIcon('history', '', 13) ?>
                                            <span><?= formatDateTime($n['NgayTao']) ?></span>
                                        </span>

                                        <?php if (!empty($n['LienKet'])): ?>
                                            <span style="color: #cbd5e1;">•</span>
                                            <a href="<?= url($n['LienKet']) ?>" class="notif-link-btn">
                                                <span>Xem chi tiết</span>
                                                <?= svgIcon('arrow-right', '', 12) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Thao tác Phải: Đánh dấu đã đọc & Nút xóa đơn lẻ -->
                                <div class="notif-card-actions">
                                    <?php if ($isUnread): ?>
                                        <button type="button" 
                                                class="btn-mark-read" 
                                                onclick="markSingleRead(<?= $notifId ?>)"
                                                title="Đánh dấu thông báo này là đã đọc">
                                            <?= svgIcon('check', '', 14) ?>
                                            <span>Đã đọc</span>
                                        </button>
                                    <?php else: ?>
                                        <span class="notif-read-status">
                                            <?= svgIcon('check', '', 14) ?> Đã đọc
                                        </span>
                                    <?php endif; ?>

                                    <!-- Nút xóa thông báo này -->
                                    <button type="button" 
                                            class="btn-single-delete" 
                                            onclick="deleteSingleNotif(<?= $notifId ?>)"
                                            title="Xóa thông báo này">
                                        <?= svgIcon('trash', '', 14) ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- PHÂN TRANG -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="justify-content: center; margin-top: 1.5rem;">
            <?php if ($page > 1): ?>
                <a href="?type=<?= urlencode($typeFilter) ?>&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $page - 1 ?>" title="Trang trước">
                    &laquo;
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?type=<?= urlencode($typeFilter) ?>&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $i ?>" 
                   class="<?= ($i === $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?type=<?= urlencode($typeFilter) ?>&read=<?= urlencode($readFilter) ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $page + 1 ?>" title="Trang sau">
                    &raquo;
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- FORM ẨN DÙNG CHO THAO TÁC ĐƠN LẺ (ĐÁNH DẤU ĐÃ ĐỌC / XÓA ĐƠN LẺ) -->
<form id="singleActionForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" id="singleActionType" value="">
    <input type="hidden" name="id" id="singleActionId" value="">
</form>

<script>
// Chọn / Bỏ chọn tất cả
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.notif-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
        toggleCardHighlight(cb);
    });
    updateSelectedCount();
}

function onCheckboxChange(checkbox) {
    toggleCardHighlight(checkbox);
    updateSelectedCount();
}

function toggleCardHighlight(checkbox) {
    const card = checkbox.closest('.notif-card');
    if (card) {
        if (checkbox.checked) {
            card.classList.add('is-checked');
        } else {
            card.classList.remove('is-checked');
        }
    }
}

// Cập nhật số lượng thông báo đã chọn
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.notif-checkbox');
    const checked = document.querySelectorAll('.notif-checkbox:checked');
    const count = checked.length;

    const master = document.getElementById('checkAllNotifs');
    if (master) {
        master.checked = (checkboxes.length > 0 && count === checkboxes.length);
    }

    const btnDel = document.getElementById('btnDeleteSelected');
    const textDel = document.getElementById('deleteSelectedText');
    if (btnDel && textDel) {
        if (count > 0) {
            btnDel.style.display = 'inline-flex';
            textDel.textContent = 'Xóa đã chọn (' + count + ')';
        } else {
            btnDel.style.display = 'none';
        }
    }
}

// Xóa các thông báo đã tick chọn
function submitDeleteSelected() {
    const checked = document.querySelectorAll('.notif-checkbox:checked');
    if (checked.length === 0) {
        alert('Vui lòng tick chọn ít nhất 1 thông báo để xóa.');
        return;
    }
    if (confirm('Bạn có chắc chắn muốn xóa ' + checked.length + ' thông báo đã chọn không?')) {
        document.getElementById('bulkActionInput').value = 'delete_selected';
        document.getElementById('bulkActionForm').submit();
    }
}

// Xóa tất cả thông báo
function confirmDeleteAll() {
    if (confirm('CẢNH BÁO NGUY HIỂM: Bạn có chắc chắn muốn xóa TOÀN BỘ tất cả thông báo trong hệ thống?\n\nThao tác này sẽ xóa sạch thông báo và KHÔNG THỂ HOÀN TÁC!')) {
        document.getElementById('bulkActionInput').value = 'delete_all';
        document.getElementById('bulkActionForm').submit();
    }
}

// Đánh dấu 1 thông báo là đã đọc
function markSingleRead(id) {
    document.getElementById('singleActionType').value = 'mark_read';
    document.getElementById('singleActionId').value = id;
    document.getElementById('singleActionForm').submit();
}

// Xóa 1 thông báo
function deleteSingleNotif(id) {
    if (confirm('Bạn có chắc chắn muốn xóa thông báo này?')) {
        document.getElementById('singleActionType').value = 'delete_single';
        document.getElementById('singleActionId').value = id;
        document.getElementById('singleActionForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
