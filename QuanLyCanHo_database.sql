-- =====================================================================
-- WEBSITE QUẢN LÝ CĂN DỊCH VỤ - SCRIPT MYSQL
-- Convert từ thiết kế SQL Server (môn Hệ QT CSDL) sang MySQL 8.0+
-- Gồm: Schema (11 bảng) + Dữ liệu mẫu + 4 View + 3 Stored Procedure
-- =====================================================================

CREATE DATABASE IF NOT EXISTS quanlycandichvu
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quanlycandichvu;

-- =====================================================================
-- PHẦN 1: TẠO BẢNG (CREATE TABLE)
-- =====================================================================

-- 1. LoaiCanHo -----------------------------------------------------------
CREATE TABLE LoaiCanHo (
    MaLoai        INT AUTO_INCREMENT PRIMARY KEY,
    TenLoai       VARCHAR(50)  NOT NULL,
    GiaThueChuan  DECIMAL(18,2) NOT NULL,
    MoTa          VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. NhanVien (BẢNG MỚI - chưa có trong tài liệu gốc, bổ sung vì HopDong.MaNV
--    tham chiếu tới bảng này. Đồng thời đây là bảng tài khoản đăng nhập
--    dùng chung cho cả Admin (chủ nhà) và User (nhân viên), phân biệt qua VaiTro)
CREATE TABLE NhanVien (
    MaNV          INT AUTO_INCREMENT PRIMARY KEY,
    HoTen         VARCHAR(100) NOT NULL,
    TenDangNhap   VARCHAR(50)  NOT NULL UNIQUE,
    MatKhau       VARCHAR(255) NOT NULL,        -- lưu hash (password_hash trong PHP)
    VaiTro        VARCHAR(20)  NOT NULL DEFAULT 'NhanVien', -- 'Admin' hoặc 'NhanVien'
    SoDienThoai   VARCHAR(15)  NULL,
    Email         VARCHAR(100) NULL,
    TrangThai     VARCHAR(20)  NOT NULL DEFAULT 'Đang làm việc',
    CHECK (VaiTro IN ('Admin','NhanVien'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CanHo ----------------------------------------------------------------
CREATE TABLE CanHo (
    MaCanHo     INT AUTO_INCREMENT PRIMARY KEY,
    SoPhong     VARCHAR(20)    NOT NULL UNIQUE,
    MaLoai      INT            NOT NULL,
    DienTich    FLOAT          NOT NULL,
    GiaThue     DECIMAL(18,2)  NOT NULL,
    TrangThai   VARCHAR(20)    NOT NULL DEFAULT 'Trống',
    MoTa        VARCHAR(255)   NULL,
    CHECK (TrangThai IN ('Trống','Đang thuê','Bảo trì')),
    FOREIGN KEY (MaLoai) REFERENCES LoaiCanHo(MaLoai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3b. CanHo_Anh (BẢNG MỚI - lưu nhiều ảnh/căn hộ, đáp ứng yêu cầu đề bài
--     "upload nhiều ảnh". Không có sẵn trong tài liệu gốc vì báo cáo CSDL
--     chỉ có 1 cột MoTa dạng text, không tính đến nhiều ảnh)
CREATE TABLE CanHo_Anh (
    MaAnh         INT AUTO_INCREMENT PRIMARY KEY,
    MaCanHo       INT NOT NULL,
    DuongDan      VARCHAR(255) NOT NULL,
    LaAnhDaiDien  TINYINT(1) NOT NULL DEFAULT 0,
    ThuTu         INT NOT NULL DEFAULT 0,
    FOREIGN KEY (MaCanHo) REFERENCES CanHo(MaCanHo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. KhachThue --------------------------------------------------------------
CREATE TABLE KhachThue (
    MaKhach           INT AUTO_INCREMENT PRIMARY KEY,
    HoTen             VARCHAR(100) NOT NULL,
    CCCD              VARCHAR(12)  NOT NULL UNIQUE,
    NgaySinh          DATE         NOT NULL,
    GioiTinh          VARCHAR(10)  NOT NULL,
    SoDienThoai       VARCHAR(15)  NOT NULL,
    Email             VARCHAR(100) NULL,
    DiaChiThuongTru   VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. HopDong ---------------------------------------------------------------
CREATE TABLE HopDong (
    MaHopDong        INT AUTO_INCREMENT PRIMARY KEY,
    MaCanHo          INT NOT NULL,
    MaKhach          INT NOT NULL,
    MaNV             INT NULL,                 -- nhân viên phụ trách hợp đồng
    NgayBatDau       DATE NOT NULL,
    NgayKetThuc      DATE NOT NULL,
    GiaThueThoaThuan DECIMAL(18,2) NOT NULL,
    TienCoc          DECIMAL(18,2) NOT NULL,
    TrangThai        VARCHAR(30) NOT NULL DEFAULT 'Đang hiệu lực',
    GhiChu           VARCHAR(255) NULL,
    CHECK (TrangThai IN ('Đang hiệu lực','Đã thanh lý','Hết hạn')),
    CHECK (NgayKetThuc > NgayBatDau),
    FOREIGN KEY (MaCanHo) REFERENCES CanHo(MaCanHo),
    FOREIGN KEY (MaKhach) REFERENCES KhachThue(MaKhach),
    FOREIGN KEY (MaNV) REFERENCES NhanVien(MaNV)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. DichVu ------------------------------------------------------------------
CREATE TABLE DichVu (
    MaDichVu   INT AUTO_INCREMENT PRIMARY KEY,
    TenDichVu  VARCHAR(100) NOT NULL,
    DonGia     DECIMAL(18,2) NOT NULL,
    DonVi      VARCHAR(20) NOT NULL,
    MoTa       VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. HoaDon --------------------------------------------------------------------
CREATE TABLE HoaDon (
    MaHoaDon       INT AUTO_INCREMENT PRIMARY KEY,
    MaHopDong      INT NOT NULL,
    NgayTao        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KyThanhToan    VARCHAR(7) NOT NULL,     -- định dạng MM/YYYY
    NgayThanhToan  DATETIME NULL,
    TienThue       DECIMAL(18,2) NOT NULL DEFAULT 0,
    TienDien       DECIMAL(18,2) NOT NULL DEFAULT 0,
    TienNuoc       DECIMAL(18,2) NOT NULL DEFAULT 0,
    TienDichVu     DECIMAL(18,2) NOT NULL DEFAULT 0,
    TongTien       DECIMAL(18,2) NOT NULL DEFAULT 0,
    TrangThai      VARCHAR(20) NOT NULL DEFAULT 'Chưa TT',
    CHECK (TrangThai IN ('Chưa TT','Đã TT','Quá hạn')),
    FOREIGN KEY (MaHopDong) REFERENCES HopDong(MaHopDong)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. HoaDon_DichVu (bảng trung gian N-N) --------------------------------------
CREATE TABLE HoaDon_DichVu (
    MaHoaDon   INT NOT NULL,
    MaDichVu   INT NOT NULL,
    SoLuong    INT NOT NULL,
    ThanhTien  DECIMAL(18,2) NOT NULL,
    PRIMARY KEY (MaHoaDon, MaDichVu),
    FOREIGN KEY (MaHoaDon) REFERENCES HoaDon(MaHoaDon),
    FOREIGN KEY (MaDichVu) REFERENCES DichVu(MaDichVu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. LichSuThanhToan -------------------------------------------------------------
CREATE TABLE LichSuThanhToan (
    MaThanhToan    INT AUTO_INCREMENT PRIMARY KEY,
    MaHoaDon       INT NOT NULL,
    NgayThanhToan  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    SoTien         DECIMAL(18,2) NOT NULL,
    HinhThuc       VARCHAR(20) NOT NULL,   -- 'Tiền mặt' / 'Chuyển khoản'
    FOREIGN KEY (MaHoaDon) REFERENCES HoaDon(MaHoaDon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. YeuCauBaoTri -------------------------------------------------------------
CREATE TABLE YeuCauBaoTri (
    MaBaoTri       INT AUTO_INCREMENT PRIMARY KEY,
    MaCanHo        INT NOT NULL,
    MaKhach        INT NOT NULL,
    NoiDung        VARCHAR(255) NOT NULL,
    NgayTiepNhan   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    NgayHoanThanh  DATETIME NULL,
    TrangThai      VARCHAR(30) NOT NULL DEFAULT 'Đã tiếp nhận',
    ChiPhi         DECIMAL(18,2) NULL,
    GhiChu         VARCHAR(255) NULL,
    FOREIGN KEY (MaCanHo) REFERENCES CanHo(MaCanHo),
    FOREIGN KEY (MaKhach) REFERENCES KhachThue(MaKhach)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. LichSuThayDoi (Audit Log - giữ lại để bám sát đề gốc, không bắt buộc dùng) --
CREATE TABLE LichSuThayDoi (
    MaLog      INT AUTO_INCREMENT PRIMARY KEY,
    TenBang    VARCHAR(50) NOT NULL,
    HanhDong   VARCHAR(20) NOT NULL,     -- INSERT / UPDATE / DELETE
    GiaTriCu   TEXT NULL,
    GiaTriMoi  TEXT NULL,
    ThoiGian   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================================
-- PHẦN 2: DỮ LIỆU MẪU (INSERT)
-- =====================================================================

-- LoaiCanHo
INSERT INTO LoaiCanHo (TenLoai, GiaThueChuan, MoTa) VALUES
('Studio', 4500000, 'Căn hộ studio nhỏ gọn, phù hợp 1 người'),
('1 Phòng ngủ', 6500000, 'Căn hộ 1 phòng ngủ riêng biệt'),
('2 Phòng ngủ', 9500000, 'Căn hộ 2 phòng ngủ, phù hợp gia đình nhỏ'),
('Penthouse', 18000000, 'Căn hộ cao cấp tầng thượng, view đẹp');

-- NhanVien (mật khẩu mẫu, thực tế phải password_hash() khi tạo qua PHP)
-- mật khẩu mẫu dưới đây là hash của "123456" (bcrypt) - CHỈ DÙNG ĐỂ TEST
INSERT INTO NhanVien (HoTen, TenDangNhap, MatKhau, VaiTro, SoDienThoai, Email) VALUES
('Nguyễn Văn Chủ', 'admin', '$2y$10$Fq0X6nQY8qz1o0m8h2b1UOe8h6Z1x0kK3v8Xn6yYcQe9M9J1x9F1a', 'Admin', '0901111111', 'admin@candichvu.com'),
('Trần Thị Nhân', 'nhanvien1', '$2y$10$Fq0X6nQY8qz1o0m8h2b1UOe8h6Z1x0kK3v8Xn6yYcQe9M9J1x9F1a', 'NhanVien', '0902222222', 'nv1@candichvu.com'),
('Lê Văn Sự', 'nhanvien2', '$2y$10$Fq0X6nQY8qz1o0m8h2b1UOe8h6Z1x0kK3v8Xn6yYcQe9M9J1x9F1a', 'NhanVien', '0903333333', 'nv2@candichvu.com');

-- CanHo (12 căn, đa dạng trạng thái để demo)
INSERT INTO CanHo (SoPhong, MaLoai, DienTich, GiaThue, TrangThai, MoTa) VALUES
('101', 1, 25, 4500000, 'Đang thuê', 'Studio tầng 1, gần thang máy'),
('102', 1, 25, 4500000, 'Trống', 'Studio tầng 1, view sân trong'),
('201', 2, 40, 6500000, 'Đang thuê', '1PN tầng 2, ban công rộng'),
('202', 2, 40, 6800000, 'Đang thuê', '1PN tầng 2, nội thất mới'),
('203', 2, 40, 6500000, 'Bảo trì', '1PN tầng 2, đang sửa điều hòa'),
('301', 3, 65, 9500000, 'Đang thuê', '2PN tầng 3, đầy đủ nội thất'),
('302', 3, 65, 9800000, 'Trống', '2PN tầng 3, view thành phố'),
('401', 3, 68, 10000000, 'Đang thuê', '2PN tầng 4, góc thoáng'),
('402', 1, 22, 4300000, 'Trống', 'Studio tầng 4, tiết kiệm'),
('501', 4, 100, 18000000, 'Đang thuê', 'Penthouse tầng thượng, hồ bơi riêng'),
('203B', 2, 42, 6700000, 'Trống', '1PN tầng 2, mới bàn giao'),
('305', 3, 66, 9600000, 'Đang thuê', '2PN tầng 3, gần thang bộ');

-- KhachThue (8 khách)
INSERT INTO KhachThue (HoTen, CCCD, NgaySinh, GioiTinh, SoDienThoai, Email, DiaChiThuongTru) VALUES
('Phạm Thị Lan', '079201001234', '1995-03-12', 'Nữ', '0911111111', 'lan.pham@gmail.com', 'Quận 1, TP.HCM'),
('Hoàng Văn Nam', '079201005678', '1990-07-25', 'Nam', '0922222222', 'nam.hoang@gmail.com', 'Quận 3, TP.HCM'),
('Đỗ Thị Hương', '079202001111', '1998-11-02', 'Nữ', '0933333333', 'huong.do@gmail.com', 'Quận Bình Thạnh, TP.HCM'),
('Vũ Minh Tuấn', '079198002222', '1988-05-18', 'Nam', '0944444444', 'tuan.vu@gmail.com', 'Quận Gò Vấp, TP.HCM'),
('Ngô Thị Mai', '079200003333', '1993-09-09', 'Nữ', '0955555555', 'mai.ngo@gmail.com', 'Quận 7, TP.HCM'),
('Bùi Quốc Anh', '079195004444', '1985-01-30', 'Nam', '0966666666', 'anh.bui@gmail.com', 'Quận Phú Nhuận, TP.HCM'),
('Trịnh Thị Kim', '079203005555', '2000-12-15', 'Nữ', '0977777777', 'kim.trinh@gmail.com', 'Quận Tân Bình, TP.HCM'),
('Lý Văn Hải', '079192006666', '1992-04-22', 'Nam', '0988888888', 'hai.ly@gmail.com', 'Quận 2, TP.HCM');

-- DichVu
INSERT INTO DichVu (TenDichVu, DonGia, DonVi, MoTa) VALUES
('Điện', 3500, 'kWh', 'Tiền điện tính theo chỉ số công tơ'),
('Nước', 15000, 'm3', 'Tiền nước tính theo đồng hồ'),
('Gửi xe máy', 150000, 'tháng', 'Phí giữ xe máy hàng tháng'),
('Gửi ô tô', 1200000, 'tháng', 'Phí giữ ô tô hàng tháng'),
('Vệ sinh chung', 100000, 'tháng', 'Phí dọn dẹp khu vực chung'),
('Internet', 200000, 'tháng', 'Phí internet cáp quang');

-- HopDong (7 hợp đồng: đa số đang hiệu lực, 1 đã thanh lý để demo lịch sử)
INSERT INTO HopDong (MaCanHo, MaKhach, MaNV, NgayBatDau, NgayKetThuc, GiaThueThoaThuan, TienCoc, TrangThai, GhiChu) VALUES
(1, 1, 2, '2026-01-01', '2026-12-31', 4500000, 9000000, 'Đang hiệu lực', 'Khách thuê lâu dài'),
(3, 2, 2, '2026-02-01', '2027-01-31', 6500000, 13000000, 'Đang hiệu lực', NULL),
(4, 3, 3, '2026-03-01', '2026-08-31', 6800000, 13600000, 'Đang hiệu lực', 'Hợp đồng 6 tháng'),
(6, 4, 2, '2025-09-01', '2026-08-31', 9500000, 19000000, 'Đang hiệu lực', NULL),
(8, 5, 3, '2026-04-01', '2027-03-31', 10000000, 20000000, 'Đang hiệu lực', NULL),
(10, 6, 2, '2025-12-01', '2026-11-30', 18000000, 36000000, 'Đang hiệu lực', 'Khách VIP'),
(12, 7, 3, '2025-06-01', '2025-12-31', 9600000, 19200000, 'Đã thanh lý', 'Đã trả phòng đúng hạn');

-- HoaDon (nhiều kỳ, đa dạng trạng thái thanh toán để demo View/Chart)
INSERT INTO HoaDon (MaHopDong, NgayTao, KyThanhToan, NgayThanhToan, TienThue, TienDien, TienNuoc, TienDichVu, TongTien, TrangThai) VALUES
(1, '2026-06-01 08:00:00', '06/2026', '2026-06-05 10:00:00', 4500000, 350000, 150000, 150000, 5150000, 'Đã TT'),
(1, '2026-07-01 08:00:00', '07/2026', NULL, 4500000, 420000, 180000, 150000, 5250000, 'Chưa TT'),
(2, '2026-06-01 08:00:00', '06/2026', '2026-06-03 09:00:00', 6500000, 500000, 200000, 350000, 7550000, 'Đã TT'),
(2, '2026-07-01 08:00:00', '07/2026', NULL, 6500000, 480000, 210000, 350000, 7540000, 'Quá hạn'),
(3, '2026-07-01 08:00:00', '07/2026', NULL, 6800000, 390000, 160000, 150000, 7500000, 'Chưa TT'),
(4, '2026-06-01 08:00:00', '06/2026', '2026-06-04 14:00:00', 9500000, 600000, 250000, 250000, 10600000, 'Đã TT'),
(4, '2026-07-01 08:00:00', '07/2026', NULL, 9500000, 650000, 260000, 250000, 10660000, 'Chưa TT'),
(5, '2026-07-01 08:00:00', '07/2026', NULL, 10000000, 700000, 300000, 200000, 11200000, 'Chưa TT'),
(6, '2026-06-01 08:00:00', '06/2026', '2026-06-02 09:30:00', 18000000, 900000, 400000, 1350000, 20650000, 'Đã TT'),
(6, '2026-07-01 08:00:00', '07/2026', NULL, 18000000, 950000, 420000, 1350000, 20720000, 'Chưa TT');

-- HoaDon_DichVu (gắn dịch vụ vào 1 vài hóa đơn tiêu biểu)
INSERT INTO HoaDon_DichVu (MaHoaDon, MaDichVu, SoLuong, ThanhTien) VALUES
(1, 3, 1, 150000),
(3, 3, 1, 150000),
(3, 6, 1, 200000),
(9, 4, 1, 1200000),
(9, 5, 1, 150000);

-- LichSuThanhToan
INSERT INTO LichSuThanhToan (MaHoaDon, NgayThanhToan, SoTien, HinhThuc) VALUES
(1, '2026-06-05 10:00:00', 5150000, 'Chuyển khoản'),
(3, '2026-06-03 09:00:00', 7550000, 'Tiền mặt'),
(6, '2026-06-04 14:00:00', 10600000, 'Chuyển khoản'),
(9, '2026-06-02 09:30:00', 20650000, 'Chuyển khoản'),
(4, '2026-07-10 11:00:00', 3000000, 'Tiền mặt'); -- thanh toán 1 phần cho hóa đơn quá hạn

-- YeuCauBaoTri
INSERT INTO YeuCauBaoTri (MaCanHo, MaKhach, NoiDung, NgayTiepNhan, NgayHoanThanh, TrangThai, ChiPhi, GhiChu) VALUES
(1, 1, 'Điều hòa không lạnh', '2026-07-15 09:00:00', '2026-07-16 15:00:00', 'Hoàn thành', 350000, 'Đã nạp gas'),
(3, 2, 'Vòi nước bị rò rỉ', '2026-07-18 10:30:00', NULL, 'Đang xử lý', NULL, NULL),
(6, 4, 'Bóng đèn hành lang cháy', '2026-07-20 08:00:00', '2026-07-20 17:00:00', 'Hoàn thành', 50000, NULL),
(10, 6, 'Cửa ban công bị kẹt', '2026-07-22 14:00:00', NULL, 'Đã tiếp nhận', NULL, 'Khách VIP - ưu tiên xử lý');


-- =====================================================================
-- PHẦN 3: VIEW
-- =====================================================================

-- 4.4.1. View_DanhSachCanHo
CREATE OR REPLACE VIEW View_DanhSachCanHo AS
SELECT
    ch.MaCanHo,
    ch.SoPhong,
    lch.TenLoai,
    ch.DienTich,
    ch.GiaThue,
    ch.TrangThai
FROM CanHo ch
JOIN LoaiCanHo lch ON ch.MaLoai = lch.MaLoai;

-- 4.4.2. View_HoaDonChuaThanhToan
CREATE OR REPLACE VIEW View_HoaDonChuaThanhToan AS
SELECT
    hd.MaHoaDon,
    hd.KyThanhToan,
    ch.SoPhong,
    kt.HoTen        AS TenKhachThue,
    kt.SoDienThoai,
    hd.TienThue,
    hd.TienDien,
    hd.TienNuoc,
    hd.TienDichVu,
    hd.TongTien,
    hd.TrangThai,
    hd.NgayTao,
    DATEDIFF(NOW(), hd.NgayTao) AS SoNgayKeTuNgayTao
FROM HoaDon hd
JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
JOIN CanHo ch   ON hp.MaCanHo   = ch.MaCanHo
JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
WHERE hd.TrangThai <> 'Đã TT'
ORDER BY hd.TrangThai DESC, SoNgayKeTuNgayTao DESC;

-- 4.4.3. View_DoanhThuTheoThang
CREATE OR REPLACE VIEW View_DoanhThuTheoThang AS
SELECT
    KyThanhToan,
    COUNT(*)            AS SoHoaDon,
    SUM(TienThue)        AS TongTienThue,
    SUM(TienDichVu)      AS TongTienDichVu,
    SUM(TongTien)        AS TongDoanhThu
FROM HoaDon
WHERE TrangThai = 'Đã TT'
GROUP BY KyThanhToan
ORDER BY STR_TO_DATE(CONCAT('01/', KyThanhToan), '%d/%m/%Y') ASC;

-- 4.4.4. View_LichSuThueCanHo
CREATE OR REPLACE VIEW View_LichSuThueCanHo AS
SELECT
    ch.MaCanHo,
    ch.SoPhong,
    lch.TenLoai,
    kt.HoTen       AS TenKhachThue,
    kt.SoDienThoai,
    hp.MaHopDong,
    hp.NgayBatDau,
    hp.NgayKetThuc,
    hp.GiaThueThoaThuan AS GiaThueThoa,
    hp.TrangThai   AS TrangThaiHopDong
FROM CanHo ch
JOIN LoaiCanHo lch ON ch.MaLoai = lch.MaLoai
JOIN HopDong hp    ON ch.MaCanHo = hp.MaCanHo
JOIN KhachThue kt  ON hp.MaKhach = kt.MaKhach;


-- =====================================================================
-- PHẦN 4: STORED PROCEDURE
-- =====================================================================

DELIMITER $$

-- 4.5.1. SP_TaoHoaDonHangThang
-- Tự động tạo hóa đơn cho tất cả hợp đồng đang hiệu lực trong kỳ chỉ định,
-- tránh tạo trùng nhờ NOT EXISTS.
CREATE PROCEDURE SP_TaoHoaDonHangThang (IN p_KyThanhToan VARCHAR(7))
BEGIN
    DECLARE v_KyThanhToan DATE;

    IF p_KyThanhToan REGEXP '^[0-9]{4}-[0-9]{2}$' THEN
        SET v_KyThanhToan = STR_TO_DATE(CONCAT(p_KyThanhToan, '-01'), '%Y-%m-%d');
    ELSE
        SET v_KyThanhToan = STR_TO_DATE(CONCAT('01/', p_KyThanhToan), '%d/%m/%Y');
    END IF;

    INSERT INTO HoaDon (MaHopDong, NgayTao, KyThanhToan, TienThue, TienDien, TienNuoc, TienDichVu, TongTien, TrangThai)
    SELECT
        hp.MaHopDong,
        NOW(),
        DATE_FORMAT(v_KyThanhToan, '%Y-%m'),
        hp.GiaThueThoaThuan,
        0, 0, 0,
        hp.GiaThueThoaThuan,
        'Chưa TT'
    FROM HopDong hp
    WHERE hp.NgayBatDau <= LAST_DAY(v_KyThanhToan)
      AND hp.NgayKetThuc >= v_KyThanhToan
      AND NOT EXISTS (
          SELECT 1 FROM HoaDon hd
          WHERE hd.MaHopDong = hp.MaHopDong
            AND hd.KyThanhToan = DATE_FORMAT(v_KyThanhToan, '%Y-%m')
      );
END$$

-- 4.5.2. SP_ThanhToanHoaDon
-- Ghi nhận 1 lượt thanh toán (hỗ trợ thanh toán từng phần), cộng dồn và
-- tự động chuyển trạng thái hóa đơn khi đủ tiền.
CREATE PROCEDURE SP_ThanhToanHoaDon (
    IN p_MaHoaDon INT,
    IN p_SoTien DECIMAL(18,2),
    IN p_HinhThuc VARCHAR(20)
)
BEGIN
    DECLARE v_TongDaTra DECIMAL(18,2);
    DECLARE v_TongTien DECIMAL(18,2);

    -- Bước 1: ghi vết dòng tiền
    INSERT INTO LichSuThanhToan (MaHoaDon, NgayThanhToan, SoTien, HinhThuc)
    VALUES (p_MaHoaDon, NOW(), p_SoTien, p_HinhThuc);

    -- Bước 2: tính tổng lũy kế và cập nhật trạng thái
    SELECT SUM(SoTien) INTO v_TongDaTra
    FROM LichSuThanhToan
    WHERE MaHoaDon = p_MaHoaDon;

    SELECT TongTien INTO v_TongTien
    FROM HoaDon
    WHERE MaHoaDon = p_MaHoaDon;

    IF v_TongDaTra >= v_TongTien THEN
        UPDATE HoaDon
        SET TrangThai = 'Đã TT', NgayThanhToan = NOW()
        WHERE MaHoaDon = p_MaHoaDon;
    ELSE
        UPDATE HoaDon
        SET TrangThai = 'Chưa TT', NgayThanhToan = NULL
        WHERE MaHoaDon = p_MaHoaDon;
    END IF;
END$$

-- 4.5.3. SP_ThanhLyHopDong
-- Thanh lý hợp đồng: chặn nếu còn nợ, cập nhật trạng thái HĐ, trả căn hộ về Trống.
CREATE PROCEDURE SP_ThanhLyHopDong (IN p_MaHopDong INT)
BEGIN
    DECLARE v_ConNo INT;
    DECLARE v_MaCanHo INT;

    SELECT COUNT(*) INTO v_ConNo
    FROM HoaDon
    WHERE MaHopDong = p_MaHopDong
      AND TrangThai IN ('Chưa TT', 'Quá hạn');

    IF v_ConNo > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Còn hóa đơn chưa thanh toán hoặc quá hạn, không thể thanh lý!';
    ELSE
        UPDATE HopDong
        SET TrangThai = 'Đã thanh lý'
        WHERE MaHopDong = p_MaHopDong;

        SELECT MaCanHo INTO v_MaCanHo
        FROM HopDong
        WHERE MaHopDong = p_MaHopDong;

        UPDATE CanHo
        SET TrangThai = 'Trống'
        WHERE MaCanHo = v_MaCanHo;
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- CÁCH TEST NHANH (chạy thử sau khi import xong)
-- =====================================================================
-- SELECT * FROM View_DanhSachCanHo;
-- SELECT * FROM View_HoaDonChuaThanhToan;
-- SELECT * FROM View_DoanhThuTheoThang;
-- SELECT * FROM View_LichSuThueCanHo;
-- CALL SP_TaoHoaDonHangThang('08/2026');
-- CALL SP_ThanhToanHoaDon(2, 5250000, 'Chuyển khoản');
-- CALL SP_ThanhLyHopDong(4);  -- sẽ báo lỗi vì hợp đồng 4 còn hóa đơn chưa TT