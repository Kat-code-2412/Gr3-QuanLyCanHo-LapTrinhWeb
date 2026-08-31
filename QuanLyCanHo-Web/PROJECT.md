# PROJECT - Quan ly can dich vu

Tai lieu dieu phoi chung cho nhom 6 nguoi. Day la ke hoach implementation, khong phai tai lieu code.

Schema su dung: `QuanLyCanHo_database.sql`, MySQL 8.0+, database `quanlycandichvu`, charset `utf8mb4`.

## 1. Tong quan & Database

### 1.1 Pham vi he thong

He thong quan ly can dich vu co hai khu vuc:

- `/admin`: chu nha, xem dashboard, quan ly toan bo du lieu va tai khoan.
- `/user`: nhan vien, thao tac nghiep vu duoc cap phep; khong quan ly tai khoan, cau hinh he thong hoac xoa du lieu nhay cam.

Quy tac chung: moi du lieu nhap tu form phai duoc validate lai o server; prepared statement la bat buoc; chi hien thong bao thanh cong/that bai qua session flash va Toastify.

### 1.2 12 bang nghiep vu

Bang va trach nhiem du kien (ten cot cu the phai lay tu file SQL):

| Bang | Vai tro | Quan he chinh |
|---|---|---|
| `LoaiCanHo` | Danh muc loai can, gia thue chuan, mo ta | Mot loai co nhieu `CanHo` |
| `NhanVien` | Tai khoan dang nhap, ho ten, lien he, `VaiTro`, `TrangThai` | `MaNV` duoc gan vao `HopDong` |
| `CanHo` | So phong, loai, dien tich, gia thue, trang thai, mo ta | Thuoc `LoaiCanHo`; duoc tham chieu boi `HopDong`, `YeuCauBaoTri` |
| `CanHo_Anh` | Nhieu anh/can ho, duong dan, anh dai dien, thu tu hien thi | Thuoc `CanHo`; khong co du lieu mau, chi co du lieu khi module upload chay that |
| `KhachThue` | Ho so nguoi thue, CCCD, ngay sinh, gioi tinh, lien he | Duoc gan vao `HopDong`, `YeuCauBaoTri` |
| `HopDong` | Can, khach, nhan vien phu trach, ngay thue, gia thoa thuan, coc, trang thai | Tao `HoaDon`; thanh ly qua `SP_ThanhLyHopDong` |
| `DichVu` | Danh muc dich vu, don gia, don vi, mo ta | Duoc gan vao hoa don qua `HoaDon_DichVu` |
| `HoaDon` | Hoa don theo `KyThanhToan` MM/YYYY, cac khoan tien, tong tien, trang thai | Thuoc `HopDong`; co `LichSuThanhToan` |
| `HoaDon_DichVu` | Bang trung gian hoa don - dich vu, so luong, thanh tien | Khoa chinh ghep `MaHoaDon`, `MaDichVu` |
| `LichSuThanhToan` | Tung lan thanh toan, so tien, hinh thuc, thoi diem | Thuoc `HoaDon`; khong co `MaNV` |
| `YeuCauBaoTri` | Can, khach, noi dung, tiep nhan/hoan thanh, trang thai, chi phi, ghi chu | Khong co cot nguoi xu ly trong schema |
| `LichSuThayDoi` | Audit log bang, hanh dong, gia tri cu/moi, thoi gian | Doc/ghi theo quy uoc audit cua nhom; khong co FK |

### 1.3 ERD dang text

```text
LoaiCanHo 1 -------- N CanHo
                         |
                         +------------- N CanHo_Anh
                         |
                         +------------- N HopDong N ------------------- 1 KhachThue
                                           |
                                           +------------- N HoaDon
                                                            |
                                                            +---------- N LichSuThanhToan
                                                            |
                                                            +---------- N HoaDon_DichVu N -------- 1 DichVu

CanHo 1 ---------------- N YeuCauBaoTri N ---------------- 1 KhachThue
NhanVien 1 ------------- N HopDong
LichSuThayDoi doc lap, luu audit theo TenBang/HanhDong
```

### 1.4 View va stored procedure

- `View_DoanhThuTheoThang`: nguon duy nhat cho bieu do doanh thu theo thang; dashboard chi doc view, khong tinh lai doanh thu bang PHP.
- `View_HoaDonChuaThanhToan`: danh sach cong no; dung cho dashboard, loc va xuat bao cao.
- `View_LichSuThueCanHo`: lich su thue theo can/khach/hop dong; dung cho bao cao va tra cuu.
- `View_DanhSachCanHo`: danh sach can ket hop `CanHo` va `LoaiCanHo`; dung cho trang danh sach.
- `SP_TaoHoaDonHangThang(IN p_KyThanhToan VARCHAR(7))`: nhan ky dang `MM/YYYY`, tao hoa don cho hop dong `Đang hiệu lực`, tranh trung ky.
- `SP_ThanhToanHoaDon(IN p_MaHoaDon INT, IN p_SoTien DECIMAL(18,2), IN p_HinhThuc VARCHAR(20))`: ho tro thanh toan tung phan, them vao `LichSuThanhToan` va tu cap nhat trang thai.
- `SP_ThanhLyHopDong(IN p_MaHopDong INT)`: tu choi neu con hoa don `Chưa TT`/`Quá hạn`, sau do doi hop dong thanh `Đã thanh lý` va can ho thanh `Trống`.

### 1.5 Quyet dinh thiet ke bat buoc

1. Tat ca truy van co input dung PDO prepared statements, khong noi chuoi SQL voi du lieu form.
2. Khong xoa cung bang khi da co hop dong, hoa don, lich su thanh toan hoac yeu cau bao tri. Dung trang thai `Inactive/Ngung su dung` neu schema cho phep va canh bao truoc thao tac.
3. Tien dung `DECIMAL` cua MySQL va format o view; khong dung float de tinh tien. Ngay gio luu theo timezone server thong nhat, hien thi theo `dd/mm/yyyy`.
4. Upload anh chi luu ten file ngau nhien, kiem tra MIME, extension, dung luong, ten file va anh dai dien; khong tin `$_FILES['type']` mot cach don doc.
5. Moi thao tac thay doi du lieu phai ghi `created_at/updated_at` neu schema co cac cot nay va phai giu transaction cho cac cap nhat lien quan.

## 2. Kien truc & Quy uoc code chung

### 2.1 Cau truc thu muc de xuat

```text
/
  admin/                  # page/controller rieng cho chu nha
  user/                   # page/controller rieng cho nhan vien
  auth/                   # login, logout, session guard
  config/                 # env.php, database.php, app.php
  includes/               # header, footer, sidebar, csrf, flash
  src/
    Repositories/         # SQL/PDO, khong render HTML
    Services/             # transaction, upload, goi stored procedure
    Validators/           # validate server-side
  assets/css/             # CSS dung chung va theo khu vuc
  assets/js/              # Toastify, Chart.js, Swiper, page scripts
  uploads/can-ho/         # anh da upload, deny execute script
  templates/              # layout, form partial, pagination
  database/               # luu schema SQL neu duoc bo sung vao repo
  PROJECT.md
```

Moi page nhan GET/POST, goi service/repository va redirect sau POST (PRG). Khong dat SQL trong file template.

### 2.2 Quy uoc code

- File PHP: `kebab-case.php` hoac theo convention da chot; class: `PascalCase`; method/variable: `camelCase`; constant: `UPPER_SNAKE_CASE`.
- Bat dau file PHP bang `declare(strict_types=1);` neu project chay PHP 8+.
- Bat buoc `htmlspecialchars` khi render text; CSRF token cho moi POST; phan quyen phai kiem tra o server.
- Dung `require_once` cho config/layout; khong copy chuoi ket noi DB vao module.
- `config/database.php` tao mot PDO dung chung, `ATTR_ERRMODE => EXCEPTION`, tat emulate prepares.
- Repository tra ve array/entity thuan, Service dieu phoi transaction va exception; template chi hien thi.
- Pagination dung `page`, `perPage` whitelist trong khoang 10-100, query co `LIMIT/OFFSET` sau khi ep kieu so.
- Moi module phai co README/ghi chu endpoint, field mapping va it nhat mot checklist test trong pull request.

### 2.3 Ket qua va thong bao

Thanh cong: redirect ve danh sach va flash `success`. Loi validate: giu lai form, hien loi theo field. Loi DB/exception: ghi log noi bo, hien thong bao chung. Toastify chi hien text da escape, khong hien SQL exception cho nguoi dung.

## 3. Module: Auth & Phan quyen

**Trang thai:** Chua lam  
**Nguoi phu trach:** ___

### Pham vi

- Tao `auth/login.php`, `auth/logout.php`, middleware/guard dung chung cho `/admin` va `/user`.
- Form login: username/email theo schema `NhanVien`, mat khau. Validate bat buoc, gioi han do dai hop ly, thong bao chung khi sai tai khoan hoac mat khau.
- Dung `password_verify` neu mat khau luu hash; khi tao/doi mat khau dung `password_hash`. Khong log mat khau.
- Login thanh cong: `session_regenerate_id(true)`, luu ID nhan vien, `VaiTro`, ten hien thi; logout huy session va cookie.
- Guard: chua dang nhap -> redirect login; sai vai tro -> HTTP 403/redirect. Khong chi an menu de bao ve URL.
- Admin duoc CRUD `NhanVien`, phan cong/vo hieu hoa tai khoan qua `TrangThai`. User khong duoc sua vai tro, xoa nhan vien hoac xem mat khau.
- Chong brute force co ban: thong bao khong tiet lo tai khoan ton tai; co the them rate limit theo session/IP neu pham vi mon hoc cho phep.

### Trang va validate

- `login.php`: tai khoan khong rong; mat khau khong rong.
- `admin/nhan-vien/index.php`: loc, phan trang, xem vai tro/trang thai.
- `admin/nhan-vien/create.php`, `edit.php`: `HoTen`, `TenDangNhap`, `MatKhau` khi tao, `VaiTro`, `SoDienThoai`, `Email`, `TrangThai`; `TenDangNhap` unique; `VaiTro` chi nhan `Admin`/`NhanVien`.
- `admin/nhan-vien/delete.php`: POST + CSRF; chan xoa tai khoan dang dang nhap va tai khoan dang duoc tham chieu neu co FK.

## 4. Module: Quan ly Can ho

**Trang thai:** Chua lam  
**Nguoi phu trach:** ___

### Bang lien quan

`CanHo`, `LoaiCanHo`, `CanHo_Anh`; hien thi can ho dang duoc thue dua tren `HopDong`. Schema khong co bang tien ich hoac bang lien ket can-tien ich (ngoai pham vi dot nay).

### Trang can code

- `admin/loai-can-ho/index.php`, `create.php`, `edit.php`: danh sach, tim kiem, CRUD loai can.
- `admin/can-ho/index.php`: loc theo loai/trang thai, tim theo ma/so can, phan trang.
- `admin/can-ho/create.php`, `edit.php`, `detail.php`: thong tin can va hop dong lien quan; upload nhieu anh (luu vao `CanHo_Anh`), chon 1 anh dai dien (`LaAnhDaiDien`), sap xep thu tu hien thi, xoa anh rieng le.
- `/user/can-ho/*`: xem va cap nhat cac truong nghiep vu duoc phep; khong xoa neu khong duoc cap quyen.

### Form va validate

- `CanHo`: `SoPhong` bat buoc va unique, `MaLoai` ton tai, `DienTich` > 0, `GiaThue` >= 0, `TrangThai` chi la `Trống`/`Đang thuê`/`Bảo trì`, `MoTa` toi da 255 ky tu.
- `LoaiCanHo`: `TenLoai` bat buoc toi da 50 ky tu, `GiaThueChuan` >= 0, `MoTa` toi da 255 ky tu. CKEditor chi nen dung cho noi dung ngan va phai strip/whitelist HTML truoc khi luu vao VARCHAR(255).
- Khong cho xoa loai dang duoc `CanHo` tham chieu; khong cho xoa can co `HopDong`, `HoaDon` hoac `YeuCauBaoTri`.
- `CanHo_Anh`: kiem tra MIME/extension/dung luong truoc khi luu (theo quyet dinh 1.5 muc 4); moi can luon co dung 1 anh `LaAnhDaiDien = 1`; xoa anh phai xoa ca file vat ly trong `uploads/can-ho/`.

## 5. Module: Hop dong & Thanh toan

**Trang thai:** Chua lam  
**Nguoi phu trach:** ___

### Bang lien quan va luong nghiep vu

`KhachThue` -> `HopDong` -> `HoaDon` -> `LichSuThanhToan`, cung voi `CanHo`, `NhanVien`, `DichVu` va `HoaDon_DichVu`. Khi lap hop dong phai kiem tra can khong co hop dong hieu luc chong lap; khi thanh toan phai cap nhat hoa don qua SP.

### Trang can code

- `admin/hop-dong/index.php`, `detail.php`, `create.php`, `edit.php`: loc trang thai/ngay, phan trang, xem chi tiet.
- `admin/hop-dong/thanh-ly.php`: form thanh ly, goi `SP_ThanhLyHopDong`.
- `admin/hoa-don/index.php`, `detail.php`: loc ky/trang thai/khach/can, xem cong no.
- `admin/hoa-don/tao-hang-thang.php`: chon thang/nam hoac hop dong, goi `SP_TaoHoaDonHangThang`, hien so thanh cong/trung/loi.
- `admin/hoa-don/thanh-toan.php`: goi `SP_ThanhToanHoaDon`, in/ xem bien nhan lich su.
- `/user/hop-dong/*`, `/user/hoa-don/*`: xem, lap hoa don va ghi nhan thanh toan theo quyen duoc cap; khong thanh ly neu khong phai admin.

### Form va validate

- `HopDong`: chon can va khach ton tai; ngay bat dau <= ngay ket thuc; tien thue/tien coc >= 0; trang thai whitelist; khong trung hop dong hieu luc cung can; file hop dong (neu schema co) kiem tra MIME.
- `HoaDon`: `KyThanhToan` dung regex `^(0[1-9]|1[0-2])/\d{4}$`; cac khoan `TienThue`, `TienDien`, `TienNuoc`, `TienDichVu`, `TongTien` khong am; `TrangThai` la `Chưa TT`/`Đã TT`/`Quá hạn`; khong tao trung `MaHopDong` + ky.
- Dich vu tren hoa don: chon `DichVu` ton tai, `SoLuong` la so nguyen duong, `ThanhTien` >= 0; khoa ghep khong duoc trung. Khong nhap `MaNV` cho thanh toan vi schema khong co cot nay.
- Thanh toan: hoa don ton tai; `SoTien` > 0; `HinhThuc` toi da 20 ky tu va whitelist `Tiền mặt`/`Chuyển khoản`; SP cho phep thanh toan tung phan, vi vay khong chan vuot so du o PHP neu muon giu dung contract hien tai.
- Thanh ly: chi nhan `MaHopDong`; SP tu tu choi khi con hoa don `Chưa TT`/`Quá hạn`, khong tao form ngay thanh ly/ly do vi schema va SP khong co cac field do.
- Moi SP phai duoc goi qua stored procedure call dung signature, bat exception va rollback; sau thanh cong redirect de tranh submit lai.

## 6. Module: Khach thue & Bao tri

**Trang thai:** Xong  
**Nguoi phu trach:** Nguyen Minh Thao  
**File da tao:** `config/database.php`, `config/env.php`, `.env.example`, `includes/header.php`, `includes/footer.php`, `config/test-connection.php`, `README.md`

### Trang can code

- `admin/khach-thue/index.php`, `create.php`, `edit.php`, `detail.php`: tim theo ho ten/so dien thoai/giay to, phan trang, xem hop dong lien quan.
- `admin/bao-tri/index.php`: loc can, khach, trang thai, khoang ngay tiep nhan.
- `admin/bao-tri/create.php`, `edit.php`, `detail.php`: tao yeu cau, phan cong, cap nhat tien do/chi phi, dong yeu cau.
- `/user/khach-thue/*`: xem khach gan voi nghiep vu; `/user/bao-tri/*`: tao va xu ly yeu cau trong pham vi.

### Form va validate

- `KhachThue`: ho ten bat buoc; so dien thoai dung format va do dai hop ly; email dung format neu co; CCCD/giay to unique neu schema quy dinh; dia chi va ghi chu gioi han do dai.
- `YeuCauBaoTri`: `MaCanHo` va `MaKhach` ton tai; `NoiDung` bat buoc toi da 255 ky tu; `TrangThai` can theo cac gia tri du lieu mau (`Đã tiếp nhận`, `Đang xử lý`, `Hoàn thành`); `ChiPhi` >= 0; `NgayHoanThanh` khong som hon `NgayTiepNhan`; `GhiChu` toi da 255 ky tu.
- Schema khong co muc do uu tien va nguoi xu ly; khong them hai field nay vao query/form DB. Moi chuyen trang thai phai kiem tra trang thai hien tai o server.
- Khong xoa khach dang duoc hop dong tham chieu; uu tien archive/inactive neu SQL co cot trang thai.

## 7. Module: Admin Dashboard & Bao cao

**Trang thai:** Chua lam  
**Nguoi phu trach:** ___

### Trang va du lieu

- `admin/index.php`: KPI tong so can, can dang thue, hoa don chua thanh toan, yeu cau bao tri dang mo. KPI phai co query ro rang va filter thoi gian neu can.
- Bieu do doanh thu: doc `View_DoanhThuTheoThang`, cho chon nam/khoang thoi gian, tra JSON da validate de Chart.js ve line/bar chart.
- Bang cong no: doc `View_HoaDonChuaThanhToan`, phan trang va link sang chi tiet hoa don.
- Bao cao lich su thue: doc `View_LichSuThueCanHo`, loc theo can/khach/trang thai/ngay; co nut xuat CSV neu duoc giao.
- Bao cao khong duoc update view; query view chi co whitelist cot sap xep va filter bang prepared statement. `View_DoanhThuTheoThang` chi tinh hoa don `Đã TT`.

### UX va validate

- Date/month filter phai hop le, gioi han khoang truy van de tranh query qua lon; nam la so nguyen trong khoang hop ly.
- Chart co empty state, loading state, loi tai du lieu; tooltip hien dung don vi tien te va khong lam tron sai so.
- Dashboard chi cho role admin; user chi xem cac bao cao duoc cap phep neu nhom thong nhat them route rieng.
- Toastify cho ket qua thao tac; Chart.js import tai asset dung version da chot; Swiper dung cho banner/thong bao noi bo neu co du lieu, co fallback khi khong co anh.

## 8. Changelog

| Ngay | Module | Viec da lam |
|---|---|---|
| 2026-08-31 | Kien truc & Setup chung | Tao cau truc thu muc chuan, file PDO MySQL chung, layout header/footer theo vai tro, mau env va file kiem tra ket noi; check syntax PHP ban dau. |
| 2026-08-31 | Kien truc & Setup chung | Test thực tế trên Laragon: import schema `quanlycandichvu`, kết nối DB thành công qua `config/test-connection.php`, và render header thật với menu khác nhau cho Admin/Nhân viên. |

### Quy trinh cap nhat changelog

Moi pull request da test xong them dung mot dong vao bang tren theo mau `YYYY-MM-DD | Ten module | Mo ta ngan + test da chay`. Khong xoa lich su cu va khong ghi cac thay doi chua merge.

## Phan cong 6 nguoi de xuat

| Nguoi | Module chinh | Pham vi phoi hop |
|---|---|---|
| 1 | Auth & phan quyen | Guard, session, CSRF, layout quyen |
| 2 | Quan ly loai can/CanHo | Upload/quan ly nhieu anh (`CanHo_Anh`), trang thai can |
| 3 | Hop dong & thanh ly | `SP_ThanhLyHopDong`, validate ngay va can trung |
| 4 | Hoa don & thanh toan | Hai SP tao hoa don/thanh toan, transaction |
| 5 | Khach thue & bao tri | CRUD, phan cong, luong trang thai |
| 6 | Dashboard & bao cao | Ba view, Chart.js, Swiper, export |

Nguoi 2-5 thong nhat voi nguoi 1 ve guard; nguoi 3-4 thong nhat signature va transaction cua stored procedure; nguoi 6 chi su dung contract query da thong nhat. Moi module ghi ro field mapping sau khi file SQL duoc bo sung.