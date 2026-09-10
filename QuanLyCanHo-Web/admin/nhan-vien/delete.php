<?php

declare(strict_types=1);

$title = 'Xác Nhận Xóa Nhân Viên';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url('/admin/nhan-vien');
$currentUserId = (int)($_SESSION['MaNV'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Mã nhân viên không hợp lệ.');
    redirect('/admin/nhan-vien/index.php');
}

// 1. Cơ chế tự bảo vệ: Chặn xóa chính mình
if ($id === $currentUserId) {
    setFlash('error', 'Cơ chế an toàn: Bạn không thể xóa tài khoản của chính mình khi đang đăng nhập!');
    redirect('/admin/nhan-vien/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM NhanVien WHERE MaNV = ?');
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    setFlash('error', 'Không tìm thấy nhân viên cần xóa.');
    redirect('/admin/nhan-vien/index.php');
}

// 2. Kiểm tra ràng buộc khóa ngoại (Hợp đồng đang phụ trách)
$stmtContracts = $pdo->prepare('SELECT COUNT(*) FROM HopDong WHERE MaNV = ?');
$stmtContracts->execute([$id]);
$contractCount = (int)$stmtContracts->fetchColumn();

// 3. Xử lý xóa khi submit form POST có CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($contractCount > 0) {
        setFlash('error', 'Không thể xóa nhân viên này vì đang phụ trách ' . $contractCount . ' hợp đồng. Hãy chuyển sang "Nghỉ việc" thay vì xóa.');
        redirect('/admin/nhan-vien/index.php');
    }

    try {
        $delStmt = $pdo->prepare('DELETE FROM NhanVien WHERE MaNV = ?');
        $delStmt->execute([$id]);
        setFlash('success', 'Đã xóa tài khoản nhân viên "' . $employee['HoTen'] . '" thành công.');
        redirect('/admin/nhan-vien/index.php');
    } catch (Throwable $ex) {
        setFlash('error', 'Lỗi khi xóa nhân viên: ' . $ex->getMessage());
        redirect('/admin/nhan-vien/index.php');
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Xóa Tài Khoản Nhân Viên</h1>
        <p class="page-subtitle">Xác nhận trước khi xóa dữ liệu nhân sự khỏi hệ thống</p>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">
            ← Quay lại danh sách
        </a>
    </div>
</div>

<div class="card" style="max-width: 650px; margin: 0 auto; border-top: 4px solid <?= ($contractCount > 0) ? 'var(--warning-color)' : 'var(--danger-color)' ?>;">
    <div class="card-header" style="background-color: #f8fafc;">
        <h3 style="font-size: 1.05rem; font-weight: 600; color: <?= ($contractCount > 0) ? 'var(--warning-color)' : 'var(--danger-color)' ?>;">
            <?= ($contractCount > 0) ? '⚠️ Cảnh Báo Ràng Buộc Dữ Liệu' : '🗑️ Xác Nhận Xóa Nhân Viên' ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if ($contractCount > 0): ?>
            <div class="alert alert-warning mb-3">
                <span class="alert-icon">⚠️</span>
                <div>
                    Nhân viên <strong><?= e($employee['HoTen']) ?></strong> hiện đang đứng tên phụ trách 
                    <strong><?= $contractCount ?></strong> hợp đồng thuê căn hộ.
                    <br>Hệ thống không cho phép xóa vĩnh viễn tài khoản để giữ nguyên lịch sử hợp đồng.
                </div>
            </div>

            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                👉 <strong>Giải pháp an toàn đề xuất:</strong> Chuyển trạng thái của nhân viên sang 
                <span class="badge badge-danger">Nghỉ việc</span> để vô hiệu hóa tài khoản và ngăn nhân viên đăng nhập.
            </p>

            <div style="display: flex; gap: 0.75rem;">
                <a href="<?= $baseUrl ?>/edit.php?id=<?= (int)$employee['MaNV'] ?>" class="btn btn-primary">
                    Chuyển sang "Nghỉ việc"
                </a>
                <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">
                    Hủy bỏ & Quay lại
                </a>
            </div>
        <?php else: ?>
            <p style="color: var(--text-secondary); margin-bottom: 1.25rem;">
                Bạn có chắc chắn muốn xóa vĩnh viễn tài khoản nhân viên dưới đây không? Thao tác này sẽ không thể khôi phục.
            </p>

            <div class="detail-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                <div class="detail-item">
                    <div class="detail-label">Mã nhân viên</div>
                    <div class="detail-value">#<?= (int)$employee['MaNV'] ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Họ và tên</div>
                    <div class="detail-value"><strong><?= e($employee['HoTen']) ?></strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tên đăng nhập</div>
                    <div class="detail-value"><code><?= e($employee['TenDangNhap']) ?></code></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Vai trò</div>
                    <div class="detail-value">
                        <span class="badge <?= ($employee['VaiTro'] === 'Admin') ? 'badge-info' : 'badge-secondary' ?>">
                            <?= e($employee['VaiTro']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-danger" style="font-weight: 600;">
                        🗑️ Đồng ý xóa vĩnh viễn
                    </button>
                    <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">
                        Hủy bỏ
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
