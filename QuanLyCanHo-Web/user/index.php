<?php

declare(strict_types=1);

$title = 'Dashboard Nhân viên - Quản lý Căn dịch vụ';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../config/database.php';

// Thống kê nhanh
$countKhach = (int)$pdo->query('SELECT COUNT(*) FROM KhachThue')->fetchColumn();
$countBaoTri = (int)$pdo->query('SELECT COUNT(*) FROM YeuCauBaoTri WHERE TrangThai <> "Hoàn thành"')->fetchColumn();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Nhân Viên</h1>
        <p class="page-subtitle">Xin chào <strong><?= e($_SESSION['HoTen'] ?? 'Nhân viên') ?></strong>, chào mừng bạn trở lại!</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 2rem;">
    <div class="detail-item" style="border-left: 4px solid var(--primary-color);">
        <div class="detail-label">Tổng số khách thuê</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--primary-color);">
            <?= $countKhach ?>
        </div>
        <a href="<?= url('/user/khach-thue/index.php') ?>" style="font-size: 0.85rem; font-weight: 500;">Tra cứu khách thuê →</a>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--warning-color);">
        <div class="detail-label">Yêu cầu bảo trì chờ xử lý</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--warning-color);">
            <?= $countBaoTri ?>
        </div>
        <a href="<?= url('/user/bao-tri/index.php') ?>" style="font-size: 0.85rem; font-weight: 500;">Xử lý bảo trì →</a>
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
                Xem danh sách khách thuê, tìm kiếm thông tin liên lạc, hỗ trợ cập nhật thông tin và kiểm tra lịch sử thuê.
            </p>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?= url('/user/khach-thue/index.php') ?>" class="btn btn-primary">Danh sách khách thuê</a>
                <a href="<?= url('/user/khach-thue/create.php') ?>" class="btn btn-outline">+ Thêm khách mới</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🛠️ Quản Lý Bảo Trì</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                Ghi nhận sự cố căn hộ từ khách thuê, theo dõi trạng thái xử lý và cập nhật ngày hoàn thành.
            </p>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?= url('/user/bao-tri/index.php') ?>" class="btn btn-primary">Danh sách bảo trì</a>
                <a href="<?= url('/user/bao-tri/create.php') ?>" class="btn btn-outline">+ Tiếp nhận sự cố</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
