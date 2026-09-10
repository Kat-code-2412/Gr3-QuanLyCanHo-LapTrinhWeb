<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['MaKhach'])) {
    redirect('/khach-hang/index.php');
}

$error = '';
$phone = '';
$cccd = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim((string)($_POST['phone'] ?? ''));
    $cccd = trim((string)($_POST['cccd'] ?? ''));

    if ($phone === '' || $cccd === '') {
        $error = 'Vui lòng nhập số điện thoại và CCCD.';
    } else {
        try {
            $pdo = require __DIR__ . '/../config/database.php';
            $stmt = $pdo->prepare('SELECT MaKhach, HoTen FROM KhachThue WHERE SoDienThoai = ? AND CCCD = ? LIMIT 1');
            $stmt->execute([$phone, $cccd]);
            $customer = $stmt->fetch();

            if ($customer) {
                session_regenerate_id(true);
                unset($_SESSION['MaNV'], $_SESSION['VaiTro'], $_SESSION['HoTen']);
                $_SESSION['MaKhach'] = (int)$customer['MaKhach'];
                $_SESSION['HoTenKhach'] = $customer['HoTen'];
                $invoiceStmt = $pdo->prepare('SELECT hd.MaHoaDon FROM HoaDon hd JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong WHERE hp.MaKhach = ? ORDER BY (hd.TrangThai = "Chưa TT") DESC, hd.MaHoaDon DESC LIMIT 1');
                $invoiceStmt->execute([(int)$customer['MaKhach']]);
                $invoiceId = (int)$invoiceStmt->fetchColumn();
                redirect($invoiceId > 0 ? '/khach-hang/hoa-don.php?id=' . $invoiceId : '/khach-hang/index.php');
            }

            $error = 'Số điện thoại hoặc CCCD không chính xác.';
        } catch (Throwable $exception) {
            $error = 'Không thể đăng nhập lúc này: ' . $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khách hàng đăng nhập</title>
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
</head>
<body>
<div class="login-card" style="margin: 8vh auto;">
    <div class="login-header">
        <h2>Khách hàng đăng nhập</h2>
        <p>Xem hóa đơn và thanh toán bằng QR</p>
    </div>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.25rem;"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group mb-2">
            <label for="phone">Số điện thoại</label>
            <input id="phone" name="phone" class="form-control" value="<?= e($phone) ?>" required autofocus>
        </div>
        <div class="form-group mb-3">
            <label for="cccd">CCCD</label>
            <input id="cccd" name="cccd" class="form-control" value="<?= e($cccd) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Đăng nhập</button>
    </form>
    <div class="login-hint">
        Nhập đúng số điện thoại và CCCD đã đăng ký trong hồ sơ khách thuê.
    </div>
</div>
</body>
</html>
