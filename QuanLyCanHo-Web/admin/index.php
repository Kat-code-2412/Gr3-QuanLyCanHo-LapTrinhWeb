<?php

declare(strict_types=1);

$title = 'Dashboard Admin - Quản lý Căn dịch vụ';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../config/database.php';

// Thống kê nhanh
$countKhach = (int)$pdo->query('SELECT COUNT(*) FROM KhachThue')->fetchColumn();
$countBaoTri = (int)$pdo->query('SELECT COUNT(*) FROM YeuCauBaoTri WHERE TrangThai <> "Hoàn thành"')->fetchColumn();
$countCanHo = (int)$pdo->query('SELECT COUNT(*) FROM CanHo')->fetchColumn();
$countHopDong = (int)$pdo->query('SELECT COUNT(*) FROM HopDong WHERE TrangThai = "Đang hiệu lực"')->fetchColumn();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Tổng Quan (Admin)</h1>
        <p class="page-subtitle">Xin chào <strong><?= e($_SESSION['HoTen'] ?? 'Admin') ?></strong>, chúc bạn một ngày làm việc hiệu quả!</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 2rem;">
    <div class="detail-item" style="border-left: 4px solid var(--primary-color);">
        <div class="detail-label">Khách thuê trong hệ thống</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--primary-color);">
            <?= $countKhach ?>
        </div>
        <a href="/admin/khach-thue/index.php" style="font-size: 0.85rem; font-weight: 500;">Xem danh sách →</a>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--warning-color);">
        <div class="detail-label">Bảo trì đang chờ/xử lý</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--warning-color);">
            <?= $countBaoTri ?>
        </div>
        <a href="/admin/bao-tri/index.php" style="font-size: 0.85rem; font-weight: 500;">Quản lý bảo trì →</a>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--success-color);">
        <div class="detail-label">Hợp đồng đang hiệu lực</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--success-color);">
            <?= $countHopDong ?>
        </div>
        <span style="font-size: 0.85rem; color: var(--text-muted);">Tổng số căn: <?= $countCanHo ?></span>
    </div>
</div>

<!-- Truy cập nhanh 2 Module chính -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <div class="card">
        <div class="card-header">
            <h3>👤 Quản Lý Khách Thuê</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                Quản lý danh sách hồ sơ khách thuê, tìm kiếm theo Tên / CCCD / SĐT, xem lịch sử hợp đồng và yêu cầu bảo trì.
            </p>
            <div style="display: flex; gap: 0.5rem;">
                <a href="/admin/khach-thue/index.php" class="btn btn-primary">Xem danh sách</a>
                <a href="/admin/khach-thue/create.php" class="btn btn-outline">+ Thêm khách mới</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🛠️ Quản Lý Bảo Trì</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                Tiếp nhận sự cố sửa chữa từ khách thuê, phân công tiến độ, cập nhật chi phí và xác nhận hoàn thành.
            </p>
            <div style="display: flex; gap: 0.5rem;">
                <a href="/admin/bao-tri/index.php" class="btn btn-primary">Xem danh sách bảo trì</a>
                <a href="/admin/bao-tri/create.php" class="btn btn-outline">+ Tạo yêu cầu mới</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
