<?php

declare(strict_types=1);

$title = 'Phân Quyền Chi Tiết Nhân Viên';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Mã nhân viên không hợp lệ.');
    redirect('/admin/nhan-vien/index.php');
}

// Lấy thông tin nhân viên
$stmt = $pdo->prepare('SELECT * FROM NhanVien WHERE MaNV = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('error', 'Không tìm thấy thông tin nhân viên.');
    redirect('/admin/nhan-vien/index.php');
}

// Xử lý lưu phân quyền
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $selectedPermissions = $_POST['permissions'] ?? [];
    if (!is_array($selectedPermissions)) {
        $selectedPermissions = [];
    }

    $selectedBuildings = $_POST['buildings'] ?? [];
    if (!is_array($selectedBuildings)) {
        $selectedBuildings = [];
    }

    try {
        $pdo->beginTransaction();

        // 1. Cập nhật quyền chức năng
        $stmtDel = $pdo->prepare('DELETE FROM phanquyen WHERE MaNV = ?');
        $stmtDel->execute([$id]);

        if (!empty($selectedPermissions)) {
            $stmtIns = $pdo->prepare('INSERT INTO phanquyen (MaNV, MaQuyen) VALUES (?, ?)');
            foreach ($selectedPermissions as $maQuyen) {
                $stmtIns->execute([$id, (int)$maQuyen]);
            }
        }

        // 2. Cập nhật tòa nhà phân công quản lý
        $stmtDelBld = $pdo->prepare('DELETE FROM nhanvien_toanha WHERE MaNV = ?');
        $stmtDelBld->execute([$id]);

        if (!empty($selectedBuildings)) {
            $stmtInsBld = $pdo->prepare('INSERT INTO nhanvien_toanha (MaNV, DiaChi) VALUES (?, ?)');
            foreach ($selectedBuildings as $bld) {
                $bld = trim((string)$bld);
                if ($bld !== '') {
                    $stmtInsBld->execute([$id, $bld]);
                }
            }
        }

        $pdo->commit();

        refreshStaffBuildingSession();
        logAudit('GRANT_PERMISSIONS', 'NhanVien', (string)$id, 'Cập nhật phân quyền & tòa nhà cho nhân viên: ' . $employee['HoTen']);
        setFlash('success', 'Đã cập nhật phân quyền và tòa nhà thành công cho nhân viên "' . e($employee['HoTen']) . '".');
        redirect('/admin/nhan-vien/index.php');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', 'Có lỗi xảy ra khi cập nhật phân quyền: ' . $ex->getMessage());
    }
}

// Lấy danh sách tất cả các quyền nhóm theo NhomQuyen
$allPermissions = $pdo->query('SELECT * FROM quyen ORDER BY NhomQuyen ASC, MaQuyen ASC')->fetchAll();
$groupedPermissions = [];
foreach ($allPermissions as $p) {
    $groupedPermissions[$p['NhomQuyen']][] = $p;
}

// Lấy danh sách ID các quyền nhân viên hiện đang có
$stmtCurrent = $pdo->prepare('SELECT MaQuyen FROM phanquyen WHERE MaNV = ?');
$stmtCurrent->execute([$id]);
$currentPermIds = $stmtCurrent->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Lấy danh sách tòa nhà nhân viên hiện được phân công
$stmtBldCurrent = $pdo->prepare('SELECT DiaChi FROM nhanvien_toanha WHERE MaNV = ?');
$stmtBldCurrent->execute([$id]);
$currentAssignedBuildings = $stmtBldCurrent->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Lấy danh sách tất cả các tòa nhà kèm số lượng phòng
$allBuildingsWithCount = $pdo->query('
    SELECT DiaChi, COUNT(*) AS SoPhong,
           SUM(CASE WHEN TrangThai = "Đang thuê" THEN 1 ELSE 0 END) AS SoPhongDangThue
    FROM CanHo
    WHERE DiaChi IS NOT NULL AND DiaChi <> ""
    GROUP BY DiaChi
    ORDER BY DiaChi ASC
')->fetchAll();
?>

<div class="page-header">
    <div>
        <div class="breadcrumb" style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">
            <a href="<?= url('/admin/dashboard.php') ?>" style="color: #64748b; text-decoration: none;">Hệ Thống</a> / 
            <a href="<?= url('/admin/nhan-vien/index.php') ?>" style="color: #64748b; text-decoration: none;">Quản Lý Tài Khoản Nhân Viên</a> / 
            <span style="color: #1e293b; font-weight: 500;">Phân Quyền</span>
        </div>
        <h1 class="page-title">Phân Quyền: <?= e($employee['HoTen']) ?></h1>
    </div>
    <div>
        <a href="<?= url('/admin/nhan-vien/index.php') ?>" class="btn btn-outline" style="font-weight: 600; border-radius: 8px;">
            &laquo; Quay lại danh sách
        </a>
    </div>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden;">
    <div class="card-header" style="background-color: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">
            Phân Quyền Hạn
        </h3>
        <span style="font-size: 0.88rem; color: #64748b;">
            Tài khoản: <code style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #f1f5f9; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-weight: 600;"><?= e($employee['TenDangNhap']) ?></code>
        </span>
    </div>
    <div class="card-body" style="padding: 1.75rem;">

        <?php if ($employee['VaiTro'] === 'Admin'): ?>
            <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                <span class="alert-icon"><?= svgIcon('info', '', 16) ?></span>
                <div>
                    <strong>Tài khoản Chủ Nhà:</strong> Tài khoản này mặc định có toàn bộ quyền quản trị trong hệ thống mà không cần phân quyền từng chức năng.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

            <!-- ================================================================
                 PHẦN 1: PHÂN CÔNG TÒA NHÀ / CƠ SỞ QUẢN LÝ
                 ================================================================ -->
            <div style="margin-bottom: 2rem; background: #ffffff; border: 2px solid #0284c7; border-radius: 10px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; border-bottom: 2px solid #e0f2fe; padding-bottom: 0.65rem;">
                    <div>
                        <h4 style="font-size: 1.05rem; font-weight: 800; color: #0369a1; margin: 0; display: flex; align-items: center; gap: 0.45rem;">
                            <?= svgIcon('building', '', 18) ?> Phân Công Tòa Nhà / Cơ Sở Quản Lý
                        </h4>
                        <p style="margin: 0.25rem 0 0; font-size: 0.825rem; color: #64748b;">
                            Nhân viên chỉ có quyền truy cập, xem và quản lý dữ liệu (Căn hộ, Khách thuê, Hợp đồng, Hóa đơn, Điện nước, Bảo trì) thuộc các tòa nhà được chọn.
                        </p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="toggleAllBuildings(true)" style="font-size: 0.78rem; font-weight: 600; border-radius: 6px;">
                            Chọn tất cả
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="toggleAllBuildings(false)" style="font-size: 0.78rem; font-weight: 600; border-radius: 6px;">
                            Bỏ chọn
                        </button>
                    </div>
                </div>

                <?php if (empty($allBuildingsWithCount)): ?>
                    <div style="color: #94a3b8; font-style: italic; padding: 0.75rem 0;">
                        Chưa có dữ liệu tòa nhà / địa chỉ nào trong hệ thống căn hộ.
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 0.85rem;">
                        <?php foreach ($allBuildingsWithCount as $bld): ?>
                            <?php $isBldChecked = in_array($bld['DiaChi'], $currentAssignedBuildings, true); ?>
                            <label style="display: flex; align-items: flex-start; gap: 0.75rem; background: <?= $isBldChecked ? '#f0fdf4' : '#ffffff' ?>; border: 1.5px solid <?= $isBldChecked ? '#86efac' : '#cbd5e1' ?>; border-radius: 8px; padding: 0.85rem 1rem; cursor: pointer; transition: all 0.2s;" class="building-card-label" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.06)'" onmouseout="this.style.boxShadow='none'">
                                <input type="checkbox" 
                                       name="buildings[]" 
                                       value="<?= e($bld['DiaChi']) ?>" 
                                       <?= $isBldChecked ? 'checked' : '' ?>
                                       onchange="onBuildingCheckboxChange(this)"
                                       style="width: 19px; height: 19px; margin-top: 2px; cursor: pointer; accent-color: #16a34a;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.925rem; line-height: 1.4;">
                                        <?= e($bld['DiaChi']) ?>
                                    </div>
                                    <div style="margin-top: 0.4rem; display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                                        <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.75rem; font-weight: 600; padding: 2px 7px;">
                                            <?= (int)$bld['SoPhong'] ?> phòng
                                        </span>
                                        <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-size: 0.75rem; font-weight: 600; padding: 2px 7px;">
                                            <?= (int)$bld['SoPhongDangThue'] ?> đang thuê
                                        </span>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ================================================================
                 PHẦN 2: PHÂN QUYỀN CHỨC NĂNG HỆ THỐNG
                 ================================================================ -->
            <div style="margin-bottom: 0.85rem;">
                <h4 style="font-size: 1.05rem; font-weight: 800; color: #1e293b; margin: 0 0 0.25rem; display: flex; align-items: center; gap: 0.45rem;">
                    <?= svgIcon('shield', '', 18) ?> Phân Quyền Chức Năng Nghiệp Vụ
                </h4>
                <p style="margin: 0 0 1rem; font-size: 0.825rem; color: #64748b;">
                    Lựa chọn các chức năng nghiệp vụ mà nhân viên được phép thực hiện trên tòa nhà được giao.
            </div>

            <?php foreach ($groupedPermissions as $groupName => $perms): ?>
                <div style="margin-bottom: 1.75rem; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 0.85rem; border-bottom: 2px solid #cbd5e1; padding-bottom: 0.4rem;">
                        Nhóm: <?= e($groupName) ?>
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 0.85rem;">
                        <?php foreach ($perms as $p): ?>
                            <?php $isChecked = in_array((int)$p['MaQuyen'], array_map('intval', $currentPermIds), true); ?>
                            <label style="display: flex; align-items: flex-start; gap: 0.65rem; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.75rem; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='#cbd5e1'">
                                <input type="checkbox" 
                                       name="permissions[]" 
                                       value="<?= (int)$p['MaQuyen'] ?>" 
                                       <?= $isChecked ? 'checked' : '' ?>
                                       style="width: 18px; height: 18px; margin-top: 2px; cursor: pointer;">
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 0.925rem;">
                                        <?= e($p['TenQuyen']) ?>
                                    </div>
                                    <code style="font-size: 0.75rem; color: #475569; display: block; margin-top: 2px;">
                                        <?= e($p['MaCode']) ?>
                                    </code>
                                    <?php if (!empty($p['MoTa'])): ?>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px;">
                                            <?= e($p['MoTa']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 2rem;">
                <a href="<?= url('/admin/nhan-vien/index.php') ?>" class="btn btn-outline">Hủy bỏ</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600;">
                    Lưu Cấu Hình Phân Quyền
                </button>
            </div>
        </form>
    </div>
<script>
function toggleAllBuildings(checked) {
    const checkboxes = document.querySelectorAll('input[name="buildings[]"]');
    checkboxes.forEach(cb => {
        cb.checked = checked;
        onBuildingCheckboxChange(cb);
    });
}

function onBuildingCheckboxChange(cb) {
    const label = cb.closest('.building-card-label');
    if (!label) return;
    if (cb.checked) {
        label.style.background = '#f0fdf4';
        label.style.borderColor = '#86efac';
    } else {
        label.style.background = '#ffffff';
        label.style.borderColor = '#cbd5e1';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
