<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../src/Repositories/NhanVienRepository.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Mã nhân viên không hợp lệ.');
}

$repo = new NhanVienRepository($pdo);
$employee = $repo->find($id);

if (!$employee) {
    flash('error', 'Không tìm thấy tài khoản nhân viên cần xóa.');
    redirect('/admin/nhan-vien/index.php');
}

$currentUserId = (int)($_SESSION['MaNV'] ?? 0);

// 1. Chặn xóa tài khoản đang đăng nhập
if ($id === $currentUserId) {
    flash('error', 'Bạn không thể tự xóa tài khoản của chính mình khi đang đăng nhập.');
    redirect('index.php');
}

// 2. Kiểm tra xem nhân viên có liên kết hợp đồng hay không
$hasContracts = $repo->hasRelatedContracts($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($hasContracts) {
        flash('error', 'Không thể xóa nhân viên vì đang phụ trách hợp đồng thuê căn hộ. Bạn nên chuyển trạng thái sang "Nghỉ việc".');
        redirect('index.php');
    }

    try {
        $repo->delete($id);
        flash('success', 'Đã xóa tài khoản nhân viên "' . $employee['HoTen'] . '" (' . $employee['TenDangNhap'] . ') thành công.');
    } catch (PDOException $e) {
        flash('error', 'Không thể xóa tài khoản do ràng buộc khóa ngoại với dữ liệu khác trong hệ thống.');
    }

    redirect('index.php');
}

$title = 'Xác nhận xóa nhân viên: ' . $employee['HoTen'];
require_once __DIR__ . '/../../includes/header.php';
?>
<!-- Nạp CSS theo đường dẫn tương đối -->
<link rel="stylesheet" href="../../assets/css/style.css">

<section class="form-card delete-card">
    <div class="delete-icon">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
    </div>

    <h1>Xác nhận xóa tài khoản</h1>

    <?php if ($hasContracts): ?>
        <div class="alert warning" style="text-align: left;">
            <strong>Lưu ý quan trọng:</strong>
            <p style="margin: 6px 0 0;">
                Nhân viên <strong><?= e($employee['HoTen']) ?></strong> đang được gán phụ trách hợp đồng thuê căn hộ trong hệ thống.
                Theo quy tắc toàn vẹn dữ liệu, bạn <strong>không thể xóa vĩnh viễn</strong> tài khoản này.
            </p>
            <p style="margin: 6px 0 0;">
                Thay vào đó, bạn có thể chỉnh sửa trạng thái tài khoản thành <strong>"Nghỉ việc"</strong> để khóa đăng nhập.
            </p>
        </div>

        <div class="actions" style="justify-content: center; margin-top: 20px;">
            <a class="button" href="edit.php?id=<?= $id ?>">
                Chuyển sang "Nghỉ việc"
            </a>
            <a class="button secondary" href="index.php">
                Quay lại danh sách
            </a>
        </div>
    <?php else: ?>
        <p class="muted">Thao tác này sẽ xóa vĩnh viễn tài khoản khỏi hệ thống và không thể khôi phục.</p>

        <div class="employee-info-box">
            <div><strong>Họ tên:</strong> <?= e($employee['HoTen']) ?></div>
            <div><strong>Tên đăng nhập:</strong> <code><?= e($employee['TenDangNhap']) ?></code></div>
            <div><strong>Vai trò:</strong> <?= e($employee['VaiTro']) ?></div>
            <div><strong>Email:</strong> <?= e($employee['Email'] ?: 'Chưa cập nhật') ?></div>
            <div><strong>Trạng thái:</strong> <?= e($employee['TrangThai']) ?></div>
        </div>

        <form method="post" class="delete-form">
            <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="actions" style="justify-content: center;">
                <button type="submit" class="button btn-danger">
                    Xác nhận xóa vĩnh viễn
                </button>
                <a class="button secondary" href="index.php">
                    Hủy bỏ
                </a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
