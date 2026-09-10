<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/guard.php';
requireRole('Admin');

$isEdit = isset($employee) && !empty($employee['MaNV']);
$errors = $errors ?? [];
$old = $old ?? [];
$data = $isEdit ? $employee : [];
$data = array_merge($data, $old);

$title = $isEdit ? ('Sửa nhân viên: ' . ($employee['HoTen'] ?? '')) : 'Thêm nhân viên mới';
$currentUserId = (int)($_SESSION['MaNV'] ?? 0);
$isEditingSelf = $isEdit && ($currentUserId === (int)($employee['MaNV'] ?? 0));

require_once __DIR__ . '/../../includes/header.php';
?>
<!-- Nạp CSS theo đường dẫn tương đối -->
<link rel="stylesheet" href="../../assets/css/style.css">

<section class="form-card-wide">
    <div class="card-header">
        <a class="back-link" href="index.php">
            &larr; Quay lại danh sách nhân viên
        </a>
        <h1><?= e($title) ?></h1>
        <p class="muted">
            <?= $isEdit ? 'Chỉnh sửa thông tin tài khoản và vai trò nhân viên trong hệ thống.' : 'Tạo mới tài khoản để nhân viên hoặc quản trị viên đăng nhập.' ?>
        </p>
    </div>

    <?php if (!empty($errors['db'])): ?>
        <div class="alert error"><?= e($errors['db']) ?></div>
    <?php elseif (!empty($errors)): ?>
        <div class="alert error">Vui lòng kiểm tra lại các trường dữ liệu được đánh dấu màu đỏ dưới đây.</div>
    <?php endif; ?>

    <form method="post" novalidate class="crud-form">
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$employee['MaNV'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <!-- Họ tên -->
            <div class="form-group">
                <label for="HoTen">
                    Họ và tên <span class="required">*</span>
                </label>
                <input id="HoTen" name="HoTen" maxlength="100" required
                       placeholder="Ví dụ: Nguyễn Văn A"
                       value="<?= e($data['HoTen'] ?? '') ?>"
                       class="<?= isset($errors['HoTen']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['HoTen'])): ?>
                    <small class="field-error"><?= e($errors['HoTen']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Tên đăng nhập -->
            <div class="form-group">
                <label for="TenDangNhap">
                    Tên đăng nhập <span class="required">*</span>
                </label>
                <input id="TenDangNhap" name="TenDangNhap" maxlength="50" required
                       placeholder="Ví dụ: nva_staff hoặc nva123"
                       value="<?= e($data['TenDangNhap'] ?? '') ?>"
                       class="<?= isset($errors['TenDangNhap']) ? 'is-invalid' : '' ?>">
                <small class="field-hint">Từ 3-50 ký tự, chỉ gồm chữ cái, số, ., _, -</small>
                <?php if (isset($errors['TenDangNhap'])): ?>
                    <small class="field-error"><?= e($errors['TenDangNhap']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Mật khẩu -->
            <div class="form-group">
                <label for="MatKhau">
                    Mật khẩu <?= $isEdit ? '<span class="muted">(Để trống nếu giữ nguyên mật khẩu cũ)</span>' : '<span class="required">*</span>' ?>
                </label>
                <input id="MatKhau" type="password" name="MatKhau" maxlength="255"
                       placeholder="<?= $isEdit ? 'Chỉ nhập nếu muốn đổi mật khẩu...' : 'Tối thiểu 6 ký tự...' ?>"
                       <?= $isEdit ? '' : 'required' ?>
                       class="<?= isset($errors['MatKhau']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['MatKhau'])): ?>
                    <small class="field-error"><?= e($errors['MatKhau']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Vai trò -->
            <div class="form-group">
                <label for="VaiTro">
                    Vai trò hệ thống <span class="required">*</span>
                </label>
                <?php if ($isEditingSelf): ?>
                    <input type="text" disabled value="Admin (Chủ nhà)" class="disabled-input">
                    <input type="hidden" name="VaiTro" value="Admin">
                    <small class="field-hint">Bạn không thể tự hạ quyền của chính mình khi đang đăng nhập.</small>
                <?php else: ?>
                    <select id="VaiTro" name="VaiTro" class="<?= isset($errors['VaiTro']) ? 'is-invalid' : '' ?>">
                        <option value="NhanVien" <?= (($data['VaiTro'] ?? '') === 'NhanVien') ? 'selected' : '' ?>>
                            Nhân viên (Quyền thao tác nghiệp vụ)
                        </option>
                        <option value="Admin" <?= (($data['VaiTro'] ?? '') === 'Admin') ? 'selected' : '' ?>>
                            Admin (Chủ nhà - Toàn quyền)
                        </option>
                    </select>
                    <?php if (isset($errors['VaiTro'])): ?>
                        <small class="field-error"><?= e($errors['VaiTro']) ?></small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Số điện thoại -->
            <div class="form-group">
                <label for="SoDienThoai">Số điện thoại</label>
                <input id="SoDienThoai" name="SoDienThoai" maxlength="15"
                       placeholder="Ví dụ: 0901234567"
                       value="<?= e($data['SoDienThoai'] ?? '') ?>"
                       class="<?= isset($errors['SoDienThoai']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['SoDienThoai'])): ?>
                    <small class="field-error"><?= e($errors['SoDienThoai']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="Email">Địa chỉ Email</label>
                <input id="Email" type="email" name="Email" maxlength="100"
                       placeholder="Ví dụ: user@example.com"
                       value="<?= e($data['Email'] ?? '') ?>"
                       class="<?= isset($errors['Email']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['Email'])): ?>
                    <small class="field-error"><?= e($errors['Email']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Trạng thái -->
            <div class="form-group full-width">
                <label for="TrangThai">
                    Trạng thái hoạt động <span class="required">*</span>
                </label>
                <?php if ($isEditingSelf): ?>
                    <input type="text" disabled value="Đang làm việc" class="disabled-input">
                    <input type="hidden" name="TrangThai" value="Đang làm việc">
                    <small class="field-hint">Không thể tự chuyển tài khoản của bạn sang trạng thái "Nghỉ việc".</small>
                <?php else: ?>
                    <select id="TrangThai" name="TrangThai" class="<?= isset($errors['TrangThai']) ? 'is-invalid' : '' ?>">
                        <option value="Đang làm việc" <?= (($data['TrangThai'] ?? 'Đang làm việc') === 'Đang làm việc') ? 'selected' : '' ?>>
                            Đang làm việc (Cho phép đăng nhập)
                        </option>
                        <option value="Nghỉ việc" <?= (($data['TrangThai'] ?? '') === 'Nghỉ việc') ? 'selected' : '' ?>>
                            Nghỉ việc (Khóa đăng nhập)
                        </option>
                    </select>
                    <?php if (isset($errors['TrangThai'])): ?>
                        <small class="field-error"><?= e($errors['TrangThai']) ?></small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="actions form-actions">
            <button type="submit" class="button btn-primary">
                <?= $isEdit ? 'Lưu thay đổi' : 'Tạo nhân viên' ?>
            </button>
            <a class="button secondary" href="index.php">Hủy bỏ</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
