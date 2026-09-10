# Nhiệm vụ: Auth & Phân quyền

## 1. Mục tiêu

Hoàn thiện đăng nhập, đăng xuất, session và phân quyền cho ba nhóm tài khoản:

- Admin/chủ nhà.
- Nhân viên.
- Khách thuê.

Mọi quyền phải được kiểm tra ở phía server. Việc ẩn menu chỉ có tác dụng với giao diện, không thay thế cho guard bảo vệ URL và thao tác dữ liệu.

## 2. Trạng thái hiện tại

### Đã có

- [x] Đăng nhập Admin/Nhân viên tại `auth/login.php`.
- [x] Đăng xuất nhân viên tại `auth/logout.php`.
- [x] Đăng nhập khách thuê bằng số điện thoại và CCCD tại `auth/customer-login.php`.
- [x] Đăng xuất khách thuê tại `auth/customer-logout.php`.
- [x] Session cho nhân viên: `MaNV`, `TenDangNhap`, `HoTen`, `VaiTro`.
- [x] Session cho khách thuê: `MaKhach`, `HoTenKhach`.
- [x] Đổi session ID sau khi đăng nhập bằng `session_regenerate_id(true)`.
- [x] Guard đăng nhập chung trong `auth/guard.php`.
- [x] Guard riêng cho Admin bằng `requireAdmin()`.
- [x] Guard riêng cho khách thuê bằng `requireCustomerLogin()`.
- [x] Menu thay đổi theo vai trò trong `includes/header.php`.
- [x] Dashboard Admin và Nhân viên có kiểm tra đăng nhập.

### Chưa hoàn thiện

- [ ] Bảo vệ toàn bộ route trong `/admin` bằng quyền Admin.
- [ ] Chặn Nhân viên truy cập trực tiếp các URL quản trị.
- [ ] Chặn Nhân viên thanh lý hợp đồng nếu không có quyền.
- [ ] Tạo module quản lý tài khoản nhân viên.
- [ ] Kiểm tra trạng thái tài khoản `NhanVien.TrangThai` khi đăng nhập.
- [ ] Thêm CSRF token cho các form POST và thao tác thay đổi dữ liệu.
- [ ] Xóa fallback mật khẩu cố định `123456` trong môi trường chính thức.
- [ ] Không hiển thị chi tiết exception/database cho người dùng.
- [ ] Thêm chống brute-force cơ bản.
- [ ] Hoàn thiện kiểm tra quyền theo từng thao tác.
- [ ] Cập nhật trạng thái module trong `PROJECT.md` sau khi hoàn thành.

## 3. Phân quyền dự kiến

| Chức năng | Admin | Nhân viên | Khách thuê |
|---|---:|---:|---:|
| Xem dashboard quản trị | Có | Không | Không |
| Xem/quản lý khách thuê | Có | Có | Không |
| Xem/tạo/sửa hợp đồng | Có | Có | Không |
| Thanh lý hợp đồng | Có | Không | Không |
| Xem/tạo/cập nhật bảo trì | Có | Có | Không |
| Xem/tạo hóa đơn | Có | Theo quyền được cấp | Không |
| Ghi nhận thanh toán | Có | Theo quyền được cấp | Không |
| Xem hóa đơn của bản thân | Không áp dụng | Không áp dụng | Có |
| Quản lý tài khoản nhân viên | Có | Không | Không |
| Thay đổi vai trò tài khoản | Có | Không | Không |
| Vô hiệu hóa tài khoản | Có | Không | Không |
| Xóa tài khoản nhân viên | Có | Không | Không |

## 4. Công việc cần triển khai

### 4.1. Hoàn thiện guard

- [ ] Giữ `requireLogin()` cho route dùng chung của Admin/Nhân viên.
- [ ] Dùng `requireAdmin()` ở đầu mọi file trong `admin/` chỉ dành cho Admin.
- [ ] Không dùng logic đổi `baseUrl` để cấp quyền ngược cho Nhân viên.
- [ ] Tạo guard hoặc hàm quyền theo hành động, ví dụ `requireRole('Admin')` hoặc `requirePermission(...)` nếu cần mở rộng.
- [ ] Trả về redirect hoặc HTTP 403 rõ ràng khi sai quyền.

Các nhóm cần rà soát:

- `admin/khach-thue/*`
- `admin/bao-tri/*`
- `admin/hop-dong/*`
- `admin/hoa-don/*`
- `admin/can-ho/*` nếu được triển khai
- `admin/nhan-vien/*`
- `user/hop-dong/thanh-ly.php`

### 4.2. Quản lý tài khoản nhân viên

Tạo các file:

- [ ] `admin/nhan-vien/index.php`: danh sách, tìm kiếm, phân trang, vai trò, trạng thái.
- [ ] `admin/nhan-vien/create.php`: tạo tài khoản và hash mật khẩu bằng `password_hash()`.
- [ ] `admin/nhan-vien/edit.php`: sửa thông tin, vai trò, trạng thái; chỉ đổi mật khẩu khi nhập mật khẩu mới.
- [ ] `admin/nhan-vien/delete.php`: chỉ nhận POST, có CSRF.

Quy tắc bắt buộc:

- [ ] `TenDangNhap` không được trùng.
- [ ] `VaiTro` chỉ nhận `Admin` hoặc `NhanVien`.
- [ ] `TrangThai` chỉ nhận các trạng thái hợp lệ của hệ thống.
- [ ] Không cho tài khoản tự xóa chính mình.
- [ ] Không cho xóa tài khoản đang được tham chiếu bởi dữ liệu khác.
- [ ] Không hiển thị `MatKhau` trong danh sách hoặc form sửa.
- [ ] Chỉ Admin được truy cập toàn bộ module này.

### 4.3. Đăng nhập an toàn

- [ ] Kiểm tra username/email và mật khẩu không rỗng.
- [ ] Giới hạn độ dài dữ liệu đầu vào.
- [ ] Chỉ cho đăng nhập tài khoản đang hoạt động.
- [ ] Dùng `password_verify()` để kiểm tra mật khẩu.
- [ ] Dùng `password_hash()` khi tạo hoặc đổi mật khẩu.
- [ ] Xóa fallback mật khẩu `123456` sau giai đoạn demo.
- [ ] Dùng thông báo chung khi sai username hoặc mật khẩu.
- [ ] Không hiển thị `$exception->getMessage()` trực tiếp.
- [ ] Ghi lỗi kỹ thuật vào log nội bộ.
- [ ] Thêm giới hạn đăng nhập sai theo session/IP nếu phù hợp phạm vi môn học.

### 4.4. CSRF

- [ ] Tạo hàm sinh token CSRF trong session.
- [ ] Tạo hàm kiểm tra token bằng so sánh an toàn.
- [ ] Nhúng token vào mọi form POST thay đổi dữ liệu.
- [ ] Kiểm tra token trước khi tạo, sửa, xóa, thanh lý hoặc thanh toán.
- [ ] Xóa hoặc thay mới token sau đăng nhập nếu cần.

Các thao tác tối thiểu phải có CSRF:

- [ ] Xóa khách thuê.
- [ ] Xóa yêu cầu bảo trì.
- [ ] Tạo/sửa hợp đồng.
- [ ] Thanh lý hợp đồng.
- [ ] Tạo/sửa tài khoản nhân viên.
- [ ] Xóa tài khoản nhân viên.
- [ ] Ghi nhận thanh toán.
- [ ] Tạo hóa đơn hàng tháng.

## 5. Tiêu chí nghiệm thu

### Đăng nhập và session

- [ ] Sai tài khoản hoặc mật khẩu không tiết lộ tài khoản nào tồn tại.
- [ ] Tài khoản bị vô hiệu hóa không đăng nhập được.
- [ ] Đăng nhập thành công tạo session mới.
- [ ] Đăng xuất hủy session và cookie.
- [ ] Khách thuê không thể dùng session nhân viên để vào khu vực nhân viên.
- [ ] Nhân viên không thể dùng session khách thuê để xem hóa đơn của khách khác.

### Phân quyền URL

- [ ] Người chưa đăng nhập bị chuyển tới trang login.
- [ ] Nhân viên truy cập `/admin/*` bị từ chối.
- [ ] Khách thuê truy cập `/admin/*` và `/user/*` bị từ chối.
- [ ] Nhân viên không thể thanh lý hợp đồng bằng cách gõ URL trực tiếp.
- [ ] Chỉ Admin truy cập được `/admin/nhan-vien/*`.

### Bảo mật thao tác

- [ ] Request thiếu hoặc sai CSRF bị từ chối.
- [ ] Thao tác xóa chỉ nhận POST.
- [ ] ID trên URL được kiểm tra hợp lệ và quyền truy cập.
- [ ] Lỗi hệ thống không làm lộ SQL, đường dẫn hoặc thông tin cấu hình.

## 6. Thứ tự thực hiện đề xuất

1. Sửa guard cho toàn bộ route Admin và thao tác thanh lý.
2. Thêm kiểm tra `TrangThai` khi đăng nhập.
3. Thêm CSRF cho các thao tác thay đổi dữ liệu.
4. Tạo module quản lý nhân viên.
5. Bỏ fallback mật khẩu demo và che chi tiết exception.
6. Bổ sung chống brute-force cơ bản.
7. Kiểm thử bằng ba loại tài khoản và cập nhật trạng thái trong `PROJECT.md`.
