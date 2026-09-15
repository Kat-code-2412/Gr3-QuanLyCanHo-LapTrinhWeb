<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/functions.php';

$isLoggedIn = !empty($_SESSION['MaNV']);
$role = $_SESSION['VaiTro'] ?? 'NhanVien';
$dashboardUrl = ($role === 'Admin') ? url('/admin/index.php') : url('/user/index.php');

// Lấy số liệu vận hành trực tiếp 100% từ Database
$totalRooms = 0;
$rentedRooms = 0;
$emptyRooms = 0;
$occupancyRate = 0.0;
$doanhThuThang = 0.0;
$maintPending = 0;
$debtCount = 0;
$featuredSuites = [];
$latestContract = null;
$latestDienNuoc = null;
$latestHoaDon = null;

try {
    $pdo = require __DIR__ . '/config/database.php';
    
    // Thống kê căn hộ thực tế
    $totalRooms = (int)$pdo->query("SELECT COUNT(*) FROM CanHo")->fetchColumn();
    $rentedRooms = (int)$pdo->query("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Đang thuê'")->fetchColumn();
    $emptyRooms = (int)$pdo->query("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Trống'")->fetchColumn();
    if ($totalRooms > 0) {
        $occupancyRate = round(($rentedRooms / $totalRooms) * 100, 1);
    } else {
        $occupancyRate = 0.0;
    }

    // Doanh thu tháng hiện tại thực tế (chỉ tính hóa đơn đã thanh toán)
    $curKyMM = date('m/Y');
    $curKyYM = date('Y-m');
    $stmtRev = $pdo->prepare("
        SELECT COALESCE(SUM(TongTien), 0) 
        FROM HoaDon 
        WHERE (TrangThaiThanhToan = 'Đã thanh toán' OR TrangThai = 'Đã TT') 
          AND (KyThanhToan = ? OR KyThanhToan = ?)
    ");
    $stmtRev->execute([$curKyMM, $curKyYM]);
    $doanhThuThang = (float)$stmtRev->fetchColumn();

    // Số sự cố bảo trì đang xử lý thực tế
    $maintPending = (int)$pdo->query("SELECT COUNT(*) FROM YeuCauBaoTri WHERE TrangThai IN ('Mới tiếp nhận', 'Đang xử lý')")->fetchColumn();

    // Công nợ tồn đọng thực tế
    $debtCount = (int)$pdo->query("SELECT COUNT(*) FROM HoaDon WHERE (TrangThaiThanhToan = 'Chưa thanh toán' OR TrangThai = 'Chưa TT')")->fetchColumn();

    // Hợp đồng mới nhất thực tế (nếu có)
    $stmtContract = $pdo->query("
        SELECT hp.*, ch.SoPhong, COALESCE(l.TenLoai, 'Căn hộ') AS TenLoai, kt.HoTen AS TenKhach
        FROM HopDong hp
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        LEFT JOIN LoaiCanHo l ON ch.MaLoai = l.MaLoai
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        ORDER BY hp.MaHopDong DESC
        LIMIT 1
    ");
    $latestContract = $stmtContract ? $stmtContract->fetch(PDO::FETCH_ASSOC) : null;

    // Chốt điện nước mới nhất thực tế (nếu có)
    $stmtDn = $pdo->query("
        SELECT dn.*, ch.SoPhong
        FROM ChiSoDienNuoc dn
        JOIN CanHo ch ON dn.MaCanHo = ch.MaCanHo
        ORDER BY dn.MaChiSo DESC
        LIMIT 1
    ");
    $latestDienNuoc = $stmtDn ? $stmtDn->fetch(PDO::FETCH_ASSOC) : null;

    // Hóa đơn mới nhất thực tế (nếu có)
    $stmtHd = $pdo->query("
        SELECT hd.*, ch.SoPhong, kt.HoTen AS TenKhach
        FROM HoaDon hd
        JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong
        JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
        JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
        ORDER BY hd.MaHoaDon DESC
        LIMIT 1
    ");
    $latestHoaDon = $stmtHd ? $stmtHd->fetch(PDO::FETCH_ASSOC) : null;

    // Lấy toàn bộ căn hộ thực tế trong cơ sở dữ liệu
    $suiteSql = "
        SELECT 
            c.MaCanHo, c.SoPhong, l.TenLoai, c.GiaThue, c.DienTich, c.TrangThai, c.DiaChi, c.MoTa,
            c.GiaDien, c.GiaNuoc, c.GiaXeMay, c.GiaOto, c.GiaInternet, c.GiaVeSinh,
            COALESCE(
                (SELECT DuongDan FROM CanHo_Anh WHERE MaCanHo = c.MaCanHo AND LaAnhDaiDien = 1 LIMIT 1),
                (SELECT DuongDan FROM CanHo_Anh WHERE MaCanHo = c.MaCanHo LIMIT 1),
                'uploads/can-ho/seed_penthouse_5b920eeeccf34de399d46c99971512f3_images.jpg'
            ) AS AnhDaiDien
        FROM CanHo c
        LEFT JOIN LoaiCanHo l ON c.MaLoai = l.MaLoai
        ORDER BY c.MaLoai ASC, c.GiaThue ASC, c.SoPhong ASC
    ";
    $featuredSuites = $pdo->query($suiteSql)->fetchAll(PDO::FETCH_ASSOC);

    // Lấy toàn bộ album ảnh nhóm theo căn hộ để phục vụ xem chi tiết
    $imgRows = $pdo->query("SELECT MaCanHo, DuongDan FROM CanHo_Anh ORDER BY LaAnhDaiDien DESC, ThuTu ASC, MaAnh ASC")->fetchAll(PDO::FETCH_ASSOC);
    $suiteImagesMap = [];
    foreach ($imgRows as $img) {
        $suiteImagesMap[$img['MaCanHo']][] = url('/' . ltrim($img['DuongDan'], '/'));
    }

    // Đếm số lượng thực tế theo từng loại căn
    $suiteCounts = ['Studio' => 0, 'Duplex' => 0, '1 Phòng Ngủ' => 0, '2 Phòng Ngủ' => 0];
    foreach ($featuredSuites as &$st) {
        $st['AllImages'] = $suiteImagesMap[$st['MaCanHo']] ?? [url('/' . ltrim($st['AnhDaiDien'], '/'))];
        $cat = $st['TenLoai'] ?? '';
        if (isset($suiteCounts[$cat])) {
            $suiteCounts[$cat]++;
        }
    }
    unset($st);

} catch (Throwable $t) {
    error_log('Lỗi index data: ' . $t->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Căn Hộ Dịch Vụ - Hệ Thống Quản Trị & Vận Hành Đẳng Cấp VIP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <style>
        :root {
            --vip-bg-dark: #040714;
            --vip-bg-surface: #090e1f;
            --vip-card: rgba(13, 20, 38, 0.75);
            --vip-card-hover: rgba(18, 30, 58, 0.9);
            --vip-border: rgba(255, 255, 255, 0.1);
            --vip-border-glow: rgba(56, 189, 248, 0.45);
            --vip-primary: #0284c7;
            --vip-cyan: #00f0ff;
            --vip-sky: #38bdf8;
            --vip-blue: #2563eb;
            --vip-indigo: #6366f1;
            --vip-emerald: #10b981;
            --vip-gold: #f59e0b;
            --vip-text-main: #f8fafc;
            --vip-text-sub: #94a3b8;
            --vip-text-muted: #64748b;
        }

        body {
            background-color: #060914;
            color: var(--vip-text-main);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            width: 100%;
            overflow-x: hidden;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-image: 
                radial-gradient(circle at 15% 12%, rgba(37, 99, 235, 0.09) 0%, transparent 45%),
                radial-gradient(circle at 85% 25%, rgba(14, 165, 233, 0.07) 0%, transparent 45%),
                radial-gradient(circle at 30% 65%, rgba(99, 102, 241, 0.06) 0%, transparent 50%),
                radial-gradient(circle at 80% 88%, rgba(226, 184, 85, 0.05) 0%, transparent 45%);
            background-attachment: fixed;
        }

        /* AMBIENT LIGHTS */
        .ambient-glow-circle {
            position: absolute;
            border-radius: 9999px;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.6;
        }

        /* ==========================================================================
           TOP STICKY NAVBAR
           ========================================================================== */
        .vip-nav {
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 3.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(4, 7, 20, 0.82);
            backdrop-filter: blur(24px) saturate(190%);
            -webkit-backdrop-filter: blur(24px) saturate(190%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.7), 0 1px 0 rgba(56, 189, 248, 0.15);
            transition: all 0.3s ease;
        }

        .vip-logo {
            display: flex;
            align-items: center;
            gap: 0.95rem;
            text-decoration: none;
            color: #ffffff;
            transition: transform 0.25s ease;
        }

        .vip-logo:hover {
            transform: translateY(-1px);
        }

        .vip-logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 50%, #4f46e5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 0 22px rgba(14, 165, 233, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .vip-logo-text h1 {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            margin: 0;
            color: #ffffff;
            background: linear-gradient(135deg, #ffffff 60%, #bae6fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .vip-logo-text span {
            font-size: 0.65rem;
            color: #38bdf8;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
            display: block;
        }

        .vip-nav-links {
            display: flex;
            align-items: center;
            gap: 2.75rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .vip-nav-links a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.925rem;
            font-weight: 600;
            transition: all 0.25s ease;
            position: relative;
            padding: 0.4rem 0;
        }

        .vip-nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #00f0ff, #38bdf8);
            border-radius: 2px;
            transition: width 0.25s ease;
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.8);
        }

        .vip-nav-links a:hover {
            color: #ffffff;
        }

        .vip-nav-links a:hover::after {
            width: 100%;
        }

        .vip-nav-actions {
            display: flex;
            align-items: center;
            gap: 0.95rem;
        }

        /* ==========================================================================
           1. HERO SLIDER (LUXURY QUIET ELEGANCE - ĐẲNG CẤP & TINH TẾ)
           ========================================================================== */
        .hero-slider-wrap {
            position: relative;
            width: 100%;
            height: calc(100vh - 78px);
            min-height: 680px;
            max-height: 860px;
            overflow: hidden;
            background: #030712;
        }

        .hero-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            transition: opacity 1s cubic-bezier(0.4, 0, 0.2, 1), visibility 1s;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding-bottom: 6.5rem;
            box-sizing: border-box;
            z-index: 1;
        }

        .hero-slide.active {
            opacity: 1;
            visibility: visible;
            z-index: 2;
        }

        .hero-slide-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center center;
            transform: scale(1.06);
            filter: brightness(0.9) contrast(1.08) saturate(1.15);
            transition: transform 9s cubic-bezier(0.25, 1, 0.5, 1);
            z-index: 0;
        }

        .hero-slide.active .hero-slide-bg {
            transform: scale(1);
        }

        .hero-slide-overlay {
            position: absolute;
            inset: 0;
            background: 
                linear-gradient(180deg, rgba(3, 7, 18, 0.42) 0%, rgba(3, 7, 18, 0.68) 55%, #030712 100%),
                radial-gradient(circle at 50% 38%, rgba(3, 7, 18, 0.1) 0%, rgba(3, 7, 18, 0.6) 100%);
            z-index: 1;
        }

        .hero-slide-container {
            max-width: 1320px;
            width: 100%;
            margin: 0 auto;
            padding: 0 3.5rem;
            position: relative;
            z-index: 2;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Staggered Cinematic Reveal Animation */
        .hero-slide .hero-title,
        .hero-slide .hero-desc,
        .hero-slide .hero-btn-group {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .hero-slide.active .hero-title {
            opacity: 1;
            transform: translateY(0);
            transition-delay: 0.15s;
        }
        .hero-slide.active .hero-desc {
            opacity: 1;
            transform: translateY(0);
            transition-delay: 0.28s;
        }
        .hero-slide.active .hero-btn-group {
            opacity: 1;
            transform: translateY(0);
            transition-delay: 0.42s;
        }

        /* Hero Typography */
        .hero-title {
            font-size: 3.15rem;
            font-weight: 800;
            line-height: 1.22;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin-bottom: 1.15rem;
            max-width: 960px;
            text-shadow: 0 4px 24px rgba(0, 0, 0, 0.9);
        }

        .hero-title .hero-gold-text {
            background: linear-gradient(135deg, #ffffff 0%, #fef08a 40%, #e2b855 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
            filter: drop-shadow(0 0 20px rgba(226, 184, 85, 0.35));
        }

        .hero-desc {
            font-size: 1.125rem;
            color: #cbd5e1;
            line-height: 1.7;
            max-width: 720px;
            margin-bottom: 2rem;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.8);
        }

        .hero-btn-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
        }

        /* Buttons */
        .btn-vip-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.95rem 2.25rem;
            background: linear-gradient(135deg, #0284c7 0%, #2563eb 60%, #1d4ed8 100%);
            color: #ffffff !important;
            font-size: 0.975rem;
            font-weight: 700;
            border-radius: 14px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.22);
            cursor: pointer;
            box-shadow: 0 10px 25px -4px rgba(37, 99, 235, 0.5), 0 0 20px rgba(2, 132, 199, 0.35);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-vip-primary:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 14px 30px -4px rgba(37, 99, 235, 0.65), 0 0 28px rgba(56, 189, 248, 0.45);
            background: linear-gradient(135deg, #0369a1 0%, #1d4ed8 60%, #1e40af 100%);
        }

        .btn-vip-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.95rem 2rem;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff !important;
            font-size: 0.975rem;
            font-weight: 700;
            border-radius: 14px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.18);
            cursor: pointer;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-vip-outline:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.4);
            color: #ffffff !important;
            transform: translateY(-2px);
        }

        /* ==========================================================================
           LUXURY METRIC STRIP (DẢI 4 THÔNG SỐ VÀNG Ở ĐÁY HERO - GỌN GÀNG & SANG TRỌNG)
           ========================================================================== */
        .hero-bottom-strip {
            position: absolute;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 2.25rem;
            padding: 0.75rem 2.25rem;
            background: rgba(5, 9, 22, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.6);
            z-index: 10;
        }

        .hero-metric-item {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .hero-metric-val {
            font-size: 1.65rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .hero-metric-lbl {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #94a3b8;
        }

        .hero-metric-divider {
            width: 1px;
            height: 32px;
            background: rgba(255, 255, 255, 0.12);
        }

        /* SLIDER CONTROLS (TINH TẾ & THANH LỊCH) */
        .hero-nav-arrow {
            position: absolute;
            top: 48%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(5, 9, 24, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: all 0.25s ease;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        }

        .hero-nav-arrow:hover {
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(226, 184, 85, 0.6);
            color: #fef08a;
            transform: translateY(-50%) scale(1.08);
            box-shadow: 0 0 22px rgba(226, 184, 85, 0.35);
        }

        .arrow-prev { left: 2.25rem; }
        .arrow-next { right: 2.25rem; }

        /* VẠCH CHỈ SỐ SLIDE (ELEGANT SLIDE INDICATORS) */
        .hero-dots-wrap {
            position: absolute;
            top: 2rem;
            right: 3rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            z-index: 10;
        }

        .hero-dot-line {
            width: 26px;
            height: 3px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.25);
            cursor: pointer;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hero-dot-line.active {
            width: 56px;
            background: linear-gradient(90deg, #fef08a, #e2b855);
            box-shadow: 0 0 14px rgba(226, 184, 85, 0.7);
        }

        /* ==========================================================================
           3. PHÂN HỆ QUẢN LÝ TOÀN DIỆN (FEATURES & MODULES SHOWCASE)
           ========================================================================== */
        .features-section {
            padding: 7rem 3.5rem 5rem;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .section-header-center {
            text-align: center;
            max-width: 820px;
            margin: 0 auto 4rem;
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.825rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #38bdf8;
            margin-bottom: 0.85rem;
            padding: 0.4rem 1rem;
            background: rgba(14, 165, 233, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: 9999px;
            backdrop-filter: blur(8px);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.025em;
            line-height: 1.25;
            margin: 0 0 1rem;
            background: linear-gradient(135deg, #ffffff 60%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-subtitle {
            font-size: 1.05rem;
            color: #94a3b8;
            line-height: 1.7;
            margin: 0;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .feature-card {
            background: rgba(13, 19, 36, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 2.25rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.5);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.4), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            background: rgba(18, 27, 52, 0.85);
            border-color: rgba(56, 189, 248, 0.35);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 25px rgba(56, 189, 248, 0.12);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.35rem;
        }

        .feature-icon-box {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .feature-card:hover .feature-icon-box {
            transform: scale(1.08);
        }

        .icon-blue {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.25) 0%, rgba(37, 99, 235, 0.3) 100%);
            border: 1px solid rgba(56, 189, 248, 0.4);
            color: #38bdf8;
        }

        .icon-amber {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.25) 0%, rgba(217, 119, 6, 0.3) 100%);
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: #fbbf24;
        }

        .icon-emerald {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.25) 0%, rgba(5, 150, 105, 0.3) 100%);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
        }

        .icon-purple {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.25) 0%, rgba(99, 102, 241, 0.3) 100%);
            border: 1px solid rgba(139, 92, 246, 0.4);
            color: #a78bfa;
        }

        .icon-rose {
            background: linear-gradient(135deg, rgba(244, 63, 94, 0.25) 0%, rgba(225, 29, 72, 0.3) 100%);
            border: 1px solid rgba(244, 63, 94, 0.4);
            color: #fb7185;
        }

        .icon-sky {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.25) 0%, rgba(14, 165, 233, 0.3) 100%);
            border: 1px solid rgba(6, 182, 212, 0.4);
            color: #22d3ee;
        }

        .feature-badge {
            font-size: 0.725rem;
            font-weight: 700;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 0.65rem;
            letter-spacing: -0.015em;
        }

        .feature-desc {
            font-size: 0.925rem;
            color: #94a3b8;
            line-height: 1.65;
            margin: 0 0 1.25rem;
            flex: 1;
        }

        .feature-points {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 1.15rem;
        }

        .feature-point-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.825rem;
            color: #cbd5e1;
        }

        .feature-point-item svg {
            color: #34d399;
            flex-shrink: 0;
        }


        /* ==========================================================================
           4. WORKFLOW SHOWCASE TABS (QUY TRÌNH VẬN HÀNH 4 BƯỚC)
           ========================================================================== */
        .workflow-section {
            padding: 7rem 3.5rem;
            background: linear-gradient(180deg, rgba(2, 5, 14, 0.92) 0%, rgba(8, 14, 28, 0.96) 50%, rgba(2, 5, 14, 0.92) 100%);
            border-top: 1px solid var(--vip-border);
            border-bottom: 1px solid var(--vip-border);
            position: relative;
        }

        .workflow-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.5), transparent);
        }

        .workflow-container {
            max-width: 1380px;
            margin: 0 auto;
        }

        .step-tabs-nav {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 0;
        }

        .step-tab-btn {
            background: rgba(13, 20, 38, 0.7);
            border: 1px solid var(--vip-border);
            border-radius: 20px;
            padding: 2rem 1.6rem;
            text-align: left;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            backdrop-filter: blur(16px);
        }

        .step-tab-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 20px;
            right: 20px;
            height: 3px;
            background: linear-gradient(90deg, #00f0ff, #38bdf8);
            border-radius: 0 0 4px 4px;
            opacity: 0.5;
            transition: all 0.3s ease;
        }

        .step-tab-btn:hover {
            background: rgba(20, 31, 56, 0.85);
            border-color: rgba(56, 189, 248, 0.45);
            transform: translateY(-4px);
            box-shadow: 0 14px 30px -8px rgba(14, 165, 233, 0.3);
        }

        .step-tab-btn:hover::before {
            opacity: 1;
            box-shadow: 0 0 14px rgba(0, 240, 255, 0.85);
        }

        .step-num {
            font-size: 0.8rem;
            font-weight: 800;
            color: #38bdf8;
            margin-bottom: 0.65rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .step-tab-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.45rem;
        }

        .step-tab-sub {
            font-size: 0.85rem;
            color: #94a3b8;
            line-height: 1.6;
            margin: 0;
        }

        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 1.15rem;
            margin-bottom: 1.35rem;
        }

        .widget-title-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .widget-icon-mini {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(37, 99, 235, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #38bdf8;
        }

        .widget-title-text {
            font-size: 0.95rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.01em;
        }

        .widget-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-live-green {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
        }

        .badge-live-blue {
            background: rgba(14, 165, 233, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.4);
            color: #38bdf8;
        }

        .badge-live-amber {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: #fbbf24;
        }

        .widget-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
            margin-bottom: 1.15rem;
        }

        .widget-tile {
            background: rgba(255, 255, 255, 0.035);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 0.85rem 1rem;
        }

        .widget-label {
            font-size: 0.725rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .widget-val {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
        }

        .widget-highlight-bar {
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: 14px;
            padding: 0.9rem 1.15rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .widget-highlight-lbl {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.05em;
        }

        .widget-highlight-val {
            font-size: 1.3rem;
            font-weight: 800;
            color: #38bdf8;
        }

        /* ==========================================================================
           CTA SECTION
           ========================================================================== */
        .cta-section {
            padding: 3rem 3.5rem 7rem;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .cta-box {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.12) 0%, rgba(37, 99, 235, 0.18) 50%, rgba(99, 102, 241, 0.12) 100%);
            border: 1px solid rgba(56, 189, 248, 0.35);
            border-radius: 32px;
            padding: 4.5rem 3rem;
            text-align: center;
            backdrop-filter: blur(24px);
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7), inset 0 1px 1px rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .cta-title {
            font-size: 2.75rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 1rem;
            letter-spacing: -0.025em;
        }

        .cta-desc {
            font-size: 1.125rem;
            color: #cbd5e1;
            max-width: 680px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }

        /* ==========================================================================
           5. QUICK LOGIN MODAL & SUITE DETAIL MODAL
           ========================================================================== */
        .modal-backdrop-vip {
            position: fixed;
            inset: 0;
            background: rgba(2, 5, 14, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .modal-vip-dialog {
            background: linear-gradient(135deg, rgba(17, 26, 48, 0.96) 0%, rgba(8, 14, 28, 0.98) 100%);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 28px;
            max-width: 490px;
            width: 100%;
            padding: 2.75rem;
            box-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.9), 0 0 45px rgba(14, 165, 233, 0.25), inset 0 1px 1px rgba(255, 255, 255, 0.2);
            position: relative;
            animation: modalPop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-close-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            cursor: pointer;
            padding: 0.45rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .modal-close-btn:hover {
            color: #ffffff;
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            transform: rotate(90deg);
        }

        /* FOOTER */
        .vip-footer {
            padding: 5rem 3.5rem 3rem;
            background: #02040b;
            border-top: 1px solid var(--vip-border);
            color: #64748b;
            font-size: 0.875rem;
            position: relative;
        }

        .vip-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.4), transparent);
        }

        .footer-grid {
            max-width: 1380px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 3.5rem;
            margin-bottom: 3.5rem;
        }

        .footer-col h4 {
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            letter-spacing: -0.01em;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #38bdf8;
            padding-left: 4px;
        }

        @media (max-width: 1024px) {
            .hero-slider-wrap { min-height: 620px; }
            .hero-title { font-size: 2.5rem; }
            .hero-slide-container { padding: 0 1.5rem; }
            .features-grid, .benefits-grid { grid-template-columns: repeat(2, 1fr); }
            .step-tabs-nav { grid-template-columns: 1fr 1fr; }
            .step-content-pane { grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .vip-nav-links { display: none; }
        }

        @media (max-width: 768px) {
            .hero-slider-wrap { min-height: 520px; }
            .hero-title { font-size: 1.85rem; }
            .hero-desc { font-size: 0.95rem; margin-bottom: 1.5rem; }
            .hero-bottom-strip { display: none; }
            .hero-nav-arrow { display: none; }
            .hero-dots-wrap { top: auto; bottom: 1.25rem; right: 50%; transform: translateX(50%); }
            .features-grid { grid-template-columns: 1fr; }
            .features-section, .workflow-section, .cta-section { padding: 3.5rem 1.25rem; }
            .cta-box { padding: 2.5rem 1.25rem; }
            .cta-title { font-size: 1.75rem; }
            .section-title { font-size: 1.85rem; }
            .hero-btn-group { flex-direction: column; align-items: stretch; width: 100%; max-width: 320px; }
            .step-tabs-nav { grid-template-columns: 1fr; }
            .widget-grid-2col { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- TOP STICKY NAVBAR -->
    <nav class="vip-nav">
        <a href="<?= url('/index.php') ?>" class="vip-logo">
            <div class="vip-logo-icon">
                <?= svgIcon('building', '', 24) ?>
            </div>
            <div class="vip-logo-text">
                <h1>CĂN HỘ DỊCH VỤ</h1>
                <span>LUXURY LIVING & SUITES</span>
            </div>
        </a>

        <ul class="vip-nav-links">
            <li><a href="#hero">Trang chủ</a></li>
            <li><a href="#features">Tính năng quản lý</a></li>
            <li><a href="#workflow">Quy trình vận hành</a></li>
        </ul>

        <div class="vip-nav-actions">
            <?php if ($isLoggedIn): ?>
                <a href="<?= e($dashboardUrl) ?>" class="btn-vip-primary" style="padding: 0.6rem 1.35rem; font-size: 0.9rem;">
                    <?= svgIcon('home', '', 16) ?>
                    <span>Vào Dashboard</span>
                </a>
            <?php else: ?>
                <button type="button" onclick="openLoginModal()" class="btn-vip-outline" style="padding: 0.6rem 1.25rem; font-size: 0.9rem;">
                    <?= svgIcon('lock', '', 16) ?>
                    <span>Đăng nhập</span>
                </button>
                <a href="<?= url('/auth/register.php') ?>" class="btn-vip-primary" style="padding: 0.6rem 1.25rem; font-size: 0.9rem;">
                    <span>Đăng ký mới</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ========================================================================
         1. HERO SLIDER CAROUSEL (4 SLIDES LUXURY VIP NEXT-GEN OPERATING SYSTEM)
         ======================================================================== -->
    <?php
    // 4 Ảnh nền Hero Slider Full HD siêu sang trọng chuẩn Luxury Serviced Apartments 5 sao
    $bgSlide1 = url('/uploads/can-ho/luxury_hero_slide_1.jpg');
    $bgSlide2 = url('/uploads/can-ho/luxury_hero_slide_2.jpg');
    $bgSlide3 = url('/uploads/can-ho/luxury_hero_slide_3.jpg');
    $bgSlide4 = url('/uploads/can-ho/luxury_hero_slide_4.jpg');
    $gaugeDeg = min(360, max(0, round(($occupancyRate / 100) * 360))) . 'deg';
    ?>
    <section class="hero-slider-wrap" id="hero">
        
        <!-- SLIDE 1: QUẢN LÝ CĂN HỘ TẬP TRUNG -->
        <div class="hero-slide active" data-slide="0">
            <div class="hero-slide-bg" style="background-image: url('<?= $bgSlide1 ?>');"></div>
            <div class="hero-slide-overlay"></div>
            <div class="hero-slide-container">
                <h2 class="hero-title">
                    Quản Lý Căn Hộ Tập Trung<br>
                    <span class="hero-gold-text">Theo Dõi Phòng & Khách Thuê Dễ Dàng</span>
                </h2>
                <p class="hero-desc">
                    Quản lý thông tin căn hộ, phòng và khách thuê trên một hệ thống duy nhất.
                </p>
                <div class="hero-btn-group">
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Bắt đầu quản trị ngay</span>
                        <?= svgIcon('arrow-right', '', 18) ?>
                    </button>
                    <a href="#features" class="btn-vip-outline">
                        <?= svgIcon('layers', '', 18) ?>
                        <span>Khám phá tính năng</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- SLIDE 2: QUẢN LÝ ĐIỆN NƯỚC -->
        <div class="hero-slide" data-slide="1">
            <div class="hero-slide-bg" style="background-image: url('<?= $bgSlide2 ?>');"></div>
            <div class="hero-slide-overlay"></div>
            <div class="hero-slide-container">
                <h2 class="hero-title">
                    Quản Lý Điện Nước<br>
                    <span class="hero-gold-text">Tính Tiền & Theo Dõi Chỉ Số</span>
                </h2>
                <p class="hero-desc">
                    Cập nhật chỉ số điện nước và tự động tính chi phí theo từng phòng.
                </p>
                <div class="hero-btn-group">
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Trải nghiệm chốt số ngay</span>
                        <?= svgIcon('arrow-right', '', 18) ?>
                    </button>
                    <a href="#workflow" class="btn-vip-outline">
                        <?= svgIcon('help-circle', '', 18) ?>
                        <span>Xem quy trình 4 bước</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- SLIDE 3: QUẢN LÝ HỢP ĐỒNG -->
        <div class="hero-slide" data-slide="2">
            <div class="hero-slide-bg" style="background-image: url('<?= $bgSlide3 ?>');"></div>
            <div class="hero-slide-overlay"></div>
            <div class="hero-slide-container">
                <h2 class="hero-title">
                    Quản Lý Hợp Đồng<br>
                    <span class="hero-gold-text">Theo Dõi Thời Hạn & Gia Hạn</span>
                </h2>
                <p class="hero-desc">
                    Lưu trữ thông tin hợp đồng, tiền cọc và nhắc thời hạn để hạn chế bỏ sót.
                </p>
                <div class="hero-btn-group">
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Truy cập hồ sơ hợp đồng</span>
                        <?= svgIcon('arrow-right', '', 18) ?>
                    </button>
                    <a href="<?= url('/auth/login.php') ?>" class="btn-vip-outline">
                        <?= svgIcon('lock', '', 18) ?>
                        <span>Đăng nhập hệ thống</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- SLIDE 4: QUẢN LÝ THU TIỀN -->
        <div class="hero-slide" data-slide="3">
            <div class="hero-slide-bg" style="background-image: url('<?= $bgSlide4 ?>');"></div>
            <div class="hero-slide-overlay"></div>
            <div class="hero-slide-container">
                <h2 class="hero-title">
                    Quản Lý Thu Tiền<br>
                    <span class="hero-gold-text">Theo Dõi Hóa Đơn & Công Nợ</span>
                </h2>
                <p class="hero-desc">
                    Theo dõi tiền phòng, điện nước và các khoản phí của từng khách thuê một cách rõ ràng.
                </p>
                <div class="hero-btn-group">
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Xem quản lý tài chính</span>
                        <?= svgIcon('arrow-right', '', 18) ?>
                    </button>
                    <a href="#features" class="btn-vip-outline">
                        <?= svgIcon('layers', '', 18) ?>
                        <span>Khám phá tính năng</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- DẢI 4 THÔNG SỐ VÀNG Ở ĐÁY HERO (TINH GỌN & THẨM MỸ) -->
        <div class="hero-bottom-strip">
            <div class="hero-metric-item">
                <span class="hero-metric-val"><?= $totalRooms ?>+</span>
                <span class="hero-metric-lbl">Căn Hộ Vận Hành</span>
            </div>
            <div class="hero-metric-divider"></div>
            <div class="hero-metric-item">
                <span class="hero-metric-val"><?= $occupancyRate ?>%</span>
                <span class="hero-metric-lbl">Tỉ Lệ Lấp Đầy</span>
            </div>
            <div class="hero-metric-divider"></div>
            <div class="hero-metric-item">
                <span class="hero-metric-val">100%</span>
                <span class="hero-metric-lbl">Minh Bạch Số Liệu</span>
            </div>
            <div class="hero-metric-divider"></div>
            <div class="hero-metric-item">
                <span class="hero-metric-val">24/7</span>
                <span class="hero-metric-lbl">Vận Hành & Hỗ Trợ</span>
            </div>
        </div>

        <!-- SLIDER CONTROLS: MŨI TÊN CHUYỂN SLIDE KÍNH MỜ TINH TẾ -->
        <button type="button" class="hero-nav-arrow arrow-prev" onclick="prevHeroSlide()" title="Slide trước">
            <?= svgIcon('chevron-left', '', 22) ?>
        </button>
        <button type="button" class="hero-nav-arrow arrow-next" onclick="nextHeroSlide()" title="Slide kế tiếp">
            <?= svgIcon('chevron-right', '', 22) ?>
        </button>

        <!-- VẠCH CHỈ SỐ SLIDE GÓC TRÊN PHẢI -->
        <div class="hero-dots-wrap">
            <div class="hero-dot-line active" onclick="setHeroSlide(0)" title="Slide 1"></div>
            <div class="hero-dot-line" onclick="setHeroSlide(1)" title="Slide 2"></div>
            <div class="hero-dot-line" onclick="setHeroSlide(2)" title="Slide 3"></div>
            <div class="hero-dot-line" onclick="setHeroSlide(3)" title="Slide 4"></div>
        </div>
    </section>


    <!-- ========================================================================
         2. PHÂN HỆ TÍNH NĂNG QUẢN TRỊ TOÀN DIỆN (ENTERPRISE FEATURES GRID)
         ======================================================================== -->
    <section class="features-section" id="features">
        <div class="section-header-center">
            <h2 class="section-title">Giải Pháp Quản Trị Căn Hộ Dịch Vụ Toàn Diện</h2>
            <p class="section-subtitle">
                Số hóa toàn diện mọi khâu quản lý từ tiếp nhận khách thuê, chốt điện nước, phát hành hóa đơn tự động đến bảo trì tòa nhà trên một nền tảng duy nhất.
            </p>
        </div>

        <div class="features-grid">
            <!-- FEATURE 1 -->
            <div class="feature-card">
                <div class="feature-top-row">
                    <div class="feature-icon-box icon-blue">
                        <?= svgIcon('building', '', 26) ?>
                    </div>
                    <span class="feature-badge">Quản lý trực quan</span>
                </div>
                <h3 class="feature-title">Quản Lý Phòng</h3>
                <p class="feature-desc">
                    Theo dõi tình trạng phòng trống, đang thuê hoặc bảo trì theo thời gian thực. Phân loại linh hoạt Studio, 1PN, 2PN, Duplex cùng biểu phí dịch vụ riêng.
                </p>
                <ul class="feature-points">
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Cập nhật tình trạng phòng tức thì</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Thiết lập đơn giá & dịch vụ riêng từng căn</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Tra cứu nhanh theo số phòng và loại phòng</li>
                </ul>
            </div>

            <!-- FEATURE 2 -->
            <div class="feature-card">
                <div class="feature-top-row">
                    <div class="feature-icon-box icon-amber">
                        <?= svgIcon('electric', '', 26) ?>
                    </div>
                    <span class="feature-badge">Chính xác 100%</span>
                </div>
                <h3 class="feature-title">Chốt Số Điện Nước</h3>
                <p class="feature-desc">
                    Tự động kế thừa chỉ số cũ từ kỳ trước, chỉ cần nhập số mới. Hệ thống tự động tính lượng tiêu thụ và thành tiền theo đơn giá phòng, không lo nhầm lẫn.
                </p>
                <ul class="feature-points">
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Ghi nhận nhanh ngay trên hệ thống</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Cảnh báo nếu chỉ số mới nhỏ hơn chỉ số cũ</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Tự động liên kết phát hành hóa đơn tháng</li>
                </ul>
            </div>

            <!-- FEATURE 3 -->
            <div class="feature-card">
                <div class="feature-top-row">
                    <div class="feature-icon-box icon-emerald">
                        <?= svgIcon('file-text', '', 26) ?>
                    </div>
                    <span class="feature-badge">Hồ sơ số hóa</span>
                </div>
                <h3 class="feature-title">Hợp Đồng & Khách Thuê</h3>
                <p class="feature-desc">
                    Lưu trữ hồ sơ cư dân an toàn, quản lý chi tiết tiền đặt cọc, ngày bắt đầu và ngày kết thúc. Chủ động cảnh báo trước hạn để chuẩn bị tái ký hoặc bàn giao.
                </p>
                <ul class="feature-points">
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Quản lý thông tin CCCD và hợp đồng thuê</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Quản lý tiền cọc minh bạch, an toàn</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Tự động cảnh báo hợp đồng sắp hết hạn</li>
                </ul>
            </div>

            <!-- FEATURE 4 -->
            <div class="feature-card">
                <div class="feature-top-row">
                    <div class="feature-icon-box icon-purple">
                        <?= svgIcon('wallet', '', 26) ?>
                    </div>
                    <span class="feature-badge">Gạch nợ tức thì</span>
                </div>
                <h3 class="feature-title">Hóa Đơn & Thanh Toán</h3>
                <p class="feature-desc">
                    Tổng hợp tự động tiền phòng, điện nước và dịch vụ (xe máy, wifi, vệ sinh) thành một hóa đơn hoàn chỉnh. Quản lý công nợ và trạng thái thanh toán minh bạch.
                </p>
                <ul class="feature-points">
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Ghi nhận thanh toán chuyển khoản & tiền mặt</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Xuất in hóa đơn khổ chuẩn A4 / A5</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Theo dõi công nợ & lịch sử thanh toán</li>
                </ul>
            </div>

            <!-- FEATURE 5 -->
            <div class="feature-card">
                <div class="feature-top-row">
                    <div class="feature-icon-box icon-rose">
                        <?= svgIcon('tool', '', 26) ?>
                    </div>
                    <span class="feature-badge">Phản hồi 24/7</span>
                </div>
                <h3 class="feature-title">Tiếp Nhận & Xử Lý Sự Cố</h3>
                <p class="feature-desc">
                    Ghi nhận kịp thời các phản ánh hư hỏng thiết bị (máy lạnh, đường ống nước, bóng đèn...) từ khách thuê. Phân công kỹ thuật và cập nhật tiến độ liên tục.
                </p>
                <ul class="feature-points">
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Tiếp nhận yêu cầu sửa chữa tức thời</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Theo dõi tiến độ từ tiếp nhận đến hoàn tất</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Lưu vết lịch sử bảo dưỡng từng phòng</li>
                </ul>
            </div>

            <!-- FEATURE 6 -->
            <div class="feature-card">
                <div class="feature-top-row">
                    <div class="feature-icon-box icon-sky">
                        <?= svgIcon('trend-up', '', 26) ?>
                    </div>
                    <span class="feature-badge">Thời gian thực</span>
                </div>
                <h3 class="feature-title">Báo Cáo & Thống Kê Doanh Thu</h3>
                <p class="feature-desc">
                    Cung cấp cái nhìn toàn diện về tỷ lệ lấp đầy buồng phòng, dòng tiền thu chi thực tế, công nợ quá hạn và hỗ trợ xuất dữ liệu phục vụ đối soát định kỳ.
                </p>
                <ul class="feature-points">
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Biểu đồ doanh thu trực quan theo tháng</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Báo cáo công nợ & danh sách nợ đọng</li>
                    <li class="feature-point-item"><?= svgIcon('check', '', 14) ?> Dữ liệu đồng bộ 100% với hệ thống</li>
                </ul>
            </div>
        </div>
    </section>


    <!-- ========================================================================
         4. QUY TRÌNH VẬN HÀNH 4 BƯỚC (INTERACTIVE WORKFLOW TABS)
         ======================================================================== -->
    <section class="workflow-section" id="workflow">
        <div class="workflow-container">
            <div style="text-align: center; max-width: 720px; margin: 0 auto 3.5rem;">
                <h2 class="section-title">Quy Trình Quản Lý 4 Bước</h2>
                <p class="section-subtitle">
                    Chuẩn hóa mọi thao tác từ lúc khách nhận phòng, chốt điện nước định kỳ đến xuất hóa đơn và xử lý kỹ thuật.
                </p>
            </div>

            <!-- 4 BƯỚC QUY TRÌNH VẬN HÀNH -->
            <div class="step-tabs-nav">
                <div class="step-tab-btn">
                    <div class="step-num">BƯỚC 01</div>
                    <div class="step-tab-title">Khách Thuê & Hợp Đồng</div>
                    <div class="step-tab-sub">Lưu thông tin khách thuê, tạo và quản lý hợp đồng thuê căn hộ.</div>
                </div>
                <div class="step-tab-btn">
                    <div class="step-num">BƯỚC 02</div>
                    <div class="step-tab-title">Điện, Nước & Dịch Vụ</div>
                    <div class="step-tab-sub">Chốt chỉ số công tơ điện nước định kỳ và tự động tính phí dịch vụ.</div>
                </div>
                <div class="step-tab-btn">
                    <div class="step-num">BƯỚC 03</div>
                    <div class="step-tab-title">Hóa Đơn & Thanh Toán</div>
                    <div class="step-tab-sub">Tổng hợp chi phí, phát hành hóa đơn và theo dõi lịch sử thanh toán.</div>
                </div>
                <div class="step-tab-btn">
                    <div class="step-num">BƯỚC 04</div>
                    <div class="step-tab-title">Bảo Trì & Báo Cáo</div>
                    <div class="step-tab-sub">Tiếp nhận xử lý sự cố thiết bị và tổng hợp báo cáo tài chính doanh thu.</div>
                </div>
            </div>

        </div>
    </section>

    <!-- ========================================================================
         5. MODAL ĐĂNG NHẬP VIP NHANH NGAY TẠI TRANG CHỦ
         ======================================================================== -->
    <div class="modal-backdrop-vip" id="loginModal" onclick="if(event.target===this) closeLoginModal()">
        <div class="modal-vip-dialog">
            <button type="button" class="modal-close-btn" onclick="closeLoginModal()" title="Đóng">
                <?= svgIcon('x', '', 20) ?>
            </button>

            <div style="text-align: center; margin-bottom: 1.75rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); margin: 0 auto 0.75rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.5);">
                    <?= svgIcon('building', '', 24) ?>
                </div>
                <h3 style="font-size: 1.45rem; font-weight: 800; color: #ffffff; margin-bottom: 0.35rem;">
                    Đăng Nhập Quản Trị
                </h3>
                <p style="font-size: 0.85rem; color: #94a3b8; margin: 0;">
                    Truy cập hệ thống quản lý & vận hành căn hộ
                </p>
            </div>

            <form method="POST" action="<?= url('/auth/login.php') ?>">
                <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

                <div style="margin-bottom: 1.15rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.4rem;">
                        Tên đăng nhập, Email hoặc SĐT
                    </label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748b; display: flex;">
                            <?= svgIcon('user', '', 16) ?>
                        </span>
                        <input type="text" 
                               id="modalAccount" 
                               name="account" 
                               style="width: 100%; padding: 0.8rem 1rem 0.8rem 2.4rem; background: rgba(30,41,59,0.7); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #ffffff; font-family: inherit; font-size: 0.9rem; box-sizing: border-box;"
                               placeholder="Nhập tên đăng nhập" 
                               required>
                    </div>
                </div>

                <div style="margin-bottom: 1.35rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                        <label style="font-size: 0.8rem; font-weight: 600; color: #cbd5e1; margin: 0;">
                            Mật khẩu
                        </label>
                        <a href="<?= url('/auth/forgot-password.php') ?>" style="font-size: 0.75rem; color: #60a5fa; text-decoration: none;">
                            Quên mật khẩu?
                        </a>
                    </div>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748b; display: flex;">
                            <?= svgIcon('lock', '', 16) ?>
                        </span>
                        <input type="password" 
                               id="modalPassword" 
                               name="password" 
                               style="width: 100%; padding: 0.8rem 2.5rem 0.8rem 2.4rem; background: rgba(30,41,59,0.7); border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; color: #ffffff; font-family: inherit; font-size: 0.9rem; box-sizing: border-box;"
                               placeholder="Nhập mật khẩu" 
                               required>
                        <span onclick="toggleModalPwd()" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #64748b; cursor: pointer; display: flex;" id="modalPwdToggle">
                            <?= svgIcon('eye', '', 16) ?>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn-vip-primary" style="width: 100%; justify-content: center; padding: 0.85rem;">
                    <span>XÁC NHẬN ĐĂNG NHẬP</span>
                    <?= svgIcon('arrow-right', '', 18) ?>
                </button>
            </form>

            <div style="margin-top: 1.25rem; text-align: center; font-size: 0.825rem; color: #64748b;">
                Chưa có tài khoản? <a href="<?= url('/auth/register.php') ?>" style="color: #60a5fa; font-weight: 700;">Đăng ký nhân viên</a>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="vip-footer">
        <div class="footer-grid">
            <div class="footer-col">
                <div style="display: flex; align-items: center; gap: 0.65rem; color: #ffffff; font-weight: 800; font-size: 1.1rem; margin-bottom: 1rem;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: #2563eb; display: flex; align-items: center; justify-content: center;">
                        <?= svgIcon('building', '', 18) ?>
                    </div>
                    <span>HỆ THỐNG VẬN HÀNH CĂN HỘ</span>
                </div>
                <p style="line-height: 1.7; font-size: 0.875rem; color: #94a3b8; max-width: 320px;">
                    Hệ thống số hóa toàn diện dành cho chủ đầu tư và đội ngũ vận hành chuỗi căn hộ dịch vụ cao cấp tại TP. Hồ Chí Minh.
                </p>
            </div>

            <div class="footer-col">
                <h4>Phân Hệ Quản Lý</h4>
                <ul class="footer-links">
                    <li><a href="#features">Quản lý Căn hộ & Phòng</a></li>
                    <li><a href="#workflow">Hợp đồng & Khách thuê</a></li>
                    <li><a href="#workflow">Chốt điện nước tự động</a></li>
                    <li><a href="#workflow">Hóa đơn & Thanh toán</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Hỗ Trợ Vận Hành</h4>
                <ul class="footer-links">
                    <li><a href="#workflow">Quy trình vận hành 4 bước</a></li>
                    <li><a href="<?= url('/auth/login.php') ?>">Cổng thông tin nhân viên</a></li>
                    <li><a href="<?= url('/auth/register.php') ?>">Đăng ký tài khoản</a></li>
                </ul>
            </div>

        </div>

        <div style="max-width: 1320px; margin: 0 auto; padding-top: 2rem; border-top: 1px solid var(--vip-border); display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>© <?= date('Y') ?> Hệ thống Quản Lý Căn Hộ Dịch Vụ Cao Cấp</div>
        </div>
    </footer>

    <!-- JAVASCRIPT ĐIỀU KHIỂN SLIDER & MODAL -->
    <script>
    // 1. HERO SLIDER LOGIC (4 SLIDES LUXURY ELEGANCE)
    let currentHeroSlide = 0;
    const heroSlides = document.querySelectorAll('.hero-slide');
    const heroDots = document.querySelectorAll('.hero-dot-line');
    const totalHeroSlides = heroSlides.length;
    let heroTimer = null;

    function setHeroSlide(index) {
        currentHeroSlide = index;
        
        // Cập nhật trạng thái hiển thị Slide mượt mà
        heroSlides.forEach((slide, i) => {
            slide.classList.toggle('active', i === index);
        });

        // Cập nhật vạch chỉ số slide
        heroDots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });
    }

    function nextHeroSlide() {
        setHeroSlide((currentHeroSlide + 1) % totalHeroSlides);
    }

    function prevHeroSlide() {
        setHeroSlide((currentHeroSlide - 1 + totalHeroSlides) % totalHeroSlides);
    }

    function startHeroAutoplay() {
        clearInterval(heroTimer);
        heroTimer = setInterval(nextHeroSlide, 6500);
    }

    function resetHeroAutoplay() {
        clearInterval(heroTimer);
        startHeroAutoplay();
    }

    // Khởi động ban đầu
    setHeroSlide(0);
    startHeroAutoplay();

    // Dừng khi rê chuột vào Hero, tiếp tục khi rời chuột
    const heroWrap = document.getElementById('hero');
    if (heroWrap) {
        heroWrap.addEventListener('mouseenter', () => clearInterval(heroTimer));
        heroWrap.addEventListener('mouseleave', () => resetHeroAutoplay());

        // Hỗ trợ Touch Swipe trên điện thoại / tablet
        let touchStartX = 0;
        let touchEndX = 0;

        heroWrap.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        heroWrap.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            if (touchEndX < touchStartX - 50) {
                nextHeroSlide();
                resetHeroAutoplay();
            } else if (touchEndX > touchStartX + 50) {
                prevHeroSlide();
                resetHeroAutoplay();
            }
        }, { passive: true });
    }


    // 3. QUICK LOGIN MODAL LOGIC
    function openLoginModal() {
        document.getElementById('loginModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLoginModal() {
        document.getElementById('loginModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function toggleModalPwd() {
        const inp = document.getElementById('modalPassword');
        const icon = document.getElementById('modalPwdToggle');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.style.color = '#38bdf8';
        } else {
            inp.type = 'password';
            icon.style.color = '#64748b';
        }
    }

    // Đóng modal khi nhấn Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeLoginModal();
        }
    });
    </script>

</body>
</html>
