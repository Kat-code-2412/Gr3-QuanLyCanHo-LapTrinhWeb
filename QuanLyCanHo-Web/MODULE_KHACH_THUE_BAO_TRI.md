# BÁO CÁO & HƯỚNG DẪN THUYẾT TRÌNH MODULE KHÁCH THUÊ & BẢO TRÌ

> **Môn học**: Lập trình Web  
> **Dự án**: Website Quản lý Căn hộ Dịch vụ (`quanlycandichvu`)  
> **Module phụ trách**: 1. Quản lý Khách thuê | 2. Quản lý Bảo trì  
> **Ngôn ngữ & Công nghệ**: PHP 8+ Vanilla, PDO MySQL, HTML5, CSS3 Custom (Inter Font & Responsive), Session-based Auth.

---

## I. TỔNG QUAN CÁC FILE ĐƯỢC PHỤ TRÁCH

Dưới đây là danh sách toàn bộ các tập tin thuộc module Khách thuê và Bảo trì:

### 1. File xử lý Giao diện & Điều hướng (View / Controller)
- **`includes/header.php`**: Khai báo menu điều hướng động theo vai trò (`Admin` / `NhanVien`), nạp font chữ, CSS và hiển thị thông báo alert (Flash message).
- **`includes/footer.php`**: Khung chân trang (Footer) dùng chung.
- **`assets/css/style.css`**: File định kiểu giao diện hiện đại (Table, Badge trạng thái, Form, Card, Alert, Responsive UI).
- **`auth/login.php` & `auth/logout.php`**: Trang đăng nhập/đăng xuất hệ thống cho Admin (`admin`/`123456`) và Nhân viên (`nhanvien1`/`123456`).
- **`admin/index.php` & `user/index.php`**: Trang Dashboard tổng quan hiển thị thống kê nhanh và các lối tắt điều hướng.

### 2. Module 1: Quản lý Khách thuê (Tenant Management)
- **`admin/khach-thue/index.php`** (và `/user/khach-thue/index.php`): Trang danh sách, tìm kiếm (theo Tên, CCCD, SĐT) và phân trang.
- **`admin/khach-thue/create.php`** (và `/user/khach-thue/create.php`): Form thêm mới khách thuê kèm validate server-side và kiểm tra CCCD trùng.
- **`admin/khach-thue/edit.php`** (và `/user/khach-thue/edit.php`): Form chỉnh sửa thông tin khách thuê (bảo đảm CCCD không trùng với khách khác).
- **`admin/khach-thue/detail.php`** (và `/user/khach-thue/detail.php`): Xem chi tiết hồ sơ khách thuê, danh sách hợp đồng đã ký và lịch sử bảo trì.
- **`admin/khach-thue/delete.php`** (và `/user/khach-thue/delete.php`): Xóa khách thuê có kiểm tra ràng buộc dữ liệu hợp đồng/bảo trì.

### 3. Module 2: Quản lý Bảo trì (Maintenance Management)
- **`admin/bao-tri/index.php`** (và `/user/bao-tri/index.php`): Trang danh sách yêu cầu bảo trì (JOIN hiển thị Tên khách & Số phòng), tìm kiếm và lọc trạng thái.
- **`admin/bao-tri/create.php`** (và `/user/bao-tri/create.php`): Form ghi nhận sự cố mới (chọn căn hộ & khách thuê).
- **`admin/bao-tri/edit.php`** (và `/user/bao-tri/edit.php`): Cập nhật trạng thái xử lý (`Đã tiếp nhận`, `Đang xử lý`, `Hoàn thành`), tự động ghi nhận ngày hoàn thành và chi phí.
- **`admin/bao-tri/detail.php`** (và `/user/bao-tri/detail.php`): Xem chi tiết yêu cầu bảo trì, thông tin liên hệ của khách và căn hộ.
- **`admin/bao-tri/delete.php`** (và `/user/bao-tri/delete.php`): Xóa yêu cầu bảo trì.

### 4. File xử lý Logic & Database
- **`config/database.php`**: Khởi tạo kết nối CSDL chuẩn PDO (`PDO::ERRMODE_EXCEPTION`, `EMULATE_PREPARES => false`).
- **`auth/guard.php`**: Hàm kiểm tra đăng nhập (`requireLogin()`), phân quyền (`requireAdmin()`, `currentUserRole()`).
- **`includes/functions.php`**: Hàm mã hóa XSS (`e()`), quản lý session flash (`setFlash()`, `getFlash()`), định dạng tiền tệ (`formatMoney()`), ngày tháng (`formatDate()`) và hiển thị badge màu (`renderStatusBadge()`).

---

## II. LUỒNG HOẠT ĐỘNG THỰC TẾ (WORKFLOW)

### 1. Luồng Thêm Khách Thuê mới
1. Người dùng bấm nút **"+ Thêm Khách Thuê"** -> Chuyển đến `create.php`.
2. Điền form gồm: Họ tên, CCCD, Ngày sinh, Giới tính, SĐT, Email, Địa chỉ.
3. Bấm **"Lưu Khách Thuê"** (Submit POST):
   - Server nhận dữ liệu và thực hiện **Validate**: Bắt buộc nhập các trường chính.
   - Chạy query `SELECT COUNT(*) FROM KhachThue WHERE CCCD = ?` để kiểm tra trùng CCCD.
   - Nếu trùng hoặc thiếu dữ liệu -> Giữ lại dữ liệu trên form và báo lỗi màu đỏ ngay bên dưới ô nhập.
   - Nếu hợp lệ -> Gọi `INSERT INTO KhachThue (...) VALUES (...)` bằng PDO Prepared Statement.
   - Lưu thông báo thành công vào Session Flash và redirect về trang danh sách `index.php`.

### 2. Luồng Sửa Khách Thuê
1. Người dùng bấm nút **"Sửa"** tại dòng khách thuê -> Chuyển đến `edit.php?id=X`.
2. Server đọc `id` từ URL, truy vấn `SELECT * FROM KhachThue WHERE MaKhach = ?` đổ dữ liệu vào form.
3. Người dùng thay đổi thông tin và nộp POST:
   - Validate CCCD trùng lặp với câu SQL: `SELECT COUNT(*) FROM KhachThue WHERE CCCD = ? AND MaKhach <> ?` (loại trừ chính mã khách đang sửa).
   - Nếu hợp lệ -> Chạy `UPDATE KhachThue SET ... WHERE MaKhach = ?`.
   - Báo thành công và chuyển về trang chi tiết `detail.php?id=X`.

### 3. Luồng Xóa Khách Thuê (An toàn khóa ngoại)
1. Người dùng bấm nút **"Xóa"** -> Trình duyệt hiển thị popup xác nhận `confirm()`.
2. Chuyển đến `delete.php?id=X`:
   - Bước 1: Kiểm tra xem khách có Hợp đồng không: `SELECT COUNT(*) FROM HopDong WHERE MaKhach = ?`.
   - Bước 2: Kiểm tra xem khách có Yêu cầu bảo trì không: `SELECT COUNT(*) FROM YeuCauBaoTri WHERE MaKhach = ?`.
   - Nếu tổng số bản ghi liên quan > 0 -> **CHẶN XÓA**, thiết lập thông báo lỗi:  
     `"Không thể xóa khách thuê vì đang có dữ liệu hợp đồng/bảo trì liên quan."` và redirect về `index.php`.
   - Nếu số bản ghi = 0 -> Chạy `DELETE FROM KhachThue WHERE MaKhach = ?` và thông báo xóa thành công.

### 4. Luồng Tạo Yêu Cầu Bảo Trì
1. Người dùng bấm **"+ Tạo Yêu Cầu Bảo Trì"** -> Chuyển tới `create.php`.
2. Form cho phép chọn **Căn hộ (Số phòng)** và **Khách thuê**, nhập **Nội dung sự cố**.
3. Khi submit POST:
   - Server kiểm tra dữ liệu bắt buộc.
   - Gán tự động `NgayTiepNhan = NOW()` và `TrangThai = 'Đã tiếp nhận'`.
   - Thực thi `INSERT INTO YeuCauBaoTri (...) VALUES (...)`.
   - Chuyển hướng về danh sách kèm thông báo thành công.

### 5. Luồng Cập Nhật Tiến Độ Bảo Trì
1. Người dùng bấm **"Cập nhật"** tại yêu cầu bảo trì -> Chuyển đến `edit.php?id=X`.
2. Cho phép đổi Trạng thái (`Đã tiếp nhận` -> `Đang xử lý` -> `Hoàn thành`), nhập Chi phí và Ngày hoàn thành.
3. Logic đặc thù:
   - Nếu chọn trạng thái **"Hoàn thành"** nhưng để trống ngày hoàn thành -> Server tự động lấy thời gian hiện tại `date('Y-m-d H:i:s')`.
   - Kiểm tra **Chi phí không được là số âm** (`ChiPhi >= 0`).
   - Chạy lệnh `UPDATE YeuCauBaoTri SET ... WHERE MaBaoTri = ?`.

---

## III. CÁC CÂU LỆNH SQL QUAN TRỌNG TRONG MODULE

### 1. Query Tìm kiếm & Phân trang Khách thuê
```sql
-- Đếm tổng số để tính số trang
SELECT COUNT(*) FROM KhachThue 
WHERE HoTen LIKE :k OR CCCD LIKE :k OR SoDienThoai LIKE :k;

-- Lấy dữ liệu phân trang
SELECT * FROM KhachThue 
WHERE HoTen LIKE :k OR CCCD LIKE :k OR SoDienThoai LIKE :k 
ORDER BY MaKhach DESC 
LIMIT 10 OFFSET 0;
```

### 2. Query JOIN lấy Chi tiết Khách thuê (Hợp đồng & Bảo trì)
```sql
-- Lấy danh sách hợp đồng của khách thuê kèm số phòng
SELECT hp.*, ch.SoPhong, ch.TrangThai AS TrangThaiCanHo
FROM HopDong hp
JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
WHERE hp.MaKhach = :maKhach
ORDER BY hp.MaHopDong DESC;

-- Lấy danh sách bảo trì của khách thuê
SELECT bt.*, ch.SoPhong
FROM YeuCauBaoTri bt
JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
WHERE bt.MaKhach = :maKhach
ORDER BY bt.MaBaoTri DESC;
```

### 3. Query JOIN Danh sách Bảo trì (Hiển thị Tên khách & Số phòng)
```sql
SELECT bt.*, ch.SoPhong, kt.HoTen AS TenKhach, kt.SoDienThoai
FROM YeuCauBaoTri bt
JOIN CanHo ch ON bt.MaCanHo = ch.MaCanHo
JOIN KhachThue kt ON bt.MaKhach = kt.MaKhach
WHERE (kt.HoTen LIKE :k OR ch.SoPhong LIKE :k OR bt.NoiDung LIKE :k)
  AND bt.TrangThai = :trangThai
ORDER BY bt.MaBaoTri DESC;
```

---

## IV. BẢNG DATABASE & QUAN HỆ NGHỆP VỤ

1. **`KhachThue`** (`MaKhach`, `HoTen`, `CCCD`, `NgaySinh`, `GioiTinh`, `SoDienThoai`, `Email`, `DiaChiThuongTru`)
2. **`CanHo`** (`MaCanHo`, `SoPhong`, `MaLoai`, `DienTich`, `GiaThue`, `TrangThai`, `MoTa`)
3. **`HopDong`** (`MaHopDong`, `MaCanHo`, `MaKhach`, `MaNV`, `NgayBatDau`, `NgayKetThuc`, `GiaThueThoaThuan`, `TienCoc`, `TrangThai`)
4. **`YeuCauBaoTri`** (`MaBaoTri`, `MaCanHo`, `MaKhach`, `NoiDung`, `NgayTiepNhan`, `NgayHoanThanh`, `TrangThai`, `ChiPhi`, `GhiChu`)

**Mối quan hệ:**
- `KhachThue` **1 --- N** `HopDong`: Một khách thuê có thể ký nhiều hợp đồng thuê phòng qua các thời kỳ.
- `CanHo` **1 --- N** `HopDong`: Một căn hộ có thể có nhiều hợp đồng thuê theo thời gian.
- `KhachThue` **1 --- N** `YeuCauBaoTri`: Một khách thuê có thể gửi nhiều yêu cầu sửa chữa sự cố.
- `CanHo` **1 --- N** `YeuCauBaoTri`: Một căn hộ có thể có nhiều đợt bảo trì.

---

## V. THẦY CÓ THỂ HỎI (10 CÂU HỎI BẢO VỆ ĐỒ ÁN & CÂU TRẢ LỜI NGẮN DỄ NHỚ)

### ❓ Câu 1: Vì sao em dùng PDO thay vì mysqli để kết nối CSDL?
> **Trả lời:** Em dùng PDO (PHP Data Objects) vì PDO hỗ trợ hướng đối tượng, làm việc được với nhiều hệ quản trị CSDL khác nhau (như MySQL, PostgreSQL, SQLite), hỗ trợ Prepared Statement an toàn và cơ chế bắt lỗi ngoại lệ `PDOException` rất chuyên nghiệp.

### ❓ Câu 2: Prepared Statement là gì và tác dụng chính của nó?
> **Trả lời:** Prepared Statement là kỹ thuật tách biệt câu lệnh SQL và dữ liệu đầu vào. Trình dịch MySQL sẽ biên dịch khung SQL trước rồi mới truyền tham số sau. Tác dụng chính là **ngăn chặn hoàn toàn lỗ hổng SQL Injection** và tăng hiệu năng khi thực thi câu lệnh nhiều lần.

### ❓ Câu 3: Làm thế nào em chống lại lỗ hổng XSS khi hiển thị dữ liệu ra HTML?
> **Trả lời:** Em viết helper `e($str)` sử dụng hàm `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`. Hàm này chuyển đổi các ký tự đặc biệt như `<`, `>`, `"`, `'`, `&` thành các mã HTML Entities, ngăn không cho script độc hại thực thi trên trình duyệt.

### ❓ Câu 4: Vì sao khi xem danh sách bảo trì lại cần sử dụng câu lệnh `JOIN`?
> **Trả lời:** Vì trong bảng `YeuCauBaoTri` chỉ lưu `MaCanHo` và `MaKhach` dạng số. Để người dùng đọc hiểu được, em cần `JOIN` sang bảng `CanHo` để lấy `SoPhong` và `JOIN` sang bảng `KhachThue` để lấy `HoTen` và `SoDienThoai`.

### ❓ Câu 5: Khi xóa một Khách thuê, vì sao phải kiểm tra dữ liệu Hợp đồng và Bảo trì trước?
> **Trả lời:** Vì các bảng `HopDong` và `YeuCauBaoTri` có khóa ngoại (`FOREIGN KEY`) tham chiếu tới `MaKhach`. Nếu xóa trực tiếp khi đã có dữ liệu liên quan sẽ gây ra lỗi gãy khóa ngoại trong MySQL (`Cannot delete or update a parent row`). Hệ thống của em kiểm tra trước và hiển thị thông báo lỗi thân thiện cho người dùng.

### ❓ Câu 6: CRUD là gì và em đã áp dụng nó như thế nào trong module Khách thuê?
> **Trả lời:** CRUD đại diện cho 4 thao tác cơ bản:
> - **C**reate (Thêm mới): File `create.php` (lệnh `INSERT`).
> - **R**ead (Xem/Đọc): File `index.php` & `detail.php` (lệnh `SELECT`).
> - **U**pdate (Cập nhật): File `edit.php` (lệnh `UPDATE`).
> - **D**elete (Xóa): File `delete.php` (lệnh `DELETE`).

### ❓ Câu 7: Trong module Bảo trì, em xử lý logic Ngày hoàn thành và Chi phí như thế nào?
> **Trả lời:**
> - Nếu trạng thái là **"Hoàn thành"** mà người dùng không nhập ngày, hệ thống sẽ tự động gán thời gian hiện tại (`date('Y-m-d H:i:s')`). Nếu trạng thái chưa hoàn thành, ngày hoàn thành có thể để `NULL`.
> - Chi phí bảo trì được validate ở server-side để bảo đảm là số và không được nhỏ hơn 0 (`ChiPhi >= 0`).

### ❓ Câu 8: Làm sao em đảm bảo số CCCD của khách thuê không bị trùng lặp?
> **Trả lời:**
> - Trong MySQL, cột `CCCD` được thiết lập thuộc tính `UNIQUE`.
> - Trên server-side PHP, trước khi `INSERT` hoặc `UPDATE`, em chạy query `SELECT COUNT(*) FROM KhachThue WHERE CCCD = ?` để kiểm tra. Khi sửa, em thêm điều kiện `AND MaKhach <> id` để tránh tự so sánh trùng với chính bản ghi đó.

### ❓ Câu 9: Cơ chế Phân quyền giữa Admin và Nhân viên trong module này hoạt động thế nào?
> **Trả lời:** Em quản lý qua `$_SESSION['VaiTro']`. Hệ thống có hàm `requireLogin()` kiểm tra đăng nhập và `requireAdmin()` bắt buộc vai trò Admin. Cả Admin (`/admin/`) và Nhân viên (`/user/`) đều truy cập được 2 module Khách thuê & Bảo trì theo đúng phạm vi menu điều hướng được cấp.

### ❓ Câu 10: Thông báo Flash Message (Thành công / Thất bại) được lưu và xóa như thế nào?
> **Trả lời:** Em sử dụng biến Session `$_SESSION['flash_success']` và `$_SESSION['flash_error']`. Khi người dùng thực hiện xong thao tác, dữ liệu thông báo được lưu vào Session. Khi trang mới được tải, hàm `getFlash()` sẽ đọc thông báo ra hiển thị rồi xóa ngay khỏi Session (`unset`), giúp thông báo chỉ xuất hiện đúng 1 lần.
