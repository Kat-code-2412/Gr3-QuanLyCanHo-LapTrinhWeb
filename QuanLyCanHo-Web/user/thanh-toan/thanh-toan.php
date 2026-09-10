<?php

declare(strict_types=1);

$title = 'Thanh toán hóa đơn';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Không tìm thấy hóa đơn cần thanh toán.');
    redirect($baseUrl . '/index.php');
}

$stmt = $pdo->prepare('SELECT hd.*, hp.MaHopDong, ch.SoPhong, kt.HoTen AS TenKhach FROM HoaDon hd JOIN HopDong hp ON hd.MaHopDong = hp.MaHopDong JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach WHERE hd.MaHoaDon = ?');
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('error', 'Hóa đơn không tồn tại.');
    redirect($baseUrl . '/index.php');
}

$qrBankId = $_ENV['QR_BANK_ID'] ?? '';
$qrBankAccount = $_ENV['QR_BANK_ACCOUNT'] ?? '';
$qrAccountName = $_ENV['QR_ACCOUNT_NAME'] ?? '';
$qrReady = $qrBankId !== '' && $qrBankAccount !== '' && $qrAccountName !== '';
$qrInfo = 'Thanh toan HD ' . $invoice['MaHoaDon'] . ' - ' . $invoice['TenKhach'];
$qrUrl = $qrReady
    ? 'https://img.vietqr.io/image/' . rawurlencode($qrBankId) . '-' . rawurlencode($qrBankAccount) . '-compact2.png?amount=' . (int)$invoice['TongTien'] . '&addInfo=' . rawurlencode($qrInfo) . '&accountName=' . rawurlencode($qrAccountName)
    : '';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maHoaDon = (int)($_POST['maHoaDon'] ?? 0);
    $soTien = (float)($_POST['soTien'] ?? 0);
    $hinhThuc = trim((string)($_POST['hinhThuc'] ?? ''));

    if ($maHoaDon <= 0) {
        $error = 'Mã hóa đơn không hợp lệ.';
    } elseif ($soTien <= 0) {
        $error = 'Số tiền thanh toán phải lớn hơn 0.';
    } elseif (!in_array($hinhThuc, ['Tiền mặt', 'Chuyển khoản'], true)) {
        $error = 'Hình thức thanh toán không hợp lệ.';
    } else {
        try {
            $call = $pdo->prepare('CALL SP_ThanhToanHoaDon(:maHoaDon, :soTien, :hinhThuc)');
            $call->execute([
                ':maHoaDon' => $maHoaDon,
                ':soTien' => $soTien,
                ':hinhThuc' => $hinhThuc,
            ]);

            setFlash('success', 'Thanh toán hóa đơn thành công.');
            redirect($baseUrl . '/detail.php?id=' . $maHoaDon);
        } catch (Throwable $e) {
            $error = 'Lỗi thanh toán: ' . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Thanh toán hóa đơn #<?= e((string)$invoice['MaHoaDon']) ?></h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/detail.php?id=<?= (int)$invoice['MaHoaDon'] ?>" class="btn btn-outline">← Chi tiết</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span class="alert-icon">✕</span>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 620px;">
    <div class="card-header">
        <h3>Thông tin thanh toán</h3>
    </div>
    <div class="card-body">
        <p><strong>Khách thuê:</strong> <?= e($invoice['TenKhach']) ?></p>
        <p><strong>Phòng:</strong> <?= e($invoice['SoPhong']) ?></p>
        <p><strong>Kỳ:</strong> <?= e($invoice['KyThanhToan']) ?></p>
        <p><strong>Tổng tiền:</strong> <span style="font-weight: 700; color: var(--primary-color);"><?= formatMoney($invoice['TongTien']) ?></span></p>

        <form method="POST">
            <input type="hidden" name="maHoaDon" value="<?= (int)$invoice['MaHoaDon'] ?>">

            <div class="form-group">
                <label for="soTien" style="font-weight: 600;">Số tiền thanh toán</label>
                <input type="number" id="soTien" name="soTien" class="form-control" step="1000" min="0" value="<?= e((string)(int)$invoice['TongTien']) ?>" required>
            </div>

            <div class="form-group">
                <label for="hinhThuc" style="font-weight: 600;">Hình thức thanh toán</label>
                <select id="hinhThuc" name="hinhThuc" class="form-control" required onchange="toggleTransferQr()">
                    <option value="Tiền mặt">Tiền mặt</option>
                    <option value="Chuyển khoản">Chuyển khoản</option>
                </select>
            </div>

            <div id="transferQr" style="display: none; margin-bottom: 1.25rem; padding: 1rem; text-align: center; background: #f8fafc; border: 1px solid #dbeafe; border-radius: 8px;">
                <?php if ($qrReady): ?>
                    <p style="margin-top: 0; font-weight: 600;">Quét mã QR để thanh toán</p>
                    <img id="qrImage" src="<?= e($qrUrl) ?>" alt="Mã QR thanh toán" style="width: min(100%, 280px); height: auto;">
                    <p style="margin-bottom: 0; color: #475569;">Nội dung: <?= e($qrInfo) ?></p>
                <?php else: ?>
                    <p style="margin: 0; color: #b45309;">Chưa cấu hình tài khoản ngân hàng để tạo mã QR.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Xác nhận thanh toán</button>
        </form>
    </div>
</div>

<script>
function toggleTransferQr() {
    const method = document.getElementById('hinhThuc').value;
    document.getElementById('transferQr').style.display = method === 'Chuyển khoản' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
