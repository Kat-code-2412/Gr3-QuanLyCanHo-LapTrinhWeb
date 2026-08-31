# Quan lý căn dịch vụ

## 1. Mục tiêu

Dự án là hệ thống quản lý căn dịch vụ cho chủ nhà và nhân viên, theo hướng dẫn trong file `PROJECT.md`.

## 2. Cấu trúc thư mục nền tảng

- `admin/` - giao diện và logic dành cho admin
- `user/` - giao diện và logic dành cho nhân viên
- `auth/` - login/logout và guard
- `config/` - cấu hình môi trường, PDO, kiểm tra kết nối
- `includes/` - layout chung `header.php`, `footer.php`
- `src/Repositories/` - repository, truy vấn SQL/PDO
- `src/Services/` - business logic và transaction
- `src/Validators/` - validate server-side
- `assets/css/`, `assets/js/` - tập tin giao diện chung
- `uploads/can-ho/` - lưu ảnh upload
- `templates/` - layout phụ, partials
- `database/` - schema SQL / backup

## 3. Cấu hình database

1. Sao chép file `.env.example` thành `.env`
2. Chỉnh sửa các giá trị theo máy bạn:

```env
DB_HOST=127.0.0.1
DB_USER=root
DB_PASSWORD=
DB_NAME=quanlycandichvu
DB_CHARSET=utf8mb4
```

3. File `config/database.php` sẽ đọc biến môi trường và tạo PDO với:
   - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
   - `PDO::ATTR_EMULATE_PREPARES => false`

## 4. Import database SQL

1. Mở phpMyAdmin hoặc MySQL client.
2. Tạo database tên `quanlycandichvu`.
3. Import file SQL của dự án, ví dụ `QuanLyCanHo_database.sql` hoặc file tương ứng trong thư mục `database/`.
4. Đảm bảo database đang dùng charset `utf8mb4`.

## 5. Chạy thử project

### Cách 1: PHP built-in server

```bash
php -S localhost:8000
```

Truy cập:

```text
http://localhost:8000/config/test-connection.php
```

Nếu hiển thị "Kết nối database thành công" thì cấu hình đang hoạt động.

### Cách 2: Chạy trong môi trường web server khác

- Copy source vào thư mục gốc của Apache/Nginx
- Đảm bảo PHP 8+ đang bật
- Kiểm tra `mod_rewrite` nếu dùng URL đẹp

## 6. Session và vai trò

Project thống nhất tên session:

```php
$_SESSION['MaNV'];
$_SESSION['VaiTro'];
```

- `VaiTro = Admin` sẽ thấy layout admin đầy đủ menu
- `VaiTro = NhanVien` hoặc các role khác sẽ thấy layout rút gọn hơn

## 7. Kiểm tra kết nối nhanh

File test:

```text
config/test-connection.php
```

Mở trực tiếp trong trình duyệt để kiểm tra kết nối PDO.

## 8. Lưu ý quan trọng

- Không hardcode thông tin database thật vào source control.
- Dùng `require_once` cho config database và layout chung.
- Luôn dùng prepared statements cho mọi truy vấn với input từ user.
- Mỗi module sau này cần dựa trên file nền tảng này để tránh lặp lại kết nối và layout.
