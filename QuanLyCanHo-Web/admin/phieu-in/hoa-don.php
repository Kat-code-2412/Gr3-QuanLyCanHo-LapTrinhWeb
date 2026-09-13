<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Mã hóa đơn không hợp lệ.');
}

// Truy vấn chi tiết hóa đơn kèm thông tin hợp đồng, phòng, khách thuê và chỉ số điện nước
$stmt = $pdo->prepare("
    SELECT 
        hd.*,
        h.MaCanHo, h.GiaDien, h.GiaNuoc, h.GiaThueThoaThuan,
        c.SoPhong, c.DiaChi,
        kt.HoTen AS TenKhachThue, kt.SoDienThoai AS SdtKhachThue, kt.CCCD,
        nv.HoTen AS TenNhanVien,
        cs.ChiSoDienCu, cs.ChiSoDienMoi, cs.ChiSoNuocCu, cs.ChiSoNuocMoi
    FROM HoaDon hd
    JOIN HopDong h ON hd.MaHopDong = h.MaHopDong
    JOIN CanHo c ON h.MaCanHo = c.MaCanHo
    JOIN KhachThue kt ON h.MaKhach = kt.MaKhach
    LEFT JOIN NhanVien nv ON h.MaNV = nv.MaNV
    LEFT JOIN chisodiennuoc cs ON cs.MaCanHo = c.MaCanHo 
         AND (cs.ThangNam = hd.KyThanhToan 
              OR cs.ThangNam = CONCAT(SUBSTRING_INDEX(hd.KyThanhToan, '/', -1), '-', SUBSTRING_INDEX(hd.KyThanhToan, '/', 1))
              OR cs.ThangNam = DATE_FORMAT(hd.NgayTao, '%Y-%m'))
    WHERE hd.MaHoaDon = ?
    ORDER BY cs.MaChiSo DESC LIMIT 1
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    exit('Không tìm thấy thông tin hóa đơn.');
}

$dienCu = (int)($invoice['ChiSoDienCu'] ?? 0);
$dienMoi = (int)($invoice['ChiSoDienMoi'] ?? 0);
$dienTT = max(0, $dienMoi - $dienCu);

$nuocCu = (int)($invoice['ChiSoNuocCu'] ?? 0);
$nuocMoi = (int)($invoice['ChiSoNuocMoi'] ?? 0);
$nuocTT = max(0, $nuocMoi - $nuocCu);

$donGiaDien = normalizeServiceFee($invoice['GiaDien'] ?? 3800, 'dien');
$donGiaNuoc = normalizeServiceFee($invoice['GiaNuoc'] ?? 100000, 'nuoc');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu In Hóa Đơn #<?= (int)$invoice['MaHoaDon'] ?> - Phòng <?= e($invoice['SoPhong']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Inter', system-ui, sans-serif;
        }
        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px;
            color: #0f172a;
        }
        .invoice-box {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .brand-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: 800;
            color: #2563eb;
            text-align: right;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .info-card {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
        }
        .info-card h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
        }
        .info-card p {
            margin: 3px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
        }
        .total-box {
            text-align: right;
            font-size: 18px;
            font-weight: 700;
            color: #1d4ed8;
            padding: 15px 0;
            border-top: 2px solid #1e293b;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            text-align: center;
            margin-top: 40px;
        }
        .sig-title {
            font-weight: 600;
            margin-bottom: 60px;
        }
        .btn-print {
            background-color: #2563eb;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        @media print {
            body { background: none; padding: 0; }
            .invoice-box { box-shadow: none; padding: 0; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div style="max-width: 800px; margin: 0 auto; text-align: right;">
    <button onclick="window.print()" class="btn-print">In Hóa Đơn</button>
</div>

<div class="invoice-box">
    <div class="invoice-header">
        <div>
            <div class="brand-title">HỆ THỐNG QUẢN LÝ CĂN HỘ DỊCH VỤ</div>
            <p style="margin: 5px 0 0 0; color: #64748b; font-size: 13px;"><?= e($invoice['DiaChi']) ?></p>
        </div>
        <div>
            <div class="invoice-title">HÓA ĐƠN THANH TOÁN</div>
            <p style="margin: 5px 0 0 0; text-align: right; font-size: 13px; color: #475569;">
                Mã HĐ: <strong>#<?= (int)$invoice['MaHoaDon'] ?></strong> | Kỳ: <strong><?= e($invoice['KyThanhToan']) ?></strong>
            </p>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <h4>THÔNG TIN KHÁCH THUÊ</h4>
            <p><strong>Họ tên:</strong> <?= e($invoice['TenKhachThue']) ?></p>
            <p><strong>Số điện thoại:</strong> <?= e($invoice['SdtKhachThue']) ?></p>
            <p><strong>Phòng thuê:</strong> Phòng <?= e($invoice['SoPhong']) ?></p>
        </div>
        <div class="info-card">
            <h4>THÔNG TIN HÓA ĐƠN</h4>
            <p><strong>Ngày tạo:</strong> <?= formatDate($invoice['NgayTao']) ?></p>
            <p><strong>Ngày thanh toán:</strong> <?= $invoice['NgayThanhToan'] ? formatDate($invoice['NgayThanhToan']) : 'Chưa thanh toán' ?></p>
            <p><strong>Trạng thái:</strong> 
                <span style="font-weight: 700; color: <?= ($invoice['TrangThaiThanhToan'] === 'Đã thanh toán') ? '#166534' : '#991b1b' ?>;">
                    <?= e($invoice['TrangThaiThanhToan']) ?>
                </span>
            </p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Khoản Mục / Dịch Vụ</th>
                <th>Chỉ số cũ - mới</th>
                <th>Số lượng / Tiêu thụ</th>
                <th>Đơn giá</th>
                <th style="text-align: right;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Tiền phòng</strong></td>
                <td>-</td>
                <td>1 tháng</td>
                <td><?= formatMoney($invoice['TienThue']) ?></td>
                <td style="text-align: right;"><strong><?= formatMoney($invoice['TienThue']) ?></strong></td>
            </tr>
            <tr>
                <td><strong>Tiền điện</strong></td>
                <td><?= $dienCu > 0 ? "{$dienCu} &rarr; {$dienMoi}" : '-' ?></td>
                <td><?= $dienTT ?> kWh</td>
                <td><?= formatMoney($donGiaDien) ?></td>
                <td style="text-align: right;"><strong><?= formatMoney($invoice['TienDien']) ?></strong></td>
            </tr>
            <tr>
                <td><strong>Tiền nước</strong></td>
                <td>Định mức khoán</td>
                <td>1 tháng</td>
                <td><?= formatMoney($invoice['TienNuoc']) ?></td>
                <td style="text-align: right;"><strong><?= formatMoney($invoice['TienNuoc']) ?></strong></td>
            </tr>
            <?php if ((float)$invoice['TienDichVu'] > 0): ?>
                <tr>
                    <td><strong>Phí dịch vụ chung (Xe, Internet, Vệ sinh)</strong></td>
                    <td>-</td>
                    <td>1 tháng</td>
                    <td><?= formatMoney($invoice['TienDichVu']) ?></td>
                    <td style="text-align: right;"><strong><?= formatMoney($invoice['TienDichVu']) ?></strong></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total-box">
        TỔNG CỘNG THANH TOÁN: <?= formatMoney($invoice['TongTien']) ?>
    </div>

    <div class="signatures">
        <div>
            <div class="sig-title">NGƯỜI LẬP HÓA ĐƠN</div>
            <p style="color: #64748b; font-size: 13px;">(Ký và ghi rõ họ tên)</p>
        </div>
        <div>
            <div class="sig-title">KHÁCH THUÊ CĂN HỘ</div>
            <p style="color: #64748b; font-size: 13px;">(Ký và ghi rõ họ tên)</p>
        </div>
    </div>
</div>

</body>
</html>
