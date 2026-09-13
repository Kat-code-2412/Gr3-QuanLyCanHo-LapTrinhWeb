<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requirePermission('BAOCAO_VIEW');

$pdo = require __DIR__ . '/../../config/database.php';
$type = trim((string)($_GET['type'] ?? 'doanh_thu'));
$ky = trim((string)($_GET['ky'] ?? ''));
$year = (int)($_GET['nam'] ?? date('Y'));

if ($type === 'doanh_thu') {
    // 1. XUẤT BÁO CÁO DOANH THU THEO TỪNG THÁNG CỤ THỂ (NẾU CÓ CHỌN KỲ)
    if ($ky !== '') {
        $cleanKy = str_replace(['/', '\\'], '_', $ky);
        $filename = "Bao_Cao_Doanh_Thu_Thang_" . $cleanKy . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // Xuất UTF-8 BOM cho Microsoft Excel hiển thị đúng dấu tiếng Việt
        fwrite($output, "\xEF\xBB\xBF");

        // Tiêu đề đầu báo cáo
        fputcsv($output, ["BÁO CÁO DOANH THU THỰC THU - THÁNG " . $ky]);
        fputcsv($output, ["Thời điểm xuất báo cáo:", date('d/m/Y H:i:s')]);
        fputcsv($output, ["Tiêu chí ghi nhận:", "Chỉ tính các hóa đơn ĐÃ THANH TOÁN thực tế của kỳ " . $ky]);
        fputcsv($output, []); // Dòng trống

        // Tiêu đề các cột
        fputcsv($output, [
            'STT',
            'Mã Hóa Đơn',
            'Kỳ Thu',
            'Số Phòng',
            'Tòa Nhà / Địa Chỉ',
            'Họ Tên Khách Thuê',
            'Số Điện Thoại',
            'Tiền Thuê Phòng (VNĐ)',
            'Tiền Điện (VNĐ)',
            'Tiền Nước (VNĐ)',
            'Tiền Dịch Vụ (VNĐ)',
            'Tổng Thực Thu (VNĐ)',
            'Ngày Thanh Toán',
            'Trạng Thái'
        ]);

        $stmt = $pdo->prepare("
            SELECT 
                hd.MaHoaDon, hd.KyThanhToan, hd.NgayTao, hd.NgayThanhToan,
                ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach, kt.SoDienThoai,
                hd.TienThue, hd.TienDien, hd.TienNuoc, hd.TienDichVu, hd.TongTien,
                hd.TrangThaiThanhToan
            FROM HoaDon hd
            JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
            JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
            JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
            WHERE (hd.TrangThaiThanhToan = 'Đã thanh toán' OR hd.TrangThai = 'Đã TT')
              AND hd.KyThanhToan = ?
            ORDER BY hd.NgayThanhToan DESC, hd.MaHoaDon DESC
        ");
        $stmt->execute([$ky]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tongTienThue = 0.0;
        $tongTienDien = 0.0;
        $tongTienNuoc = 0.0;
        $tongTienDV   = 0.0;
        $tongDoanhThu = 0.0;

        $stt = 1;
        foreach ($rows as $r) {
            $tongTienThue += (float)$r['TienThue'];
            $tongTienDien += (float)$r['TienDien'];
            $tongTienNuoc += (float)$r['TienNuoc'];
            $tongTienDV   += (float)$r['TienDichVu'];
            $tongDoanhThu += (float)$r['TongTien'];

            $ngayTT = !empty($r['NgayThanhToan']) ? date('d/m/Y H:i', strtotime($r['NgayThanhToan'])) : '-';

            fputcsv($output, [
                $stt++,
                'HD-' . str_pad((string)$r['MaHoaDon'], 4, '0', STR_PAD_LEFT),
                $r['KyThanhToan'],
                $r['SoPhong'],
                $r['DiaChi'],
                $r['TenKhach'],
                $r['SoDienThoai'] ? "'" . $r['SoDienThoai'] : '',
                number_format((float)$r['TienThue'], 0, ',', '.'),
                number_format((float)$r['TienDien'], 0, ',', '.'),
                number_format((float)$r['TienNuoc'], 0, ',', '.'),
                number_format((float)$r['TienDichVu'], 0, ',', '.'),
                number_format((float)$r['TongTien'], 0, ',', '.'),
                $ngayTT,
                'Đã thanh toán'
            ]);
        }

        // Dòng tổng kết
        fputcsv($output, []);
        fputcsv($output, [
            'TỔNG CỘNG THỰC THU',
            '',
            '',
            '',
            '',
            count($rows) . ' Hóa đơn',
            '',
            number_format($tongTienThue, 0, ',', '.'),
            number_format($tongTienDien, 0, ',', '.'),
            number_format($tongTienNuoc, 0, ',', '.'),
            number_format($tongTienDV, 0, ',', '.'),
            number_format($tongDoanhThu, 0, ',', '.'),
            '',
            '100% Đã Thu'
        ]);

        fclose($output);
        exit;
    }

    // 2. XUẤT BÁO CÁO DOANH THU THEO CẢ NĂM (TỔNG HỢP TỪNG THÁNG)
    $filename = "Bao_Cao_Doanh_Thu_Tong_Hop_Nam_" . $year . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, ["BÁO CÁO TỔNG HỢP DOANH THU THỰC THU - NĂM " . $year]);
    fputcsv($output, ["Thời điểm xuất:", date('d/m/Y H:i:s')]);
    fputcsv($output, []);

    // Tiêu đề cột
    fputcsv($output, [
        'Kỳ Thanh Toán',
        'Năm',
        'Số Hóa Đơn Đã Thu',
        'Tiền Thuê Phòng (VNĐ)',
        'Tiền Điện (VNĐ)',
        'Tiền Nước (VNĐ)',
        'Tiền Dịch Vụ (VNĐ)',
        'Tổng Doanh Thu Thực Thu (VNĐ)'
    ]);

    $stmt = $pdo->prepare("
        SELECT 
            hd.KyThanhToan,
            COUNT(*) AS SoHoaDon,
            SUM(hd.TienThue) AS TongTienThue,
            SUM(hd.TienDien) AS TongTienDien,
            SUM(hd.TienNuoc) AS TongTienNuoc,
            SUM(hd.TienDichVu) AS TongTienDichVu,
            SUM(hd.TongTien) AS TongDoanhThu
        FROM HoaDon hd
        WHERE (hd.TrangThaiThanhToan = 'Đã thanh toán' OR hd.TrangThai = 'Đã TT')
          AND RIGHT(hd.KyThanhToan, 4) = ?
        GROUP BY hd.KyThanhToan
        ORDER BY STR_TO_DATE(CONCAT('01/', hd.KyThanhToan), '%d/%m/%Y') ASC
    ");
    $stmt->execute([(string)$year]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sumPhong = 0.0;
    $sumDien  = 0.0;
    $sumNuoc  = 0.0;
    $sumDV    = 0.0;
    $sumTong  = 0.0;
    $sumHD    = 0;

    foreach ($rows as $r) {
        $sumPhong += (float)$r['TongTienThue'];
        $sumDien  += (float)$r['TongTienDien'];
        $sumNuoc  += (float)$r['TongTienNuoc'];
        $sumDV    += (float)$r['TongTienDichVu'];
        $sumTong  += (float)$r['TongDoanhThu'];
        $sumHD    += (int)$r['SoHoaDon'];

        fputcsv($output, [
            'Tháng ' . $r['KyThanhToan'],
            $year,
            $r['SoHoaDon'],
            number_format((float)$r['TongTienThue'], 0, ',', '.'),
            number_format((float)$r['TongTienDien'], 0, ',', '.'),
            number_format((float)$r['TongTienNuoc'], 0, ',', '.'),
            number_format((float)$r['TongTienDichVu'], 0, ',', '.'),
            number_format((float)$r['TongDoanhThu'], 0, ',', '.'),
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, [
        'TỔNG CỘNG NĂM ' . $year,
        $year,
        $sumHD . ' Hóa đơn',
        number_format($sumPhong, 0, ',', '.'),
        number_format($sumDien, 0, ',', '.'),
        number_format($sumNuoc, 0, ',', '.'),
        number_format($sumDV, 0, ',', '.'),
        number_format($sumTong, 0, ',', '.')
    ]);

    fclose($output);
    exit;

} elseif ($type === 'cong_no') {
    $isSingleKy = ($ky !== '' && $ky !== 'all');
    $cleanKy = $isSingleKy ? str_replace(['/', '\\'], '_', $ky) : 'Tat_Ca_Cac_Ky';
    $filename = "Bao_Cao_Cong_No_" . $cleanKy . "_" . date('Ymd') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    // Tiêu đề đầu trang
    fputcsv($output, ["BÁO CÁO CÔNG NỢ CHI TIẾT " . ($isSingleKy ? "- THÁNG " . $ky : "- TẤT CẢ CÁC KỲ")]);
    fputcsv($output, ["Thời điểm xuất báo cáo:", date('d/m/Y H:i:s')]);
    fputcsv($output, ["Tiêu chí lọc:", $isSingleKy ? "Chỉ xuất công nợ kỳ " . $ky : "Toàn bộ hóa đơn chưa thanh toán trong hệ thống"]);
    fputcsv($output, []);

    // Tiêu đề các cột
    fputcsv($output, [
        'STT',
        'Mã Hóa Đơn',
        'Kỳ Thu',
        'Số Phòng',
        'Địa Chỉ Tòa Nhà',
        'Họ Tên Khách Thuê',
        'Số Điện Thoại',
        'Tiền Thuê (VNĐ)',
        'Tiền Điện (VNĐ)',
        'Tiền Nước (VNĐ)',
        'Tiền Dịch Vụ (VNĐ)',
        'Tổng Công Nợ (VNĐ)',
        'Ngày Lập HĐ',
        'Số Ngày Quá Hạn',
        'Trạng Thái'
    ]);

    $sql = "
        SELECT 
            hd.MaHoaDon, hd.KyThanhToan, hd.NgayTao,
            hd.TienThue, hd.TienDien, hd.TienNuoc, hd.TienDichVu, hd.TongTien,
            hd.TrangThaiThanhToan,
            DATEDIFF(CURRENT_DATE(), hd.NgayTao) AS SoNgayQuaHan,
            ch.SoPhong, ch.DiaChi,
            kt.HoTen AS TenKhach, kt.SoDienThoai
        FROM HoaDon hd
        JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        WHERE (hd.TrangThaiThanhToan <> 'Đã thanh toán' AND hd.TrangThai <> 'Đã TT')
    ";
    $params = [];
    if ($isSingleKy) {
        $sql .= " AND hd.KyThanhToan = ?";
        $params[] = $ky;
    }
    $sql .= " ORDER BY STR_TO_DATE(CONCAT('01/', hd.KyThanhToan), '%d/%m/%Y') DESC, hd.TongTien DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sumThue = 0.0;
    $sumDien = 0.0;
    $sumNuoc = 0.0;
    $sumDV   = 0.0;
    $sumTong = 0.0;
    $stt = 1;

    foreach ($rows as $r) {
        $sumThue += (float)$r['TienThue'];
        $sumDien += (float)$r['TienDien'];
        $sumNuoc += (float)$r['TienNuoc'];
        $sumDV   += (float)$r['TienDichVu'];
        $sumTong += (float)$r['TongTien'];

        fputcsv($output, [
            $stt++,
            'HD-' . str_pad((string)$r['MaHoaDon'], 4, '0', STR_PAD_LEFT),
            $r['KyThanhToan'],
            $r['SoPhong'],
            $r['DiaChi'],
            $r['TenKhach'],
            $r['SoDienThoai'] ? "'" . $r['SoDienThoai'] : '',
            number_format((float)$r['TienThue'], 0, ',', '.'),
            number_format((float)$r['TienDien'], 0, ',', '.'),
            number_format((float)$r['TienNuoc'], 0, ',', '.'),
            number_format((float)$r['TienDichVu'], 0, ',', '.'),
            number_format((float)$r['TongTien'], 0, ',', '.'),
            date('d/m/Y', strtotime($r['NgayTao'])),
            $r['SoNgayQuaHan'] . ' ngày',
            $r['TrangThaiThanhToan']
        ]);
    }

    // Dòng tổng cộng
    fputcsv($output, []);
    fputcsv($output, [
        'TỔNG CỘNG',
        '',
        '',
        count($rows) . ' hóa đơn',
        '',
        '',
        '',
        number_format($sumThue, 0, ',', '.'),
        number_format($sumDien, 0, ',', '.'),
        number_format($sumNuoc, 0, ',', '.'),
        number_format($sumDV, 0, ',', '.'),
        number_format($sumTong, 0, ',', '.'),
        '',
        '',
        ''
    ]);

    fclose($output);
    exit;

} elseif ($type === 'danh_sach_phong') {
    $filename = "Danh_Sach_Can_Ho_" . date('Y_m_d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, ['Mã Căn Hộ', 'Số Phòng', 'Địa Chỉ Tòa Nhà', 'Giá Thuê Niêm Yết', 'Trạng Thái', 'Mô Tả']);

    $rows = $pdo->query("SELECT * FROM CanHo ORDER BY DiaChi ASC, SoPhong ASC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['MaCanHo'],
            $r['SoPhong'],
            $r['DiaChi'],
            number_format((float)$r['GiaThue'], 0, ',', '.'),
            $r['TrangThai'],
            $r['MoTa'] ?? ''
        ]);
    }

    fclose($output);
    exit;
} else {
    exit('Loại báo cáo không hợp lệ.');
}
