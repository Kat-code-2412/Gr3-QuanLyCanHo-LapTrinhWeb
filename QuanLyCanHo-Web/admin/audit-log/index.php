<?php

declare(strict_types=1);

$title = 'Nhật Ký Thao Tác Hệ Thống (Audit Logs)';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url('/admin/audit-log');

// Tham số lọc & phân trang
$keyword = trim((string)($_GET['keyword'] ?? ''));
$moduleFilter = trim((string)($_GET['module'] ?? ''));
$actionFilter = trim((string)($_GET['action_type'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Xử lý các thao tác POST: Xóa đã chọn, Xóa tất cả, Xóa đơn lẻ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['action'] ?? '';

    $redirectParams = array_filter([
        'keyword'     => $keyword !== '' ? $keyword : null,
        'module'      => $moduleFilter !== '' ? $moduleFilter : null,
        'action_type' => $actionFilter !== '' ? $actionFilter : null,
        'page'        => $page > 1 ? $page : null,
    ]);
    $redirectUrl = $baseUrl . '/index.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

    if ($postAction === 'delete_selected') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids) && !empty($ids)) {
            $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
            if (!empty($cleanIds)) {
                $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                $stmtDel = $pdo->prepare("DELETE FROM audit_logs WHERE MaLog IN ($placeholders)");
                $stmtDel->execute($cleanIds);
                $countDeleted = $stmtDel->rowCount();
                setFlash('success', "Đã xóa thành công {$countDeleted} bản ghi nhật ký đã chọn.");
            } else {
                setFlash('warning', 'Vui lòng chọn ít nhất một bản ghi hợp lệ để xóa.');
            }
        } else {
            setFlash('warning', 'Vui lòng tích chọn ít nhất một bản ghi nhật ký để xóa.');
        }
    } elseif ($postAction === 'delete_all') {
        $pdo->exec("DELETE FROM audit_logs");
        setFlash('success', 'Đã xóa toàn bộ nhật ký thao tác trong hệ thống thành công.');
        redirect($baseUrl . '/index.php');
    } elseif ($postAction === 'delete_single') {
        $idLog = (int)($_POST['id'] ?? 0);
        if ($idLog > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM audit_logs WHERE MaLog = ?");
            $stmtDel->execute([$idLog]);
            setFlash('success', "Đã xóa bản ghi nhật ký #{$idLog} thành công.");
        }
    }

    redirect($redirectUrl);
}

$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($keyword !== '') {
    $whereClauses[] = "(TenDangNhap LIKE ? OR ChiTiet LIKE ? OR IPAddress LIKE ? OR DoiTuongId LIKE ?)";
    $kw = '%' . $keyword . '%';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

if ($moduleFilter !== '') {
    $whereClauses[] = "Module = ?";
    $params[] = $moduleFilter;
}

if ($actionFilter !== '') {
    $whereClauses[] = "HanhDong = ?";
    $params[] = $actionFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Đếm tổng số log
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs {$whereSql}");
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Lấy danh sách log
$sql = "SELECT * FROM audit_logs {$whereSql} ORDER BY MaLog DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Lấy danh sách các Module & Action unique phục vụ bộ lọc
$modulesList = $pdo->query("SELECT DISTINCT Module FROM audit_logs WHERE Module IS NOT NULL ORDER BY Module ASC")->fetchAll(PDO::FETCH_COLUMN);
$actionsList = $pdo->query("SELECT DISTINCT HanhDong FROM audit_logs WHERE HanhDong IS NOT NULL ORDER BY HanhDong ASC")->fetchAll(PDO::FETCH_COLUMN);

function getAuditActionBadge(string $action): string {
    $actionUpper = strtoupper($action);
    if (str_contains($actionUpper, 'DELETE') || str_contains($actionUpper, 'XOA')) {
        return '<span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; white-space: nowrap;">' . e($action) . '</span>';
    }
    if (str_contains($actionUpper, 'CREATE') || str_contains($actionUpper, 'GENERATE') || str_contains($actionUpper, 'TAO')) {
        return '<span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; white-space: nowrap;">' . e($action) . '</span>';
    }
    if (str_contains($actionUpper, 'UPDATE') || str_contains($actionUpper, 'CAP_NHAT') || str_contains($actionUpper, 'EDIT') || str_contains($actionUpper, 'GRANT')) {
        return '<span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; white-space: nowrap;">' . e($action) . '</span>';
    }
    if (str_contains($actionUpper, 'PAYMENT') || str_contains($actionUpper, 'THANH_TOAN')) {
        return '<span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; white-space: nowrap;">' . e($action) . '</span>';
    }
    if (str_contains($actionUpper, 'LIQUIDATE') || str_contains($actionUpper, 'THANH_LY')) {
        return '<span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; background: #fffbeb; color: #92400e; border: 1px solid #fde68a; white-space: nowrap;">' . e($action) . '</span>';
    }
    if (str_contains($actionUpper, 'LOGIN') || str_contains($actionUpper, 'DANG_NHAP')) {
        return '<span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; background: #f5f3ff; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap;">' . e($action) . '</span>';
    }
    return '<span style="display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; white-space: nowrap;">' . e($action) . '</span>';
}
?>

<style>
.audit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.audit-breadcrumb {
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
    margin-bottom: 0.35rem;
}
.audit-breadcrumb a {
    color: var(--text-muted, #64748b);
    text-decoration: none;
}
.audit-breadcrumb a:hover {
    color: var(--primary-color, #2563eb);
}
.audit-title {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.02em;
}

/* Filter Card */
.audit-filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    margin-bottom: 1.5rem;
}
.audit-filter-body {
    padding: 1.25rem 1.5rem;
}
.audit-filter-grid {
    display: grid;
    grid-template-columns: 2.2fr 1.2fr 1.2fr auto;
    gap: 1rem;
    align-items: flex-end;
}
@media (max-width: 992px) {
    .audit-filter-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 640px) {
    .audit-filter-grid {
        grid-template-columns: 1fr;
    }
}
.audit-filter-group label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.4rem;
}
.audit-filter-group .form-control {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 0.52rem 0.75rem;
    font-size: 0.88rem;
    background-color: #ffffff;
    transition: all 0.2s;
}
.audit-filter-group .form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.audit-filter-actions {
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
}

/* Table Card */
.audit-table-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    background: #ffffff;
}
.audit-table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.85rem 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.audit-table td {
    padding: 0.85rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.audit-table tbody tr:hover {
    background-color: #f8fafc;
}

/* Action buttons */
.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem;
    border-radius: 6px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.18s ease;
    line-height: 1;
}
.btn-action-danger {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
.btn-action-danger:hover {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
}
</style>

<div class="audit-header">
    <div>
        <div class="audit-breadcrumb">
            <a href="<?= url('/admin/dashboard.php') ?>">Hệ Thống</a> / <span>Nhật Ký Thao Tác (Audit Logs)</span>
        </div>
        <h1 class="audit-title">Nhật Ký Thao Tác Hệ Thống (Audit Logs)</h1>
    </div>
    <div>
        <?php if ($totalLogs > 0): ?>
            <form method="POST" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa TOÀN BỘ nhật ký thao tác không? Toàn bộ lịch sử hoạt động sẽ bị xóa vĩnh viễn và không thể khôi phục!');" style="margin: 0;">
                <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-outline" style="color: #dc2626; border-color: #fca5a5; background: #ffffff; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; padding: 0.52rem 1rem; border-radius: 8px;">
                    <?= svgIcon('trash', '', 14) ?>
                    <span>Xóa tất cả nhật ký</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- BỘ LỌC TÌM KIẾM LOG -->
<div class="audit-filter-card">
    <div class="audit-filter-body">
        <form method="GET" action="" class="audit-filter-grid">
            <div class="audit-filter-group">
                <label for="keyword">Tìm kiếm</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex;">
                        <?= svgIcon('search', '', 15) ?>
                    </span>
                    <input type="text" 
                           id="keyword" 
                           name="keyword" 
                           class="form-control" 
                           placeholder="Tài khoản, nội dung chi tiết, IP..." 
                           value="<?= e($keyword) ?>"
                           style="padding-left: 35px;">
                </div>
            </div>

            <div class="audit-filter-group">
                <label for="module">Module</label>
                <select name="module" id="module" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Tất cả Module --</option>
                    <?php foreach ($modulesList as $mod): ?>
                        <option value="<?= e($mod) ?>" <?= ($moduleFilter === $mod) ? 'selected' : '' ?>><?= e($mod) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="audit-filter-group">
                <label for="action_type">Hành động</label>
                <select name="action_type" id="action_type" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Tất cả hành động --</option>
                    <?php foreach ($actionsList as $act): ?>
                        <option value="<?= e($act) ?>" <?= ($actionFilter === $act) ? 'selected' : '' ?>><?= e($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="audit-filter-actions">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 0.52rem 1.1rem; border-radius: 8px;">
                    <?= svgIcon('filter', '', 14) ?>
                    <span>Lọc nhật ký</span>
                </button>
                <?php if ($keyword !== '' || $moduleFilter !== '' || $actionFilter !== ''): ?>
                    <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline" style="font-weight: 600; padding: 0.52rem 1rem; border-radius: 8px;">Xóa lọc</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- BẢNG HIỂN THỊ LOGS (KÈM CHỨC NĂNG TICK XÓA) -->
<form id="bulkForm" method="POST" action="">
    <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="delete_selected">

    <div class="audit-table-card">
        <div class="card-header" style="background: #ffffff; padding: 0.9rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: #1e293b;">
                    Nhật Ký Thao Tác
                    <span style="font-size: 0.85rem; font-weight: 500; color: #64748b; margin-left: 0.4rem;">(<?= number_format($totalLogs) ?> bản ghi)</span>
                </h3>
            </div>
            <div>
                <!-- Nút Xóa đã chọn (tự động hiện khi tick chọn ít nhất 1 dòng) -->
                <button type="button" id="btnDeleteSelected" onclick="submitDeleteSelected()" class="btn btn-danger" style="display: none; align-items: center; gap: 6px; font-weight: 600; padding: 0.45rem 1rem; border-radius: 8px; font-size: 0.84rem;">
                    <?= svgIcon('trash', '', 14) ?>
                    <span>Xóa đã chọn (<span id="selectedCount">0</span>)</span>
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table audit-table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 42px; text-align: center;">
                            <input type="checkbox" id="checkAll" style="width: 16px; height: 16px; cursor: pointer;" title="Chọn tất cả bản ghi ở trang này">
                        </th>
                        <th style="width: 75px;">Mã Log</th>
                        <th style="min-width: 130px;">Thời gian</th>
                        <th style="min-width: 120px;">Tài khoản</th>
                        <th>Hành động</th>
                        <th>Module</th>
                        <th style="width: 95px;">Đối tượng</th>
                        <th style="min-width: 240px;">Chi tiết thao tác</th>
                        <th style="width: 105px;">IP Address</th>
                        <th style="width: 50px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="10" class="text-center" style="padding: 3rem 1rem; color: #64748b;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem; color: #94a3b8;"><?= svgIcon('info', '', 36) ?></div>
                                <p style="margin: 0; font-weight: 500;">Chưa có bản ghi nhật ký hoạt động nào phù hợp với bộ lọc.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="ids[]" value="<?= (int)$log['MaLog'] ?>" class="log-checkbox" style="width: 16px; height: 16px; cursor: pointer;">
                                </td>
                                <td><strong style="color: #64748b; font-size: 0.85rem;">#<?= (int)$log['MaLog'] ?></strong></td>
                                <td>
                                    <div style="font-weight: 600; color: #334155; font-size: 0.85rem;">
                                        <?= date('d/m/Y', strtotime($log['ThoiGian'])) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 1px;">
                                        <?= date('H:i:s', strtotime($log['ThoiGian'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #eff6ff; color: #2563eb; font-size: 0.75rem;">
                                            <?= svgIcon('user', '', 12) ?>
                                        </span>
                                        <strong style="color: #1e293b; font-size: 0.88rem;"><?= e($log['TenDangNhap']) ?></strong>
                                    </div>
                                </td>
                                <td><?= getAuditActionBadge($log['HanhDong']) ?></td>
                                <td>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 5px; font-size: 0.8rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">
                                        <?= e($log['Module']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['DoiTuongId'])): ?>
                                        <span style="font-weight: 600; color: #0284c7; font-size: 0.85rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">
                                            #<?= e($log['DoiTuongId']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 320px; line-height: 1.45;">
                                    <span style="font-size: 0.88rem; color: #334155;">
                                        <?= e($log['ChiTiet'] ?: '—') ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.78rem; background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; padding: 2px 6px; border-radius: 4px; white-space: nowrap;">
                                        <?= e($log['IPAddress'] ?: '—') ?>
                                    </code>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <button type="button" 
                                            class="btn-action btn-action-danger" 
                                            style="padding: 4px 6px;" 
                                            title="Xóa bản ghi nhật ký này"
                                            onclick="deleteSingleLog(<?= (int)$log['MaLog'] ?>)">
                                        <?= svgIcon('trash', '', 13) ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Form ẩn xử lý xóa đơn lẻ -->
<form id="singleDeleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="delete_single">
    <input type="hidden" name="id" id="singleDeleteId" value="">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.log-checkbox');
    const btnDeleteSelected = document.getElementById('btnDeleteSelected');
    const selectedCountSpan = document.getElementById('selectedCount');

    function updateBulkState() {
        const checkedBoxes = document.querySelectorAll('.log-checkbox:checked');
        const count = checkedBoxes.length;
        if (count > 0) {
            btnDeleteSelected.style.display = 'inline-flex';
            selectedCountSpan.textContent = count;
        } else {
            btnDeleteSelected.style.display = 'none';
        }
        if (checkAll) {
            checkAll.checked = (checkboxes.length > 0 && count === checkboxes.length);
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = checkAll.checked);
            updateBulkState();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkState);
    });
});

function submitDeleteSelected() {
    const checked = document.querySelectorAll('.log-checkbox:checked');
    if (checked.length === 0) {
        alert('Vui lòng tích chọn ít nhất một bản ghi nhật ký để xóa.');
        return;
    }
    if (confirm('Bạn có chắc chắn muốn xóa ' + checked.length + ' bản ghi nhật ký đã chọn không? Hành động này không thể hoàn tác!')) {
        document.getElementById('bulkForm').submit();
    }
}

function deleteSingleLog(id) {
    if (confirm('Bạn có chắc chắn muốn xóa bản ghi nhật ký #' + id + ' không?')) {
        document.getElementById('singleDeleteId').value = id;
        document.getElementById('singleDeleteForm').submit();
    }
}
</script>

<!-- PHÂN TRANG -->
<?php if ($totalPages > 1): ?>
    <div class="pagination" style="display: flex; justify-content: center; gap: 6px; margin-top: 1.5rem;">
        <?php
        $pageParams = [];
        if ($keyword !== '') $pageParams['keyword'] = $keyword;
        if ($moduleFilter !== '') $pageParams['module'] = $moduleFilter;
        if ($actionFilter !== '') $pageParams['action_type'] = $actionFilter;
        ?>

        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query($pageParams + ['page' => $page - 1]) ?>" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; font-weight: 600; font-size: 0.85rem;">&laquo; Trước</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= ($i === $page) ? 'active' : '' ?>" href="?<?= http_build_query($pageParams + ['page' => $i]) ?>" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid <?= ($i === $page) ? '#2563eb' : '#cbd5e1' ?>; background: <?= ($i === $page) ? '#2563eb' : '#ffffff' ?>; color: <?= ($i === $page) ? '#ffffff' : '#334155' ?>; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query($pageParams + ['page' => $page + 1]) ?>" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; text-decoration: none; font-weight: 600; font-size: 0.85rem;">Sau &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
