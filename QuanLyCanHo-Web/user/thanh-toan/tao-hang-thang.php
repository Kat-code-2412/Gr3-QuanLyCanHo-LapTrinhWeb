<?php

declare(strict_types=1);

$title = 'Tạo hóa đơn hàng tháng';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/guard.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url(currentUserRole() === 'Admin' ? '/admin/hoa-don' : '/user/thanh-toan');

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kyThanhToan = trim((string)($_POST['ky_thanh_toan'] ?? ''));

    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{4}$/', $kyThanhToan)) {
        $error = 'Kỳ thanh toán không hợp lệ. Ví dụ đúng: 08/2026';
    } else {
        try {
            $parts = explode('/', $kyThanhToan);
            $thang = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $nam = $parts[1];
            $startOfMonth = "$nam-$thang-01";
            $endOfMonth = date('Y-m-t', strtotime($startOfMonth));
            $thangNamStr = "$nam-$thang";

            $pdo->beginTransaction();

            // Chỉ lấy các hợp đồng đang hiệu lực VÀ thời hạn hợp đồng bao gồm kỳ thanh toán này
            // Khách hết hạn trước tháng này (ví dụ hết hạn tháng 9 khi tạo kỳ tháng 10) sẽ bị loại trừ
            $bldCond = buildStaffBuildingCondition('ch.DiaChi');
            $stmtContracts = $pdo->prepare("
                SELECT hp.*, ch.SoPhong, ch.DiaChi, kt.HoTen AS TenKhach
                FROM HopDong hp
                JOIN CanHo ch ON hp.MaCanHo = ch.MaCanHo
                JOIN KhachThue kt ON hp.MaKhach = kt.MaKhach
                WHERE hp.TrangThai = 'Đang hiệu lực'
                  AND hp.NgayBatDau <= ?
                  AND (hp.NgayKetThuc IS NULL OR hp.NgayKetThuc >= ?)
                  AND {$bldCond['sql']}
                ORDER BY ch.SoPhong ASC
            ");
            $stmtContracts->execute(array_merge([$endOfMonth, $startOfMonth], $bldCond['params']));
            $contracts = $stmtContracts->fetchAll();

            $createdCount = 0;
            $skippedCount = 0;

            foreach ($contracts as $hd) {
                // Kiểm tra xem hóa đơn của hợp đồng này trong kỳ này đã tạo chưa
                $chk = $pdo->prepare("SELECT COUNT(*) FROM HoaDon WHERE MaHopDong = ? AND KyThanhToan = ?");
                $chk->execute([$hd['MaHopDong'], $kyThanhToan]);
                if ((int)$chk->fetchColumn() > 0) {
                    $skippedCount++;
                    continue;
                }

                // Lấy chỉ số điện nước khớp đúng kỳ ThangNam (hoặc chỉ số mới nhất nếu chưa nhập kỳ này)
                $csStmt = $pdo->prepare("SELECT * FROM chisodiennuoc WHERE MaCanHo = ? AND ThangNam = ? LIMIT 1");
                $csStmt->execute([$hd['MaCanHo'], $thangNamStr]);
                $cs = $csStmt->fetch();

                if (!$cs) {
                    $csStmt = $pdo->prepare("SELECT * FROM chisodiennuoc WHERE MaCanHo = ? ORDER BY MaChiSo DESC LIMIT 1");
                    $csStmt->execute([$hd['MaCanHo']]);
                    $cs = $csStmt->fetch();
                }

                $dienTT = $cs ? max(0, (int)$cs['ChiSoDienMoi'] - (int)$cs['ChiSoDienCu']) : 0;
                $tienDien = $dienTT * normalizeServiceFee($hd['GiaDien'] ?? 3800, 'dien');
                $tienNuoc = normalizeServiceFee($hd['GiaNuoc'] ?? 100000, 'nuoc');
                $tienDichVu = (normalizeServiceFee($hd['GiaXeMay'] ?? 120000, 'xemay') * (int)($hd['SoXeMay'] ?? 0)) 
                            + (normalizeServiceFee($hd['GiaOto'] ?? 1200000, 'oto') * (int)($hd['SoOto'] ?? 0)) 
                            + normalizeServiceFee($hd['GiaInternet'] ?? 100000, 'internet') 
                            + normalizeServiceFee($hd['GiaVeSinh'] ?? 50000, 'vesinh');
                $tienThue = (float)$hd['GiaThueThoaThuan'];
                $tongTien = $tienThue + $tienDien + $tienNuoc + $tienDichVu;

                $ins = $pdo->prepare("INSERT INTO HoaDon (MaHopDong, NgayTao, KyThanhToan, TienThue, TienDien, TienNuoc, TienDichVu, TongTien, TrangThai, TrangThaiThanhToan)
                                      VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, 'Chưa TT', 'Chưa thanh toán')");
                $ins->execute([$hd['MaHopDong'], $kyThanhToan, $tienThue, $tienDien, $tienNuoc, $tienDichVu, $tongTien]);
                $createdCount++;
            }
            $pdo->commit();

            logAudit('CREATE_INVOICES', 'HoaDon', null, "Tạo hàng loạt hóa đơn kỳ $kyThanhToan: thành công $createdCount, bỏ qua $skippedCount");

            if ($createdCount === 0 && $skippedCount > 0) {
                setFlash('warning', "Tất cả $skippedCount hợp đồng trong kỳ $kyThanhToan đều đã có hóa đơn từ trước.");
            } elseif ($createdCount === 0) {
                setFlash('warning', "Không có khách thuê nào có hợp đồng còn hiệu lực trong kỳ $kyThanhToan để tạo hóa đơn.");
            } else {
                $msg = "Đã tạo thành công $createdCount hóa đơn cho khách thuê trong kỳ $kyThanhToan.";
                if ($skippedCount > 0) {
                    $msg .= " ($skippedCount hóa đơn đã có sẵn được giữ nguyên).";
                }
                setFlash('success', $msg);
            }
            redirect($baseUrl . '/index.php?ky_thanh_toan=' . urlencode($kyThanhToan));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Lỗi khi tạo hóa đơn: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tạo hóa đơn hàng tháng</h1>
    </div>
    <div>
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline">← Quay lại</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span class="alert-icon"><?= svgIcon('alert-triangle', '', 16) ?></span>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Tạo hóa đơn theo kỳ</h3>
    </div>
    <div class="card-body" style="max-width: 500px;">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label for="ky_thanh_toan" style="font-weight: 600;">Kỳ thanh toán</label>
                <input type="text" id="ky_thanh_toan" name="ky_thanh_toan" class="form-control" value="<?= e(date('m/Y')) ?>" placeholder="Ví dụ: 08/2026" required>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Tạo hóa đơn</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
