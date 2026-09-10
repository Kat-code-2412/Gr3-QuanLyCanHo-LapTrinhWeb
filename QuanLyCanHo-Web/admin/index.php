<?php

declare(strict_types=1);

$title = 'Dashboard Admin - Quản lý Căn dịch vụ';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../config/database.php';

// 4 KPI theo PROJECT.md mục 7
$countCanHo        = (int)$pdo->query('SELECT COUNT(*) FROM CanHo')->fetchColumn();
$countCanDangThue  = (int)$pdo->query("SELECT COUNT(*) FROM CanHo WHERE TrangThai = 'Đang thuê'")->fetchColumn();
$countHoaDonChuaTT = (int)$pdo->query('SELECT COUNT(*) FROM View_HoaDonChuaThanhToan')->fetchColumn();
$countBaoTri       = (int)$pdo->query("SELECT COUNT(*) FROM YeuCauBaoTri WHERE TrangThai <> 'Hoàn thành'")->fetchColumn();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Tổng Quan (Admin)</h1>
    </div>
</div>

<div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 2rem;">
    <div class="detail-item" style="border-left: 4px solid var(--primary-color);">
        <div class="detail-label">Tổng số căn hộ</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--primary-color);">
            <?= $countCanHo ?>
        </div>
        <a href="<?= url('/admin/can-ho/index.php') ?>" style="font-size: 0.85rem; font-weight: 500;">Xem danh sách →</a>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--success-color);">
        <div class="detail-label">Căn đang thuê</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--success-color);">
            <?= $countCanDangThue ?>
        </div>
        <span style="font-size: 0.85rem; color: var(--text-muted);">/ <?= $countCanHo ?> căn</span>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--danger-color);">
        <div class="detail-label">Hóa đơn chưa thanh toán</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--danger-color);">
            <?= $countHoaDonChuaTT ?>
        </div>
        <a href="<?= url('/admin/hoa-don/index.php') ?>" style="font-size: 0.85rem; font-weight: 500;">Xem công nợ →</a>
    </div>

    <div class="detail-item" style="border-left: 4px solid var(--warning-color);">
        <div class="detail-label">Bảo trì đang chờ/xử lý</div>
        <div class="detail-value" style="font-size: 1.8rem; font-weight: 700; color: var(--warning-color);">
            <?= $countBaoTri ?>
        </div>
        <a href="<?= url('/admin/bao-tri/index.php') ?>" style="font-size: 0.85rem; font-weight: 500;">Quản lý bảo trì →</a>
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
                Quản lý danh sách hồ sơ khách thuê, tìm kiếm theo Tên / CCCD / SĐT và xem yêu cầu bảo trì.
            </p>
            <div style="display: flex; gap: 0.5rem;">
                <a href="<?= url('/admin/khach-thue/index.php') ?>" class="btn btn-primary">Xem danh sách</a>
                <a href="<?= url('/admin/khach-thue/create.php') ?>" class="btn btn-outline">+ Thêm khách mới</a>
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
                <a href="<?= url('/admin/bao-tri/index.php') ?>" class="btn btn-primary">Xem danh sách bảo trì</a>
                <a href="<?= url('/admin/bao-tri/create.php') ?>" class="btn btn-outline">+ Tạo yêu cầu mới</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>