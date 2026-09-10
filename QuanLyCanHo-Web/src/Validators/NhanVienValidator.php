<?php
declare(strict_types=1);

final class NhanVienValidator
{
    /**
     * Kiểm tra dữ liệu nhân viên từ form.
     */
    public static function validate(array $input, bool $creating = true): array
    {
        $errors = [];
        $hoTen = trim((string)($input['HoTen'] ?? ''));
        $tenDangNhap = trim((string)($input['TenDangNhap'] ?? ''));
        $matKhau = (string)($input['MatKhau'] ?? '');
        $email = trim((string)($input['Email'] ?? ''));
        $phone = trim((string)($input['SoDienThoai'] ?? ''));
        $role = (string)($input['VaiTro'] ?? '');
        $status = (string)($input['TrangThai'] ?? '');

        // 1. Họ tên
        if ($hoTen === '') {
            $errors['HoTen'] = 'Họ tên nhân viên không được để trống.';
        } elseif (mb_strlen($hoTen) > 100) {
            $errors['HoTen'] = 'Họ tên không được vượt quá 100 ký tự.';
        }

        // 2. Tên đăng nhập
        if ($tenDangNhap === '') {
            $errors['TenDangNhap'] = 'Tên đăng nhập không được để trống.';
        } elseif (mb_strlen($tenDangNhap) < 3 || mb_strlen($tenDangNhap) > 50) {
            $errors['TenDangNhap'] = 'Tên đăng nhập phải từ 3 đến 50 ký tự.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $tenDangNhap)) {
            $errors['TenDangNhap'] = 'Tên đăng nhập chỉ được chứa chữ cái, số, dấu chấm (.), gạch dưới (_) hoặc gạch nối (-).';
        }

        // 3. Mật khẩu
        if ($creating) {
            if (trim($matKhau) === '') {
                $errors['MatKhau'] = 'Mật khẩu là bắt buộc khi tạo tài khoản mới.';
            } elseif (strlen($matKhau) < 6) {
                $errors['MatKhau'] = 'Mật khẩu phải có tối thiểu 6 ký tự.';
            }
        } else {
            if ($matKhau !== '' && strlen($matKhau) < 6) {
                $errors['MatKhau'] = 'Mật khẩu mới phải có tối thiểu 6 ký tự.';
            }
        }

        // 4. Vai trò
        if (!in_array($role, ['Admin', 'NhanVien'], true)) {
            $errors['VaiTro'] = 'Vai trò chỉ được chọn là "Admin" hoặc "NhanVien".';
        }

        // 5. Trạng thái
        if (!in_array($status, ['Đang làm việc', 'Nghỉ việc'], true)) {
            $errors['TrangThai'] = 'Trạng thái chỉ được là "Đang làm việc" hoặc "Nghỉ việc".';
        }

        // 6. Số điện thoại (tùy chọn nhưng nếu có phải hợp lệ)
        if ($phone !== '') {
            if (!preg_match('/^[0-9]{9,15}$/', $phone)) {
                $errors['SoDienThoai'] = 'Số điện thoại chỉ được chứa số và từ 9 đến 15 chữ số.';
            }
        }

        // 7. Email (tùy chọn nhưng nếu có phải hợp lệ)
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                $errors['Email'] = 'Địa chỉ email không đúng định dạng hoặc vượt quá 100 ký tự.';
            }
        }

        return $errors;
    }
}
