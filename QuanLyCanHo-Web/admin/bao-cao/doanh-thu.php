<?php

declare(strict_types=1);

$title = 'Báo cáo Doanh thu - Quản lý Căn dịch vụ';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';

// Validate năm chọn - whitelist số nguyên hợp lý
$currentYear = (int)date('Y');
$year = filter_input(INPUT_GET, 'nam', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 2000, 'max_range' => 2100],
]);
if ($year === false || $year === null) {
    $year = $currentYear;
}

// Danh sách năm có dữ liệu, đổ vào dropdown
$years = $pdo->query("SELECT DISTINCT RIGHT(KyThanhToan, 4) AS Nam FROM View_DoanhThuTheoThang ORDER BY Nam DESC")
             ->fetchAll(PDO::FETCH_COLUMN);

// Doanh thu theo tháng của năm đã chọn
$stmt = $pdo->prepare("SELECT KyThanhToan, TongDoanhThu FROM View_DoanhThuTheoThang
                        WHERE RIGHT(KyThanhToan, 4) = :year
                        ORDER BY STR_TO_DATE(CONCAT('01/', KyThanhToan), '%d/%m/%Y') ASC");
$stmt->execute(['year' => (string)$year]);
$rows = $stmt->fetchAll();

$labels  = array_column($rows, 'KyThanhToan');
$data    = array_map('floatval', array_column($rows, 'TongDoanhThu'));
$tongNam = array_sum($data);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Báo cáo Doanh thu theo tháng</h1>
    </div>
</div>

<form method="get" class="form-group" style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1.5rem;">
    <label for="nam" style="font-weight: 500; margin: 0;">Chọn năm:</label>
    <select name="nam" id="nam" class="form-control" style="width: auto;" onchange="this.form.submit()">
        <?php if (empty($years)): ?>
            <option value="<?= $currentYear ?>"><?= $currentYear ?></option>
        <?php else: ?>
            <?php foreach ($years as $y): ?>
                <option value="<?= e($y) ?>" <?= ((int)$y === $year) ? 'selected' : '' ?>><?= e($y) ?></option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
    <span style="color: var(--text-muted);">Tổng năm <?= $year ?>: <strong><?= formatMoney($tongNam) ?></strong></span>
</form>

<div class="card">
    <div class="card-body">
        <?php if (empty($rows)): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                Chưa có hóa đơn đã thanh toán nào trong năm <?= $year ?>.
            </p>
        <?php else: ?>
            <canvas id="chartDoanhThu" height="90"></canvas>
        <?php endif; ?>
        <p id="chartError" style="display:none; color: var(--danger-color); text-align:center;">
            Không tải được biểu đồ. Vui lòng tải lại trang.
        </p>
    </div>
</div>

<?php if (!empty($rows)): ?>
<script>
try {
    const ctx = document.getElementById('chartDoanhThu').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Doanh thu (đ)',
                data: <?= json_encode($data) ?>,
                backgroundColor: '#2563eb'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (item) => new Intl.NumberFormat('vi-VN').format(item.raw) + ' đ'
                    }
                }
            },
            scales: {
                y: { ticks: { callback: (value) => new Intl.NumberFormat('vi-VN').format(value) } }
            }
        }
    });
} catch (e) {
    document.getElementById('chartDoanhThu').style.display = 'none';
    document.getElementById('chartError').style.display = 'block';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>