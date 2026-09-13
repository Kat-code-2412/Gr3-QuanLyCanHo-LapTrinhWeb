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
            background-color: var(--vip-bg-dark);
            color: var(--vip-text-main);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-image: 
                radial-gradient(circle at 12% 10%, rgba(37, 99, 235, 0.14) 0%, transparent 42%),
                radial-gradient(circle at 88% 20%, rgba(6, 182, 212, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 50% 60%, rgba(99, 102, 241, 0.09) 0%, transparent 50%),
                radial-gradient(circle at 80% 85%, rgba(16, 185, 129, 0.08) 0%, transparent 45%),
                linear-gradient(to right, rgba(255, 255, 255, 0.018) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.018) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 100% 100%, 100% 100%, 60px 60px, 60px 60px;
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
           1. HERO SLIDER CAROUSEL (CINEMATIC LUXURY VIP)
           ========================================================================== */
        .hero-slider-wrap {
            position: relative;
            width: 100%;
            height: calc(100vh - 78px);
            min-height: 660px;
            max-height: 840px;
            overflow: hidden;
            background: #02050e;
        }

        .hero-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.9s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.9s;
            display: flex;
            align-items: center;
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
            transform: scale(1.07);
            filter: brightness(0.9) contrast(1.12) saturate(1.2);
            transition: transform 8s cubic-bezier(0.25, 1, 0.5, 1);
            z-index: 0;
        }

        .hero-slide.active .hero-slide-bg {
            transform: scale(1);
        }

        .hero-slide-overlay {
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 76% 50%, rgba(4, 7, 20, 0.08) 0%, rgba(4, 7, 20, 0.55) 65%, rgba(4, 7, 20, 0.94) 100%),
                linear-gradient(90deg, rgba(4, 7, 20, 0.96) 0%, rgba(4, 7, 20, 0.84) 40%, rgba(4, 7, 20, 0.35) 75%, rgba(4, 7, 20, 0.2) 100%),
                linear-gradient(0deg, rgba(4, 7, 20, 0.95) 0%, transparent 25%);
            z-index: 1;
        }

        .hero-slide-container {
            max-width: 1380px;
            width: 100%;
            margin: 0 auto;
            padding: 0 4rem;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
        }

        .hero-title {
            font-size: 3.45rem;
            font-weight: 800;
            line-height: 1.14;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin-bottom: 1.35rem;
            text-shadow: 0 4px 28px rgba(0, 0, 0, 0.8);
        }

        .hero-title .text-gradient {
            background: linear-gradient(135deg, #00f0ff 0%, #38bdf8 45%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
            filter: drop-shadow(0 0 25px rgba(56, 189, 248, 0.45));
        }

        .hero-desc {
            font-size: 1.15rem;
            color: #cbd5e1;
            line-height: 1.75;
            max-width: 640px;
            margin-bottom: 2.5rem;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.7);
        }

        .hero-btn-group {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        /* ==========================================================================
           VIP BUTTONS (NEON GLOW & SHINE EFFECTS)
           ========================================================================== */
        .btn-vip-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2.25rem;
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 50%, #4f46e5 100%);
            color: #ffffff !important;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 14px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.25);
            cursor: pointer;
            box-shadow: 0 0 28px rgba(14, 165, 233, 0.45), 0 10px 25px -5px rgba(37, 99, 235, 0.55);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-vip-primary::after {
            content: '';
            position: absolute;
            top: 0;
            left: -120%;
            width: 80%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), transparent);
            transform: skewX(-20deg);
            transition: left 0.65s ease;
        }

        .btn-vip-primary:hover::after {
            left: 150%;
        }

        .btn-vip-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 0 38px rgba(14, 165, 233, 0.65), 0 15px 32px -4px rgba(37, 99, 235, 0.7);
            background: linear-gradient(135deg, #38bdf8 0%, #3b82f6 50%, #6366f1 100%);
        }

        .btn-vip-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background: rgba(13, 20, 38, 0.6);
            color: #ffffff !important;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 14px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.16);
            cursor: pointer;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-vip-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(56, 189, 248, 0.65);
            color: #38bdf8 !important;
            transform: translateY(-3px);
            box-shadow: 0 0 25px rgba(56, 189, 248, 0.35), 0 8px 25px -4px rgba(0, 0, 0, 0.5);
        }

        /* SLIDER NAVIGATION CONTROLS */
        .hero-nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(8, 14, 30, 0.72);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            backdrop-filter: blur(18px);
            transition: all 0.25s ease;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.6);
        }

        .hero-nav-arrow:hover {
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            border-color: rgba(255, 255, 255, 0.45);
            transform: translateY(-50%) scale(1.12);
            box-shadow: 0 0 28px rgba(14, 165, 233, 0.65);
            color: #ffffff;
        }

        .arrow-prev { left: 2.5rem; }
        .arrow-next { right: 2.5rem; }

        .hero-dots-wrap {
            position: absolute;
            bottom: 2.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 0.85rem;
            z-index: 10;
            padding: 0.55rem 1.15rem;
            background: rgba(4, 7, 20, 0.65);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        }

        .hero-dot {
            width: 40px;
            height: 5px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.22);
            cursor: pointer;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hero-dot.active {
            background: linear-gradient(90deg, #00f0ff, #38bdf8);
            width: 68px;
            box-shadow: 0 0 18px rgba(0, 240, 255, 0.75);
        }

        /* ==========================================================================
           3. BỘ SƯU TẬP CĂN HỘ DỊCH VỤ MẪU (INTERACTIVE SUITE CAROUSEL)
           ========================================================================== */
        .suites-section {
            padding: 7rem 3.5rem;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .section-header-wrap {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 3rem;
            flex-wrap: wrap;
            gap: 1.75rem;
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
            margin-bottom: 0.65rem;
            padding: 0.35rem 0.9rem;
            background: rgba(14, 165, 233, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: 9999px;
            backdrop-filter: blur(8px);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.025em;
            margin: 0;
            background: linear-gradient(135deg, #ffffff 60%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            background: rgba(13, 20, 38, 0.75);
            padding: 0.4rem;
            border-radius: 16px;
            border: 1px solid var(--vip-border);
            backdrop-filter: blur(18px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
        }

        .filter-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 0.6rem 1.25rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .filter-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.06);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            color: #ffffff;
            box-shadow: 0 0 18px rgba(14, 165, 233, 0.45);
        }

        .suites-carousel-container {
            position: relative;
        }

        .suites-slider {
            display: flex;
            gap: 2rem;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 1.25rem 0.5rem 2.5rem;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .suites-slider::-webkit-scrollbar {
            display: none;
        }

        /* VIP SUITE CARD */
        .suite-card {
            flex: 0 0 360px;
            background: linear-gradient(180deg, rgba(17, 26, 48, 0.88) 0%, rgba(8, 14, 28, 0.96) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            overflow: hidden;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 45px -15px rgba(0, 0, 0, 0.75), inset 0 1px 1px rgba(255, 255, 255, 0.15);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .suite-card:hover {
            transform: translateY(-10px) scale(1.015);
            border-color: rgba(56, 189, 248, 0.45);
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.85), 0 0 35px rgba(14, 165, 233, 0.25), inset 0 1px 1px rgba(255, 255, 255, 0.2);
        }

        .suite-img-wrap {
            height: 235px;
            width: 100%;
            position: relative;
            overflow: hidden;
            background: #040714;
        }

        .suite-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.65s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .suite-card:hover .suite-img {
            transform: scale(1.1);
        }

        .suite-price-pill {
            position: absolute;
            bottom: 14px;
            left: 14px;
            background: rgba(4, 7, 20, 0.88);
            border: 1px solid rgba(56, 189, 248, 0.35);
            border-radius: 10px;
            padding: 0.4rem 0.85rem;
            font-size: 0.875rem;
            font-weight: 800;
            color: #38bdf8;
            backdrop-filter: blur(14px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
            letter-spacing: -0.01em;
        }

        .suite-status-pill {
            position: absolute;
            top: 14px;
            right: 14px;
            border-radius: 8px;
            padding: 0.35rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(14px);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-ready {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.45);
            color: #34d399;
            box-shadow: 0 0 16px rgba(16, 185, 129, 0.3);
        }

        .status-rented {
            background: rgba(37, 99, 235, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.45);
            color: #60a5fa;
            box-shadow: 0 0 16px rgba(37, 99, 235, 0.3);
        }

        .status-maint {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.45);
            color: #fbbf24;
            box-shadow: 0 0 16px rgba(245, 158, 11, 0.3);
        }

        .suite-img-count {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(4, 7, 20, 0.82);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border-radius: 8px;
            padding: 0.3rem 0.7rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            backdrop-filter: blur(14px);
        }

        .suite-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .suite-room-code {
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .suite-type-name {
            font-size: 0.875rem;
            color: #38bdf8;
            font-weight: 700;
            margin-bottom: 0.6rem;
        }

        .suite-location {
            font-size: 0.825rem;
            color: #94a3b8;
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
            line-height: 1.45;
            margin-bottom: 1rem;
            min-height: 38px;
        }

        .suite-services-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.55rem;
            padding: 0.85rem 0;
            border-top: 1px solid var(--vip-border);
            border-bottom: 1px solid var(--vip-border);
            margin-bottom: 1.15rem;
        }

        .suite-svc-item {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.775rem;
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.035);
            padding: 0.4rem 0.55rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }

        .suite-svc-item strong {
            color: #f1f5f9;
            font-weight: 700;
        }

        .btn-view-suite {
            width: 100%;
            background: rgba(14, 165, 233, 0.12);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
            border-radius: 12px;
            padding: 0.75rem;
            font-size: 0.875rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-view-suite:hover {
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 22px rgba(14, 165, 233, 0.5);
            transform: translateY(-2px);
        }

        .suite-scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(8, 14, 30, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(14px);
            transition: all 0.25s ease;
        }

        .suite-scroll-btn:hover {
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-50%) scale(1.12);
            box-shadow: 0 0 25px rgba(14, 165, 233, 0.6);
        }

        .suite-scroll-prev { left: -25px; }
        .suite-scroll-next { right: -25px; }

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
            margin-bottom: 4rem;
        }

        .step-tab-btn {
            background: rgba(13, 20, 38, 0.65);
            border: 1px solid var(--vip-border);
            border-radius: 20px;
            padding: 1.75rem 1.5rem;
            text-align: left;
            cursor: pointer;
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
            background: transparent;
            border-radius: 0 0 4px 4px;
            transition: all 0.3s ease;
        }

        .step-tab-btn:hover {
            background: rgba(20, 31, 56, 0.8);
            border-color: rgba(255, 255, 255, 0.16);
            transform: translateY(-3px);
        }

        .step-tab-btn.active {
            background: linear-gradient(180deg, rgba(14, 165, 233, 0.15) 0%, rgba(13, 20, 38, 0.85) 100%);
            border-color: rgba(56, 189, 248, 0.45);
            box-shadow: 0 12px 30px -8px rgba(14, 165, 233, 0.35), inset 0 1px 1px rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        .step-tab-btn.active::before {
            background: linear-gradient(90deg, #00f0ff, #38bdf8);
            box-shadow: 0 0 14px rgba(0, 240, 255, 0.85);
        }

        .step-num {
            font-size: 0.8rem;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 0.6rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .step-tab-btn.active .step-num {
            color: #38bdf8;
        }

        .step-tab-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.35rem;
        }

        .step-tab-sub {
            font-size: 0.825rem;
            color: #94a3b8;
            line-height: 1.5;
        }

        .step-content-pane {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 3.5rem;
            align-items: center;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(8, 14, 28, 0.96) 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 28px;
            padding: 3.5rem;
            backdrop-filter: blur(24px);
            box-shadow: 0 25px 60px -20px rgba(0, 0, 0, 0.8), inset 0 1px 1px rgba(255, 255, 255, 0.15);
        }

        .step-content-pane.active {
            display: grid;
            animation: fadeInStep 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes fadeInStep {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-pane-info h3 {
            font-size: 2.15rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 1.15rem;
            letter-spacing: -0.025em;
            background: linear-gradient(135deg, #ffffff 70%, #93c5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .step-pane-info p {
            color: #cbd5e1;
            font-size: 1.05rem;
            line-height: 1.75;
            margin-bottom: 1.75rem;
        }

        .step-check-list {
            list-style: none;
            padding: 0;
            margin: 0 0 2.25rem;
        }

        .step-check-list li {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-size: 0.95rem;
            color: #e2e8f0;
            margin-bottom: 0.85rem;
        }

        .step-check-list li svg {
            color: #34d399;
            flex-shrink: 0;
            filter: drop-shadow(0 0 6px rgba(52, 211, 153, 0.5));
        }

        /* HUD COMMAND-CENTER PREVIEW BOX */
        .step-preview-box {
            background: #040714;
            border: 1px solid rgba(56, 189, 248, 0.25);
            border-radius: 20px;
            padding: 2.25rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.75), 0 0 35px rgba(14, 165, 233, 0.15), inset 0 1px 1px rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .step-preview-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 25px;
            right: 25px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.6), transparent);
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

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .hero-slide-container { padding: 0 2rem; }
            .hero-title { font-size: 2.7rem; }
            .step-tabs-nav { grid-template-columns: 1fr 1fr; }
            .step-content-pane { grid-template-columns: 1fr; gap: 2rem; padding: 2rem; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .vip-nav-links { display: none; }
        }

        @media (max-width: 640px) {
            .vip-nav { padding: 0 1.25rem; }
            .hero-title { font-size: 2.15rem; }
            .hero-btn-group { flex-direction: column; align-items: stretch; }
            .step-tabs-nav { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
            .arrow-prev, .arrow-next { display: none; }
            .suites-section { padding: 4rem 1.25rem; }
            .workflow-section { padding: 4rem 1.25rem; }
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
            <li><a href="#suites">Bộ sưu tập Suites</a></li>
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
         1. HERO SLIDER CAROUSEL (4 SLIDES LUXURY VIP)
         ======================================================================== -->
    <?php
    // Chuẩn bị danh sách ảnh nền cho Hero Slider (kết hợp ảnh căn hộ thực tế và ảnh seed chất lượng cao)
    $heroSlideImages = [];
    if (!empty($imgRows)) {
        foreach ($imgRows as $ir) {
            if (!empty($ir['DuongDan'])) {
                $heroSlideImages[] = url('/' . ltrim($ir['DuongDan'], '/'));
            }
        }
    }
    if (empty($heroSlideImages) && !empty($featuredSuites)) {
        foreach ($featuredSuites as $fs) {
            if (!empty($fs['AnhDaiDien'])) {
                $heroSlideImages[] = url('/' . ltrim($fs['AnhDaiDien'], '/'));
            }
        }
    }
    $bgSlide1 = $heroSlideImages[0] ?? url('/uploads/can-ho/seed_1pn_36b3e8db12d94465a1f11565f05fca70_phong-ngu-anh-khai-go-vap-3.jpg');
    $bgSlide2 = $heroSlideImages[1] ?? url('/uploads/can-ho/seed_penthouse_5b920eeeccf34de399d46c99971512f3_images.jpg');
    $bgSlide3 = $heroSlideImages[2] ?? url('/uploads/can-ho/seed_2pn_4d8b2f62cfc2490c95993e8beb7d4667_images.jpg');
    ?>
    <section class="hero-slider-wrap" id="hero">
        
        <!-- SLIDE 1 -->
        <div class="hero-slide active" data-slide="0">
            <div class="hero-slide-bg" style="background-image: url('<?= $bgSlide1 ?>');"></div>
            <div class="hero-slide-overlay"></div>
            <div class="hero-slide-container">
                <div style="max-width: 780px;">
                    <h2 class="hero-title">
                        Hệ Thống Quản Lý Căn Hộ Dịch Vụ<br>
                        <span class="text-gradient">Quản Lý Tập Trung – Vận Hành Hiệu Quả</span>
                    </h2>
                    <p class="hero-desc">
                        Giải pháp hỗ trợ chủ nhà và nhân viên quản lý căn hộ, khách thuê, hợp đồng và các hoạt động vận hành hằng ngày.
                    </p>
                    <div class="hero-btn-group">
                        <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                            <span>Bắt đầu quản trị ngay</span>
                            <?= svgIcon('arrow-right', '', 18) ?>
                        </button>
                        <a href="#suites" class="btn-vip-outline">
                            <?= svgIcon('eye', '', 18) ?>
                            <span>Khám phá các căn hộ</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- SLIDE 2 -->
        <div class="hero-slide" data-slide="1">
            <div class="hero-slide-bg" style="background-image: url('<?= $bgSlide2 ?>');"></div>
            <div class="hero-slide-overlay"></div>
            <div class="hero-slide-container">
                <div style="max-width: 780px;">
                    <h2 class="hero-title">
                        Quản Lý Điện Nước Tập Trung<br>
                        <span class="text-gradient">Theo Dõi Chỉ Số – Tính Phí Chính Xác</span>
                    </h2>
                    <p class="hero-desc">
                        Nhập chỉ số theo tháng, tự động lấy dữ liệu kỳ trước và hỗ trợ tính chi phí cho khách thuê.
                    </p>
                    <div class="hero-btn-group">
                        <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                            <span>Trải nghiệm chốt số ngay</span>
                            <?= svgIcon('arrow-right', '', 18) ?>
                        </button>
                        <a href="#workflow" class="btn-vip-outline">
                            <span>Xem quy trình 4 bước</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- SLIDE 3 -->
        <div class="hero-slide" data-slide="2">
            <div class="hero-slide-bg" style="background-image: url('<?= $bgSlide3 ?>');"></div>
            <div class="hero-slide-overlay"></div>
            <div class="hero-slide-container">
                <div style="max-width: 780px;">
                    <h2 class="hero-title">
                        Quản Lý Hợp Đồng Dễ Dàng<br>
                        <span class="text-gradient">Theo Dõi Thời Hạn – Cảnh Báo Tự Động</span>
                    </h2>
                    <p class="hero-desc">
                        Lưu thông tin khách thuê, tiền cọc và thời hạn hợp đồng, đồng thời nhắc khi hợp đồng sắp hết hạn.
                    </p>
                    <div class="hero-btn-group">
                        <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                            <span>Truy cập hồ sơ hợp đồng</span>
                            <?= svgIcon('arrow-right', '', 18) ?>
                        </button>
                        <a href="<?= url('/auth/login.php') ?>" class="btn-vip-outline">
                            <span>Đăng nhập chuyên biệt</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- SLIDER CONTROLS -->
        <button type="button" class="hero-nav-arrow arrow-prev" onclick="prevHeroSlide()" title="Slide trước">
            <?= svgIcon('chevron-left', '', 24) ?>
        </button>
        <button type="button" class="hero-nav-arrow arrow-next" onclick="nextHeroSlide()" title="Slide kế tiếp">
            <?= svgIcon('chevron-right', '', 24) ?>
        </button>

        <div class="hero-dots-wrap">
            <div class="hero-dot active" onclick="setHeroSlide(0)"></div>
            <div class="hero-dot" onclick="setHeroSlide(1)"></div>
            <div class="hero-dot" onclick="setHeroSlide(2)"></div>
        </div>
    </section>


    <!-- ========================================================================
         3. BỘ SƯU TẬP CĂN HỘ DỊCH VỤ MẪU (INTERACTIVE SUITES CAROUSEL)
         ======================================================================== -->
    <section class="suites-section" id="suites">
        <div class="section-header-wrap">
            <div>
                <div class="section-tag"><?= svgIcon('building', '', 14) ?> HỆ THỐNG CĂN HỘ</div>
                <h2 class="section-title">Khám Phá Các Căn Hộ Dịch Vụ Mẫu</h2>
            </div>
            
            <div class="filter-tabs">
                <button type="button" class="filter-btn active" onclick="filterSuites('all', this)">Tất cả (<?= count($featuredSuites) ?>)</button>
                <button type="button" class="filter-btn" onclick="filterSuites('Studio', this)">Studio (<?= $suiteCounts['Studio'] ?? 0 ?>)</button>
                <button type="button" class="filter-btn" onclick="filterSuites('Duplex', this)">Duplex (<?= $suiteCounts['Duplex'] ?? 0 ?>)</button>
                <button type="button" class="filter-btn" onclick="filterSuites('1 Phòng Ngủ', this)">1 Phòng Ngủ (<?= $suiteCounts['1 Phòng Ngủ'] ?? 0 ?>)</button>
                <button type="button" class="filter-btn" onclick="filterSuites('2 Phòng Ngủ', this)">2 Phòng Ngủ (<?= $suiteCounts['2 Phòng Ngủ'] ?? 0 ?>)</button>
            </div>
        </div>

        <div class="suites-carousel-container">
            <button type="button" class="suite-scroll-btn suite-scroll-prev" onclick="scrollSuites(-360)" title="Lướt sang trái">
                <?= svgIcon('chevron-left', '', 20) ?>
            </button>
            <button type="button" class="suite-scroll-btn suite-scroll-next" onclick="scrollSuites(360)" title="Lướt sang phải">
                <?= svgIcon('chevron-right', '', 20) ?>
            </button>

            <div class="suites-slider" id="suitesSlider">
                <?php if (empty($featuredSuites)): ?>
                    <div style="width: 100%; text-align: center; padding: 3.5rem 1.5rem; background: rgba(15, 23, 42, 0.4); border: 1px dashed rgba(255,255,255,0.12); border-radius: 16px; margin: 0 1rem;">
                        <div style="color: #64748b; margin-bottom: 0.75rem; display: flex; justify-content: center;">
                            <?= svgIcon('building', '', 48) ?>
                        </div>
                        <h3 style="color: #ffffff; font-size: 1.25rem; font-weight: 700; margin-bottom: 0.4rem;">Chưa Có Căn Hộ Nào Trong Hệ Thống</h3>
                        <p style="color: #94a3b8; font-size: 0.95rem; max-width: 500px; margin: 0 auto 1.25rem; line-height: 1.6;">
                            Dữ liệu căn hộ hiện đang trống. Quản trị viên vui lòng đăng nhập vào trang quản trị để thêm các căn hộ mới.
                        </p>
                        <a href="<?= e($dashboardUrl) ?>" class="btn-vip-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <?= svgIcon('home', '', 16) ?> <span>Vào Trang Quản Trị</span>
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($featuredSuites as $suite): 
                        $isRented = ($suite['TrangThai'] === 'Đang thuê');
                        $isMaint = ($suite['TrangThai'] === 'Bảo trì');
                        $statusClass = $isRented ? 'status-rented' : ($isMaint ? 'status-maint' : 'status-ready');
                        $statusText = $isRented ? 'Đang thuê' : ($isMaint ? 'Bảo trì' : 'Trống sẵn sàng');
                        $typeCat = $suite['TenLoai'] ?? 'Studio';
                        $modalData = [
                            'id' => (int)$suite['MaCanHo'],
                            'room' => $suite['SoPhong'],
                            'type' => $suite['TenLoai'] ?? 'Studio',
                            'price' => formatMoney((float)$suite['GiaThue']) . ' / tháng',
                            'area' => $suite['DienTich'] . ' m²',
                            'status' => $statusText,
                            'statusClass' => $statusClass,
                            'address' => $suite['DiaChi'] ?: 'Tòa nhà Căn hộ Dịch vụ',
                            'desc' => $suite['MoTa'] ?: 'Căn hộ dịch vụ tiện nghi, không gian thoáng mát sạch sẽ.',
                            'images' => $suite['AllImages'] ?? [url('/' . ltrim($suite['AnhDaiDien'], '/'))],
                            'dien' => formatMoney(normalizeServiceFee($suite['GiaDien'] ?? 3800, 'dien')) . ' / kWh',
                            'nuoc' => formatMoney(normalizeServiceFee($suite['GiaNuoc'] ?? 100000, 'nuoc')) . ' / tháng',
                            'xeMay' => formatMoney(normalizeServiceFee($suite['GiaXeMay'] ?? 120000, 'xemay')) . ' / xe / tháng',
                            'oto' => formatMoney(normalizeServiceFee($suite['GiaOto'] ?? 1200000, 'oto')) . ' / xe / tháng',
                            'internet' => formatMoney(normalizeServiceFee($suite['GiaInternet'] ?? 100000, 'internet')) . ' / tháng',
                            'veSinh' => formatMoney(normalizeServiceFee($suite['GiaVeSinh'] ?? 50000, 'vesinh')) . ' / tháng'
                        ];
                        $jsonData = htmlspecialchars(json_encode($modalData, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="suite-card" data-type="<?= e($typeCat) ?>">
                        <div class="suite-img-wrap" onclick='openSuiteModal(<?= $jsonData ?>)' style="cursor: pointer;" title="Bấm để xem album ảnh & chi tiết">
                            <img src="<?= url('/' . ltrim($suite['AnhDaiDien'], '/')) ?>" alt="Căn <?= e($suite['SoPhong']) ?>" class="suite-img" loading="lazy">
                            <div class="suite-price-pill">
                                <?= formatMoney((float)$suite['GiaThue']) ?> / tháng
                            </div>
                            <div class="suite-status-pill <?= $statusClass ?>">
                                <?= $statusText ?>
                            </div>
                            <?php if (!empty($suite['AllImages']) && count($suite['AllImages']) > 1): ?>
                                <div class="suite-img-count">
                                    <?= svgIcon('camera', '', 12) ?>
                                    <span><?= count($suite['AllImages']) ?> ảnh</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="suite-body">
                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.25rem;">
                                <div class="suite-room-code">Phòng <?= e($suite['SoPhong']) ?></div>
                                <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">#<?= (int)$suite['MaCanHo'] ?></span>
                            </div>
                            <div class="suite-type-name"><?= e($suite['TenLoai']) ?> &bull; <?= number_format((float)$suite['DienTich'], 0) ?> m²</div>
                            <div class="suite-location">
                                <?= svgIcon('map-pin', '', 14) ?>
                                <span><?= e($suite['DiaChi'] ?: 'Tòa nhà Căn hộ Dịch vụ') ?></span>
                            </div>
                            <div class="suite-services-row">
                                <div class="suite-svc-item" title="Đơn giá điện theo đồng hồ">
                                    <?= svgIcon('electric', '', 13) ?>
                                    <span>Điện: <strong><?= formatMoney(normalizeServiceFee($suite['GiaDien'] ?? 3800, 'dien')) ?>/kWh</strong></span>
                                </div>
                                <div class="suite-svc-item" title="Tiền nước khoán theo phòng">
                                    <?= svgIcon('water', '', 13) ?>
                                    <span>Nước: <strong><?= formatMoney(normalizeServiceFee($suite['GiaNuoc'] ?? 100000, 'nuoc')) ?>/tháng</strong></span>
                                </div>
                                <div class="suite-svc-item" title="Phí gửi xe máy theo xe">
                                    <?= svgIcon('motorcycle', '', 13) ?>
                                    <span>Xe máy: <strong><?= formatMoney(normalizeServiceFee($suite['GiaXeMay'] ?? 120000, 'xemay')) ?>/xe</strong></span>
                                </div>
                                <div class="suite-svc-item" title="Internet wifi tốc độ cao">
                                    <?= svgIcon('wifi', '', 13) ?>
                                    <span>Wifi: <strong><?= formatMoney(normalizeServiceFee($suite['GiaInternet'] ?? 100000, 'internet')) ?>/tháng</strong></span>
                                </div>
                            </div>
                            <button type="button" class="btn-view-suite" onclick='openSuiteModal(<?= $jsonData ?>)'>
                                <span>Xem chi tiết & biểu phí</span>
                                <?= svgIcon('arrow-right', '', 14) ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================================
         4. QUY TRÌNH VẬN HÀNH 4 BƯỚC (INTERACTIVE WORKFLOW TABS)
         ======================================================================== -->
    <section class="workflow-section" id="workflow">
        <div class="workflow-container">
            <div style="text-align: center; max-width: 720px; margin: 0 auto 3rem;">
                <h2 class="section-title">Quy Trình Quản Lý Căn Hộ</h2>
                <p style="color: #94a3b8; font-size: 1.05rem; margin-top: 0.5rem; font-weight: 500;">
                    Từ Khách Thuê Đến Vận Hành
                </p>
            </div>

            <!-- TABS NAVIGATION -->
            <div class="step-tabs-nav">
                <div class="step-tab-btn active" onclick="showStep(0)">
                    <div class="step-num">BƯỚC 01</div>
                    <div class="step-tab-title">Khách Thuê & Hợp Đồng</div>
                    <div class="step-tab-sub">Lưu thông tin khách thuê và quản lý hợp đồng.</div>
                </div>
                <div class="step-tab-btn" onclick="showStep(1)">
                    <div class="step-num">BƯỚC 02</div>
                    <div class="step-tab-title">Điện, Nước & Dịch Vụ</div>
                    <div class="step-tab-sub">Cập nhật chỉ số và tính chi phí hàng tháng.</div>
                </div>
                <div class="step-tab-btn" onclick="showStep(2)">
                    <div class="step-num">BƯỚC 03</div>
                    <div class="step-tab-title">Hóa Đơn & Thanh Toán</div>
                    <div class="step-tab-sub">Theo dõi các khoản phí và xuất hóa đơn.</div>
                </div>
                <div class="step-tab-btn" onclick="showStep(3)">
                    <div class="step-num">BƯỚC 04</div>
                    <div class="step-tab-title">Bảo Trì & Báo Cáo</div>
                    <div class="step-tab-sub">Theo dõi yêu cầu sửa chữa và tình hình vận hành.</div>
                </div>
            </div>

            <!-- STEP 1 CONTENT -->
            <div class="step-content-pane active" data-step="0">
                <div class="step-pane-info">
                    <h3>Tạo & Quản Lý Hợp Đồng</h3>
                    <p>
                        Quản lý thông tin khách thuê, căn hộ, tiền cọc, thời gian thuê và các dịch vụ đi kèm trên một hệ thống.
                    </p>
                    <ul class="step-check-list">
                        <li><?= svgIcon('check', '', 18) ?> Chọn căn hộ và kiểm tra tình trạng phòng trước khi lưu</li>
                        <li><?= svgIcon('check', '', 18) ?> Tự động lấy thông tin giá thuê và dịch vụ của căn hộ</li>
                        <li><?= svgIcon('check', '', 18) ?> Theo dõi hợp đồng sắp hết hạn trên Dashboard</li>
                    </ul>
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Đăng nhập để lập hợp đồng</span>
                        <?= svgIcon('arrow-right', '', 16) ?>
                    </button>
                </div>
                <div class="step-preview-box">
                    <?php if ($latestContract): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; margin-bottom: 1rem;">
                            <span style="font-weight: 700; color: #ffffff;">HỢP ĐỒNG THUÊ CĂN HỘ #<?= e((string)$latestContract['MaHopDong']) ?></span>
                            <span class="badge badge-success"><?= svgIcon('check', '', 12) ?> <?= e($latestContract['TrangThai']) ?></span>
                        </div>
                        <div style="font-size: 0.9rem; color: #cbd5e1; line-height: 2;">
                            <div>Căn hộ: <strong style="color: #ffffff;">Phòng <?= e($latestContract['SoPhong']) ?> (<?= e($latestContract['TenLoai']) ?>)</strong></div>
                            <div>Khách thuê: <strong style="color: #ffffff;"><?= e($latestContract['TenKhach']) ?></strong></div>
                            <div>Tiền thuê: <strong style="color: #38bdf8;"><?= formatMoney((float)$latestContract['GiaThueThoaThuan']) ?> / tháng</strong></div>
                            <div>Tiền cọc: <strong style="color: #34d399;"><?= formatMoney((float)$latestContract['TienCoc']) ?></strong></div>
                            <div>Thời hạn: <strong style="color: #ffffff;"><?= formatDate($latestContract['NgayBatDau']) ?> &rarr; <?= formatDate($latestContract['NgayKetThuc']) ?></strong></div>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 2rem 1rem;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; color: #64748b; margin-bottom: 0.75rem;">
                                <?= svgIcon('file-text', '', 24) ?>
                            </div>
                            <div style="font-weight: 700; color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">Chưa Có Hợp Đồng Thuê Nào</div>
                            <div style="font-size: 0.85rem; color: #94a3b8; max-width: 280px; line-height: 1.5;">
                                Dữ liệu hợp đồng trong hệ thống đang trống. Khi bạn lập hợp đồng mới, thông tin sẽ tự động hiển thị tại đây.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STEP 2 CONTENT -->
            <div class="step-content-pane" data-step="1">
                <div class="step-pane-info">
                    <h3>Chốt Chỉ Số Điện Nước Thông Minh</h3>
                    <p>
                        Ghi nhận chỉ số công tơ điện hàng tháng nhanh gọn. Hệ thống tự kế thừa số cũ từ kỳ trước, tính lượng tiêu thụ và áp đơn giá điện riêng theo từng tòa nhà.
                    </p>
                    <ul class="step-check-list">
                        <li><?= svgIcon('check', '', 18) ?> Tiền nước tính bằng VNĐ khoán theo tháng (không tính theo m3)</li>
                        <li><?= svgIcon('check', '', 18) ?> Báo lỗi ngay nếu số mới nhỏ hơn số cũ</li>
                        <li><?= svgIcon('check', '', 18) ?> Tự động liên kết phát hành hóa đơn tương ứng</li>
                    </ul>
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Trải nghiệm chốt chỉ số</span>
                        <?= svgIcon('arrow-right', '', 16) ?>
                    </button>
                </div>
                <div class="step-preview-box">
                    <?php if ($latestDienNuoc): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; margin-bottom: 1rem;">
                            <span style="font-weight: 700; color: #ffffff;">CHỐT ĐIỆN NƯỚC KỲ <?= e($latestDienNuoc['ThangNam'] ?? date('m/Y')) ?></span>
                            <span class="badge badge-info"><?= svgIcon('electric', '', 12) ?> Đã ghi nhận</span>
                        </div>
                        <div style="font-size: 0.9rem; color: #cbd5e1; line-height: 2;">
                            <div>Phòng: <strong style="color: #ffffff;">Phòng <?= e($latestDienNuoc['SoPhong']) ?></strong></div>
                            <div>Chỉ số điện cũ: <strong style="color: #94a3b8;"><?= number_format((float)$latestDienNuoc['ChiSoDienCu'], 0) ?> kWh</strong></div>
                            <div>Chỉ số điện mới: <strong style="color: #ffffff;"><?= number_format((float)$latestDienNuoc['ChiSoDienMoi'], 0) ?> kWh</strong> (Tiêu thụ: <?= number_format((float)($latestDienNuoc['ChiSoDienMoi'] - $latestDienNuoc['ChiSoDienCu']), 0) ?> kWh)</div>
                            <div>Tiền điện: <strong style="color: #ffffff;"><?= formatMoney((float)($latestDienNuoc['TienDien'] ?? 0)) ?></strong></div>
                            <div>Tiền nước: <strong style="color: #38bdf8;"><?= formatMoney((float)($latestDienNuoc['TienNuoc'] ?? 0)) ?></strong></div>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 2rem 1rem;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; color: #64748b; margin-bottom: 0.75rem;">
                                <?= svgIcon('electric', '', 24) ?>
                            </div>
                            <div style="font-weight: 700; color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">Chưa Có Kỳ Chốt Điện Nước</div>
                            <div style="font-size: 0.85rem; color: #94a3b8; max-width: 280px; line-height: 1.5;">
                                Dữ liệu chỉ số đang trống. Khi quản trị viên ghi nhận chỉ số kỳ mới, thông tin sẽ hiển thị tại đây.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STEP 3 CONTENT -->
            <div class="step-content-pane" data-step="2">
                <div class="step-pane-info">
                    <h3>Phát Hành Hóa Đơn & Thanh Toán Tức Thì</h3>
                    <p>
                        Tự động tổng hợp tiền phòng, tiền điện, tiền nước và các phụ phí dịch vụ (xe máy, rác, internet) thành 1 hóa đơn hoàn chỉnh chỉ trong 1 click chuột.
                    </p>
                    <ul class="step-check-list">
                        <li><?= svgIcon('check', '', 18) ?> Hỗ trợ in ấn mẫu hóa đơn khổ A4/A5 chuyên nghiệp</li>
                        <li><?= svgIcon('check', '', 18) ?> Ghi nhận thanh toán linh hoạt: Chuyển khoản ngân hàng hoặc tiền mặt</li>
                        <li><?= svgIcon('check', '', 18) ?> Cập nhật trạng thái tức thời vào doanh thu thực thu</li>
                    </ul>
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Xem mẫu hóa đơn chuẩn</span>
                        <?= svgIcon('arrow-right', '', 16) ?>
                    </button>
                </div>
                <div class="step-preview-box">
                    <?php if ($latestHoaDon): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; margin-bottom: 1rem;">
                            <span style="font-weight: 700; color: #ffffff;">HÓA ĐƠN #HD-<?= str_pad((string)$latestHoaDon['MaHoaDon'], 4, '0', STR_PAD_LEFT) ?></span>
                            <span class="badge badge-success"><?= svgIcon('check', '', 12) ?> <?= e($latestHoaDon['TrangThaiThanhToan'] ?? $latestHoaDon['TrangThai'] ?? 'Đã phát hành') ?></span>
                        </div>
                        <div style="font-size: 0.9rem; color: #cbd5e1; line-height: 2;">
                            <div>Phòng / Khách: <strong style="color: #ffffff;">Phòng <?= e($latestHoaDon['SoPhong']) ?> (<?= e($latestHoaDon['TenKhach']) ?>)</strong></div>
                            <div>Kỳ thanh toán: <strong style="color: #ffffff;"><?= e($latestHoaDon['KyThanhToan']) ?></strong></div>
                            <div>Tiền phòng: <strong style="color: #ffffff;"><?= formatMoney((float)($latestHoaDon['TienPhong'] ?? 0)) ?></strong></div>
                            <div>Dịch vụ & Điện nước: <strong style="color: #ffffff;"><?= formatMoney((float)(($latestHoaDon['TienDien'] ?? 0) + ($latestHoaDon['TienNuoc'] ?? 0) + ($latestHoaDon['TienDichVu'] ?? 0))) ?></strong></div>
                            <div style="border-top: 1px dashed rgba(255,255,255,0.2); margin-top: 0.5rem; padding-top: 0.5rem;">
                                TỔNG CỘNG: <strong style="color: #38bdf8; font-size: 1.2rem;"><?= formatMoney((float)$latestHoaDon['TongTien']) ?></strong>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 2rem 1rem;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; color: #64748b; margin-bottom: 0.75rem;">
                                <?= svgIcon('wallet', '', 24) ?>
                            </div>
                            <div style="font-weight: 700; color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">Chưa Có Hóa Đơn Phát Hành</div>
                            <div style="font-size: 0.85rem; color: #94a3b8; max-width: 280px; line-height: 1.5;">
                                Hệ thống chưa tạo hóa đơn nào. Hóa đơn mới phát hành sẽ tự động hiển thị tại đây.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STEP 4 CONTENT -->
            <div class="step-content-pane" data-step="3">
                <div class="step-pane-info">
                    <h3>Bảo Trì 24/7 & Báo Cáo Tài Chính Tổng Thể</h3>
                    <p>
                        Tiếp nhận phản ánh hư hỏng từ khách thuê (máy lạnh, vòi nước, khóa cửa), phân công kỹ thuật viên xử lý và đối soát công nợ toàn hệ thống theo thời gian thực.
                    </p>
                    <ul class="step-check-list">
                        <li><?= svgIcon('check', '', 18) ?> Báo cáo doanh thu trên biểu đồ trực quan theo năm</li>
                        <li><?= svgIcon('check', '', 18) ?> Thống kê danh sách khách nợ tiền phòng quá hạn</li>
                        <li><?= svgIcon('check', '', 18) ?> Xuất file Excel / PDF báo cáo tài chính chỉ trong 1 thao tác</li>
                    </ul>
                    <button type="button" onclick="openLoginModal()" class="btn-vip-primary">
                        <span>Vào xem Báo cáo Dashboard</span>
                        <?= svgIcon('arrow-right', '', 16) ?>
                    </button>
                </div>
                <div class="step-preview-box">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; margin-bottom: 1rem;">
                        <span style="font-weight: 700; color: #ffffff;">TỔNG QUAN HỆ THỐNG VẬN HÀNH</span>
                        <span class="badge badge-info"><?= svgIcon('trend-up', '', 12) ?> Thời gian thực</span>
                    </div>
                    <div style="font-size: 0.9rem; color: #cbd5e1; line-height: 2;">
                        <div>Tổng số căn hộ: <strong style="color: #ffffff;"><?= $totalRooms ?> phòng</strong></div>
                        <div>Tỷ lệ lấp đầy: <strong style="color: #34d399;"><?= $occupancyRate ?>% (<?= $rentedRooms ?>/<?= $totalRooms ?> phòng)</strong></div>
                        <div>Doanh thu tháng này: <strong style="color: #38bdf8;"><?= formatMoney($doanhThuThang) ?></strong></div>
                        <div>Số sự cố bảo trì: <strong style="color: <?= $maintPending > 0 ? '#f59e0b' : '#34d399' ?>;"><?= $maintPending ?> Đang xử lý</strong></div>
                        <div>Công nợ tồn đọng: <strong style="color: <?= $debtCount > 0 ? '#ef4444' : '#94a3b8' ?>;"><?= $debtCount ?> Hóa đơn</strong></div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ========================================================================
         MODAL XEM CHI TIẾT CĂN HỘ THỰC TẾ (ALBUM & BIỂU PHÍ)
         ======================================================================== -->
    <div class="modal-backdrop-vip" id="suiteDetailModal" onclick="if(event.target===this) closeSuiteModal()">
        <div class="modal-vip-dialog" style="max-width: 660px; padding: 1.75rem; max-height: 90vh; overflow-y: auto;">
            <button type="button" class="modal-close-btn" onclick="closeSuiteModal()" title="Đóng">
                <?= svgIcon('x', '', 20) ?>
            </button>

            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="width: 42px; height: 42px; border-radius: 10px; background: #2563eb; display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0;">
                    <?= svgIcon('building', '', 22) ?>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <h3 id="modalSuiteRoom" style="font-size: 1.35rem; font-weight: 800; color: #ffffff; margin: 0;"></h3>
                        <span id="modalSuiteStatus" class="suite-status-pill" style="position: static;"></span>
                    </div>
                    <p id="modalSuiteAddress" style="font-size: 0.825rem; color: #94a3b8; margin: 0.25rem 0 0;"></p>
                </div>
            </div>

            <!-- GALLERY ẢNH THỰC TẾ -->
            <div style="margin-bottom: 1.25rem;">
                <div style="height: 280px; width: 100%; border-radius: 12px; overflow: hidden; background: #000; margin-bottom: 0.5rem; position: relative;">
                    <img id="modalSuiteMainImg" src="" alt="Ảnh căn hộ thực tế" style="width: 100%; height: 100%; object-fit: cover; transition: opacity 0.2s ease;">
                </div>
                <div id="modalSuiteThumbs" style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem;"></div>
            </div>

            <!-- THÔNG SỐ CHÍNH -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="background: rgba(30, 41, 59, 0.6); padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
                    <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase;">Giá thuê niêm yết</div>
                    <div id="modalSuitePrice" style="font-size: 1.15rem; font-weight: 800; color: #38bdf8; margin-top: 0.2rem;"></div>
                </div>
                <div style="background: rgba(30, 41, 59, 0.6); padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
                    <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase;">Loại căn hộ</div>
                    <div id="modalSuiteType" style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-top: 0.2rem;"></div>
                </div>
                <div style="background: rgba(30, 41, 59, 0.6); padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
                    <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase;">Diện tích sử dụng</div>
                    <div id="modalSuiteArea" style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-top: 0.2rem;"></div>
                </div>
            </div>

            <!-- MÔ TẢ TỪ CƠ SỞ DỮ LIỆU -->
            <div style="margin-bottom: 1.25rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.35rem;">Mô tả tiện ích thực tế từ hệ thống</div>
                <div id="modalSuiteDesc" style="font-size: 0.875rem; color: #e2e8f0; line-height: 1.6; background: rgba(15, 23, 42, 0.55); padding: 0.85rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06);"></div>
            </div>

            <!-- BIỂU PHÍ DỊCH VỤ TOÀ NHÀ -->
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                    <?= svgIcon('tool', '', 14) ?>
                    <span>Biểu phí dịch vụ áp dụng thực tế</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; font-size: 0.8rem;">
                    <div style="background: rgba(30, 41, 59, 0.45); padding: 0.6rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);">
                        <span style="color: #94a3b8;">Tiền điện:</span> <strong id="modalSuiteDien" style="color: #38bdf8; display: block;"></strong>
                    </div>
                    <div style="background: rgba(30, 41, 59, 0.45); padding: 0.6rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);">
                        <span style="color: #94a3b8;">Tiền nước:</span> <strong id="modalSuiteNuoc" style="color: #38bdf8; display: block;"></strong>
                    </div>
                    <div style="background: rgba(30, 41, 59, 0.45); padding: 0.6rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);">
                        <span style="color: #94a3b8;">Phí xe máy:</span> <strong id="modalSuiteXeMay" style="color: #ffffff; display: block;"></strong>
                    </div>
                    <div style="background: rgba(30, 41, 59, 0.45); padding: 0.6rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);">
                        <span style="color: #94a3b8;">Phí ô tô:</span> <strong id="modalSuiteOto" style="color: #ffffff; display: block;"></strong>
                    </div>
                    <div style="background: rgba(30, 41, 59, 0.45); padding: 0.6rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);">
                        <span style="color: #94a3b8;">Wifi Internet:</span> <strong id="modalSuiteInternet" style="color: #ffffff; display: block;"></strong>
                    </div>
                    <div style="background: rgba(30, 41, 59, 0.45); padding: 0.6rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.06);">
                        <span style="color: #94a3b8;">Phí vệ sinh:</span> <strong id="modalSuiteVeSinh" style="color: #ffffff; display: block;"></strong>
                    </div>
                </div>
            </div>

            <!-- NÚT THAO TÁC -->
            <div style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn-vip-primary" style="flex: 1; justify-content: center; padding: 0.75rem;" onclick="closeSuiteModal(); openLoginModal();">
                    <span id="modalActionBtnText">Đăng nhập để quản lý căn này</span>
                    <?= svgIcon('arrow-right', '', 16) ?>
                </button>
                <button type="button" class="btn-vip-outline" style="padding: 0.75rem 1.25rem;" onclick="closeSuiteModal()">
                    <span>Đóng</span>
                </button>
            </div>
        </div>
    </div>

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
                               placeholder="VD: admin hoặc nv_an" 
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
                    <li><a href="#suites">Quản lý Căn hộ</a></li>
                    <li><a href="#workflow">Hợp đồng & Cọc</a></li>
                    <li><a href="#workflow">Chốt điện nước VNĐ</a></li>
                    <li><a href="#workflow">Hóa đơn & Doanh thu</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Hỗ Trợ Vận Hành</h4>
                <ul class="footer-links">
                    <li><a href="#workflow">Bảo trì kỹ thuật 24/7</a></li>
                    <li><a href="#workflow">Kiểm soát công nợ</a></li>
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
    // 1. HERO SLIDER LOGIC
    let currentHeroSlide = 0;
    const heroSlides = document.querySelectorAll('.hero-slide');
    const heroDots = document.querySelectorAll('.hero-dot');
    const totalHeroSlides = heroSlides.length;
    let heroTimer;

    function setHeroSlide(index) {
        currentHeroSlide = index;
        heroSlides.forEach((slide, i) => {
            slide.classList.toggle('active', i === index);
        });
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
        heroTimer = setInterval(nextHeroSlide, 6000);
    }

    function resetHeroAutoplay() {
        clearInterval(heroTimer);
        startHeroAutoplay();
    }

    startHeroAutoplay();

    // Hỗ trợ Touch Swipe cho Hero
    let touchStartX = 0;
    let touchEndX = 0;
    const heroWrap = document.getElementById('hero');

    heroWrap.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    heroWrap.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleHeroSwipe();
    }, { passive: true });

    function handleHeroSwipe() {
        if (touchEndX < touchStartX - 50) {
            nextHeroSlide();
            resetHeroAutoplay();
        }
        if (touchEndX > touchStartX + 50) {
            prevHeroSlide();
            resetHeroAutoplay();
        }
    }

    // 2. SUITES HORIZONTAL CAROUSEL SCROLL
    function scrollSuites(offset) {
        const slider = document.getElementById('suitesSlider');
        slider.scrollBy({ left: offset, behavior: 'smooth' });
    }

    // Lọc theo loại căn
    function filterSuites(type, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const cards = document.querySelectorAll('.suite-card');
        cards.forEach(card => {
            const cardType = card.getAttribute('data-type');
            if (type === 'all' || cardType === type) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });

        const slider = document.getElementById('suitesSlider');
        if (slider) slider.scrollTo({ left: 0, behavior: 'smooth' });
    }

    // Modal xem chi tiết căn hộ thực tế
    function openSuiteModal(data) {
        document.getElementById('modalSuiteRoom').textContent = 'Phòng ' + data.room + ' (' + data.type + ')';
        document.getElementById('modalSuiteAddress').textContent = data.address;

        const stEl = document.getElementById('modalSuiteStatus');
        stEl.textContent = data.status;
        stEl.className = 'suite-status-pill ' + data.statusClass;

        document.getElementById('modalSuitePrice').textContent = data.price;
        document.getElementById('modalSuiteType').textContent = data.type;
        document.getElementById('modalSuiteArea').textContent = data.area;
        document.getElementById('modalSuiteDesc').textContent = data.desc;

        document.getElementById('modalSuiteDien').textContent = data.dien;
        document.getElementById('modalSuiteNuoc').textContent = data.nuoc;
        document.getElementById('modalSuiteXeMay').textContent = data.xeMay;
        document.getElementById('modalSuiteOto').textContent = data.oto;
        document.getElementById('modalSuiteInternet').textContent = data.internet;
        document.getElementById('modalSuiteVeSinh').textContent = data.veSinh;

        const mainImg = document.getElementById('modalSuiteMainImg');
        const thumbs = document.getElementById('modalSuiteThumbs');
        thumbs.innerHTML = '';

        if (data.images && data.images.length > 0) {
            mainImg.src = data.images[0];
            data.images.forEach((imgSrc, idx) => {
                const th = document.createElement('img');
                th.src = imgSrc;
                th.alt = 'Ảnh thu nhỏ ' + (idx + 1);
                th.style.width = '64px';
                th.style.height = '48px';
                th.style.objectFit = 'cover';
                th.style.borderRadius = '6px';
                th.style.cursor = 'pointer';
                th.style.flexShrink = '0';
                th.style.border = (idx === 0) ? '2px solid #38bdf8' : '2px solid rgba(255,255,255,0.1)';
                th.onclick = function() {
                    mainImg.src = imgSrc;
                    thumbs.querySelectorAll('img').forEach(im => im.style.borderColor = 'rgba(255,255,255,0.1)');
                    th.style.borderColor = '#38bdf8';
                };
                thumbs.appendChild(th);
            });
        }

        const actionText = (data.status === 'Trống sẵn sàng') ? 'Đăng nhập để lập hợp đồng thuê' : 'Đăng nhập để quản lý căn này';
        document.getElementById('modalActionBtnText').textContent = actionText;

        document.getElementById('suiteDetailModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeSuiteModal() {
        document.getElementById('suiteDetailModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // 3. WORKFLOW TABS
    function showStep(index) {
        document.querySelectorAll('.step-tab-btn').forEach((btn, i) => {
            btn.classList.toggle('active', i === index);
        });
        document.querySelectorAll('.step-content-pane').forEach((pane, i) => {
            pane.classList.toggle('active', i === index);
        });
    }

    // 4. QUICK LOGIN MODAL LOGIC
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
            closeSuiteModal();
        }
    });
    </script>

</body>
</html>
