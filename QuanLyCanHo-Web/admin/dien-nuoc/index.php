<?php

declare(strict_types=1);

$title = 'Quản Lý Chỉ Số Điện Nước Hàng Tháng';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$pdo = require __DIR__ . '/../../config/database.php';
$baseUrl = url('/admin/dien-nuoc');

// Tháng năm mặc định (Định dạng YYYY-MM)
$selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$selectedAddress = trim((string)($_GET['address'] ?? ''));

// Lấy danh sách địa chỉ tòa nhà để lọc (theo phân quyền)
$staffAssigned = getStaffAssignedBuildings();
if ($staffAssigned !== null) {
    $addresses = $staffAssigned;
    if ($selectedAddress !== '' && !in_array($selectedAddress, $staffAssigned, true)) {
        $selectedAddress = '';
    }
} else {
    $addresses = $pdo->query("SELECT DISTINCT DiaChi FROM CanHo WHERE DiaChi IS NOT NULL AND DiaChi != '' ORDER BY DiaChi ASC")->fetchAll(PDO::FETCH_COLUMN);
}

// Xử lý chốt chỉ số điện nước & tự động tạo hóa đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_meter') {
    verifyCsrf();
    
    $maCanHo = (int)($_POST['ma_can_ho'] ?? 0);
    $thangNam = trim((string)($_POST['thang_nam'] ?? $selectedMonth));
    $dienCu = max(0, (int)($_POST['dien_cu'] ?? 0));
    $dienMoi = max(0, (int)($_POST['dien_moi'] ?? 0));
    $tienNuocPost = isset($_POST['tien_nuoc']) ? normalizeServiceFee(str_replace('.', '', $_POST['tien_nuoc']), 'nuoc') : 100000;
    $ghiChu = trim((string)($_POST['ghi_chu'] ?? ''));
    $createInvoice = !empty($_POST['create_invoice']);
    $chkBld = $pdo->prepare('SELECT DiaChi FROM CanHo WHERE MaCanHo = ?');
    $chkBld->execute([$maCanHo]);
    $aptDiaChi = (string)($chkBld->fetchColumn() ?: '');
    if (!isStaffAssignedBuilding($aptDiaChi)) {
        setFlash('error', 'Bạn không có quyền cập nhật điện nước cho phòng thuộc tòa nhà này.');
        redirect('/admin/dien-nuoc/index.php?month=' . urlencode($thangNam) . '&address=' . urlencode($selectedAddress));
    }

    if ($dienMoi < $dienCu) {
        setFlash('error', 'Chỉ số điện mới (' . $dienMoi . ') không được nhỏ hơn chỉ số cũ (' . $dienCu . ').');
        redirect('/admin/dien-nuoc/index.php?month=' . urlencode($thangNam) . '&address=' . urlencode($selectedAddress));
    }

    try {
        $pdo->beginTransaction();

        // Kiểm tra xem phòng này đã chốt số trước đó chưa để hiển thị thông báo phù hợp
        $chkExists = $pdo->prepare("SELECT 1 FROM chisodiennuoc WHERE MaCanHo = ? AND ThangNam = ?");
        $chkExists->execute([$maCanHo, $thangNam]);
        $isUpdate = (bool)$chkExists->fetchColumn();

        // 1. Lưu / Cập nhật chỉ số điện nước (ChiSoNuocMoi lưu số tiền nước theo tháng VNĐ)
        $stmtUpsert = $pdo->prepare("
            INSERT INTO chisodiennuoc (MaCanHo, ThangNam, ChiSoDienCu, ChiSoDienMoi, ChiSoNuocCu, ChiSoNuocMoi, NgayGhi, NguoiGhi, GhiChu)
            VALUES (?, ?, ?, ?, 0, ?, CURDATE(), ?, ?)
            ON DUPLICATE KEY UPDATE
                ChiSoDienCu = VALUES(ChiSoDienCu),
                ChiSoDienMoi = VALUES(ChiSoDienMoi),
                ChiSoNuocMoi = VALUES(ChiSoNuocMoi),
                NgayGhi = CURDATE(),
                NguoiGhi = VALUES(NguoiGhi),
                GhiChu = VALUES(GhiChu)
        ");
        $stmtUpsert->execute([
            $maCanHo,
            $thangNam,
            $dienCu,
            $dienMoi,
            (int)$tienNuocPost,
            $_SESSION['MaNV'] ?? null,
            $ghiChu
        ]);

        $dienTieuThu = $dienMoi - $dienCu;

        // Lấy số phòng để log
        $soPhong = $pdo->query("SELECT SoPhong FROM CanHo WHERE MaCanHo = {$maCanHo}")->fetchColumn() ?: (string)$maCanHo;

        // 2. Nối tiếp tự động tạo/cập nhật hóa đơn nếu có tích chọn
        if ($createInvoice) {
            // Lấy hợp đồng active của căn hộ
            $stmtHd = $pdo->prepare("
                SELECT * FROM HopDong 
                WHERE MaCanHo = ? AND TrangThai = 'Đang hiệu lực' 
                ORDER BY MaHopDong DESC LIMIT 1
            ");
            $stmtHd->execute([$maCanHo]);
            $hopDong = $stmtHd->fetch();

            if ($hopDong) {
                $donGiaDien = normalizeServiceFee($hopDong['GiaDien'] ?? 3800, 'dien');
                $donGiaNuoc = normalizeServiceFee($hopDong['GiaNuoc'] ?? 100000, 'nuoc');
                $giaThue = (float)$hopDong['GiaThueThoaThuan'];
                $phiDichVu = (normalizeServiceFee($hopDong['GiaXeMay'] ?? 0, 'xemay') * (int)($hopDong['SoXeMay'] ?? 0))
                           + (normalizeServiceFee($hopDong['GiaOto'] ?? 0, 'oto') * (int)($hopDong['SoOto'] ?? 0))
                           + normalizeServiceFee($hopDong['GiaInternet'] ?? 0, 'internet')
                           + normalizeServiceFee($hopDong['GiaVeSinh'] ?? 0, 'vesinh');

                $tienDien = $dienTieuThu * $donGiaDien;
                $tienNuoc = $tienNuocPost > 0 ? $tienNuocPost : $donGiaNuoc;
                $tongTien = $giaThue + $tienDien + $tienNuoc + $phiDichVu;

                // Kỳ thanh toán: MM/YYYY
                [$yy, $mm] = explode('-', $thangNam);
                $kyThanhToan = $mm . '/' . $yy;

                // Kiểm tra xem hóa đơn tháng này đã tồn tại chưa
                $stmtCheckInv = $pdo->prepare("
                    SELECT MaHoaDon FROM HoaDon 
                    WHERE MaHopDong = ? AND (KyThanhToan = ? OR KyThanhToan = ?)
                ");
                $stmtCheckInv->execute([$hopDong['MaHopDong'], $kyThanhToan, $thangNam]);
                $existingInvId = $stmtCheckInv->fetchColumn();

                if ($existingInvId) {
                    // Cập nhật hóa đơn để đồng bộ tiền điện nước mới nhất
                    $stmtUpInv = $pdo->prepare("
                        UPDATE HoaDon 
                        SET TienDien = ?, TienNuoc = ?, TongTien = TienThue + TienDichVu + ? + ?
                        WHERE MaHoaDon = ?
                    ");
                    $stmtUpInv->execute([
                        $tienDien, $tienNuoc,
                        $tienDien, $tienNuoc, $existingInvId
                    ]);
                } else {
                    // Insert hóa đơn mới
                    $stmtInsInv = $pdo->prepare("
                        INSERT INTO HoaDon (MaHopDong, NgayTao, KyThanhToan, TienThue, TienDien, TienNuoc, TienDichVu, TongTien, TrangThai, TrangThaiThanhToan)
                        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, 'Chưa TT', 'Chưa thanh toán')
                    ");
                    $stmtInsInv->execute([
                        $hopDong['MaHopDong'], $kyThanhToan, $giaThue,
                        $tienDien, $tienNuoc, $phiDichVu, $tongTien
                    ]);
                }
            }
        }

        $pdo->commit();

        logAudit('UPDATE_METER', 'DienNuoc', (string)$maCanHo, "Chốt số điện ({$dienTieuThu} kWh) phòng {$soPhong} tháng {$thangNam}");
        addNotification(null, "Chốt số điện nước tháng {$thangNam}", "Đã cập nhật chỉ số điện nước cho phòng {$soPhong} thành công.", 'DienNuoc', "/admin/dien-nuoc/index.php?month={$thangNam}");

        if ($isUpdate) {
            setFlash('success', 'Cập nhật điện nước phòng ' . e($soPhong) . ' thành công !');
        } else {
            setFlash('success', 'Đã lưu chỉ số điện nước phòng ' . e($soPhong) . ' thành công !');
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', 'Có lỗi xảy ra khi lưu chỉ số: ' . $ex->getMessage());
    }

    redirect('/admin/dien-nuoc/index.php?month=' . urlencode($thangNam) . '&address=' . urlencode($selectedAddress));
}

// Xử lý XÓA chỉ số điện nước để nhập lại từ đầu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_meter') {
    verifyCsrf();
    
    $maCanHo = (int)($_POST['ma_can_ho'] ?? 0);
    $thangNam = trim((string)($_POST['thang_nam'] ?? $selectedMonth));
    $soPhong = $pdo->query("SELECT SoPhong FROM CanHo WHERE MaCanHo = {$maCanHo}")->fetchColumn() ?: (string)$maCanHo;

    $chkBld = $pdo->prepare('SELECT DiaChi FROM CanHo WHERE MaCanHo = ?');
    $chkBld->execute([$maCanHo]);
    $aptDiaChi = (string)($chkBld->fetchColumn() ?: '');
    if (!isStaffAssignedBuilding($aptDiaChi)) {
        setFlash('error', 'Bạn không có quyền xóa chỉ số điện nước phòng thuộc tòa nhà này.');
        redirect('/admin/dien-nuoc/index.php?month=' . urlencode($thangNam) . '&address=' . urlencode($selectedAddress));
    }

    try {
        $pdo->beginTransaction();

        // 1. Xóa bản ghi trong chisodiennuoc
        $stmtDel = $pdo->prepare("DELETE FROM chisodiennuoc WHERE MaCanHo = ? AND ThangNam = ?");
        $stmtDel->execute([$maCanHo, $thangNam]);

        // 2. Tìm hợp đồng và hóa đơn tháng này (nếu có và chưa thanh toán) để trừ tiền điện nước đã chốt
        $stmtHd = $pdo->prepare("
            SELECT MaHopDong FROM HopDong 
            WHERE MaCanHo = ? AND TrangThai = 'Đang hiệu lực' 
            ORDER BY MaHopDong DESC LIMIT 1
        ");
        $stmtHd->execute([$maCanHo]);
        $hopDongId = $stmtHd->fetchColumn();

        if ($hopDongId) {
            [$yy, $mm] = explode('-', $thangNam);
            $kyThanhToan = $mm . '/' . $yy;

            $stmtInv = $pdo->prepare("
                SELECT MaHoaDon, TienThue, TienDichVu, TrangThaiThanhToan 
                FROM HoaDon 
                WHERE MaHopDong = ? AND (KyThanhToan = ? OR KyThanhToan = ?)
            ");
            $stmtInv->execute([$hopDongId, $kyThanhToan, $thangNam]);
            $inv = $stmtInv->fetch();

            if ($inv && $inv['TrangThaiThanhToan'] !== 'Đã thanh toán') {
                $newTotal = (float)$inv['TienThue'] + (float)$inv['TienDichVu'];
                $stmtUp = $pdo->prepare("
                    UPDATE HoaDon 
                    SET TienDien = 0, TienNuoc = 0, TongTien = ? 
                    WHERE MaHoaDon = ?
                ");
                $stmtUp->execute([$newTotal, $inv['MaHoaDon']]);
            }
        }

        $pdo->commit();

        logAudit('DELETE_METER', 'DienNuoc', (string)$maCanHo, "Xóa chốt số điện nước phòng {$soPhong} tháng {$thangNam}");
        setFlash('success', 'Đã xóa chỉ số điện nước thành công !');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', 'Có lỗi xảy ra khi xóa chỉ số: ' . $ex->getMessage());
    }

    redirect('/admin/dien-nuoc/index.php?month=' . urlencode($thangNam) . '&address=' . urlencode($selectedAddress));
}

// Truy vấn danh sách phòng & thông tin chỉ số điện nước tháng chọn
$whereClauses = ["c.TrangThai = 'Đang thuê'"];
$params = [];

if ($selectedAddress !== '') {
    $whereClauses[] = "c.DiaChi = ?";
    $params[] = $selectedAddress;
}

// Giới hạn theo tòa nhà nhân viên quản lý
$bldCond = buildStaffBuildingCondition('c.DiaChi');
if ($bldCond['sql'] !== '1=1') {
    $whereClauses[] = $bldCond['sql'];
    foreach ($bldCond['params'] as $bp) {
        $params[] = $bp;
    }
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

// Tính tháng trước (YYYY-MM)
$prevMonth = date('Y-m', strtotime("{$selectedMonth}-01 -1 month"));

$sql = "
    SELECT 
        c.MaCanHo, c.SoPhong, c.DiaChi, c.GiaThue,
        cs.ChiSoDienCu, cs.ChiSoDienMoi, cs.ChiSoNuocCu, cs.ChiSoNuocMoi, cs.NgayGhi, cs.GhiChu,
        hd.MaHopDong, hd.GiaDien AS DonGiaDien, hd.GiaNuoc AS DonGiaNuoc, 
        (COALESCE(hd.GiaXeMay*hd.SoXeMay,0) + COALESCE(hd.GiaOto*hd.SoOto,0) + COALESCE(hd.GiaInternet,0) + COALESCE(hd.GiaVeSinh,0)) AS PhiDichVu,
        kt.HoTen AS TenKhachThue, kt.SoDienThoai AS SdtKhachThue,
        -- Tự động lấy số mới tháng trước làm số cũ nếu tháng này chưa ghi
        (SELECT cs_prev.ChiSoDienMoi FROM chisodiennuoc cs_prev WHERE cs_prev.MaCanHo = c.MaCanHo AND cs_prev.ThangNam = '{$prevMonth}' LIMIT 1) AS DienMoiThangTruoc,
        (SELECT cs_prev.ChiSoNuocMoi FROM chisodiennuoc cs_prev WHERE cs_prev.MaCanHo = c.MaCanHo AND cs_prev.ThangNam = '{$prevMonth}' LIMIT 1) AS NuocMoiThangTruoc
    FROM CanHo c
    LEFT JOIN chisodiennuoc cs ON c.MaCanHo = cs.MaCanHo AND cs.ThangNam = ?
    LEFT JOIN HopDong hd ON c.MaCanHo = hd.MaCanHo AND hd.TrangThai = 'Đang hiệu lực'
    LEFT JOIN KhachThue kt ON hd.MaKhach = kt.MaKhach
    {$whereSql}
    ORDER BY c.DiaChi ASC, c.SoPhong ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$selectedMonth], $params));
$rooms = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title" style="margin: 0;">
            Quản Lý Chỉ Số Điện & Nước Hàng Tháng
        </h1>
    </div>
</div>

<?php if ($staffAssigned !== null && empty($staffAssigned)): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 0.9rem 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem; color: #92400e;">
        <?= svgIcon('info', '', 20) ?>
        <div style="font-size: 0.9rem;">
            <strong>Lưu ý:</strong> Bạn hiện chưa được chỉ định quản lý tòa nhà nào trong hệ thống, do đó danh sách phòng và chỉ số điện nước sẽ không hiển thị. Vui lòng liên hệ Admin để được phân công tòa nhà.
        </div>
    </div>
<?php endif; ?>

<!-- BỘ LỌC THÁNG VÀ TÒA NHÀ -->
<div class="card mb-3" style="padding: 1.25rem;">
    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label for="month" style="font-size: 0.825rem; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                <?= svgIcon('calendar', '', 14) ?> Tháng chốt số
            </label>
            <input type="month" 
                   id="month" 
                   name="month" 
                   class="form-control" 
                   value="<?= e($selectedMonth) ?>" 
                   onchange="this.form.submit()">
        </div>

        <div style="flex: 2; min-width: 260px;">
            <label for="address" style="font-size: 0.825rem; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                <?= svgIcon('building', '', 14) ?> Tòa nhà / Địa chỉ căn hộ
            </label>
            <select name="address" id="address" class="form-control" onchange="this.form.submit()">
                <?php if ($staffAssigned !== null && empty($staffAssigned)): ?>
                    <option value="">-- Chưa được phân công tòa nhà --</option>
                <?php else: ?>
                    <option value="">-- Tất cả tòa nhà --</option>
                    <?php foreach ($addresses as $addr): ?>
                        <option value="<?= e($addr) ?>" <?= ($selectedAddress === $addr) ? 'selected' : '' ?>>
                            <?= e($addr) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">
                <?= svgIcon('filter', '', 15) ?>
                <span>Lọc danh sách</span>
            </button>
        </div>
    </form>
</div>

<!-- DANH SÁCH PHÒNG CHỐT SỐ -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <?= svgIcon('door', '', 18) ?>
            <span>Danh Sách Phòng Đang Thuê Tháng <?= e(date('m/Y', strtotime($selectedMonth . '-01'))) ?> (<?= count($rooms) ?> phòng)</span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 140px;">Căn hộ</th>
                    <th>Khách đang thuê</th>
                    <th>Đơn giá định mức</th>
                    <th style="width: 170px;">Chỉ số Điện (kWh)</th>
                    <th style="width: 170px;">Tiền Nước (VNĐ/tháng)</th>
                    <th style="width: 190px;">Tổng</th>
                    <th style="text-align: center; width: 185px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($staffAssigned !== null && empty($staffAssigned)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 3.5rem 1rem; color: #64748b;">
                            <div style="margin-bottom: 0.75rem; color: #f59e0b; display: flex; justify-content: center;">
                                <?= svgIcon('building', '', 42) ?>
                            </div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 0.4rem;">
                                Bạn chưa được phân công quản lý tòa nhà nào
                            </div>
                            <div style="font-size: 0.875rem; color: #64748b; max-width: 480px; margin: 0 auto; line-height: 1.5;">
                                Dữ liệu chỉ số điện nước được giới hạn theo các tòa nhà bạn phụ trách. Vui lòng liên hệ Quản trị viên để được phân công tòa nhà quản lý.
                            </div>
                        </td>
                    </tr>
                <?php elseif (empty($rooms)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 3rem 1rem; color: #94a3b8;">
                            Hiện không có phòng nào đang thuê phù hợp với bộ lọc đã chọn.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rooms as $row): ?>
                        <?php
                        $formId = 'meter_form_' . (int)$row['MaCanHo'];
                        $isRecorded = isset($row['ChiSoDienMoi']);
                        $dienCu = $row['ChiSoDienCu'] ?? ($row['DienMoiThangTruoc'] ?? 0);
                        $dienMoi = $row['ChiSoDienMoi'] ?? $dienCu;
                        $dienTT = max(0, $dienMoi - $dienCu);
                        
                        $donGiaDienChuan = normalizeServiceFee($row['DonGiaDien'] ?? 3800, 'dien');
                        $donGiaNuocChuan = normalizeServiceFee($row['DonGiaNuoc'] ?? 100000, 'nuoc');
                        $tienNuocHienThi = ((int)($row['ChiSoNuocMoi'] ?? 0) > 0) ? (float)$row['ChiSoNuocMoi'] : (float)$donGiaNuocChuan;
                        $tienDienUocTinh = $dienTT * $donGiaDienChuan;
                        $tienNuocUocTinh = $tienNuocHienThi;
                        $tongDienNuoc = $tienDienUocTinh + $tienNuocUocTinh;
                        ?>
                        <tr data-meter-row data-don-gia-dien="<?= $donGiaDienChuan ?>">
                            <td>
                                <strong style="font-size: 0.95rem; color: #0f172a;">Phòng <?= e($row['SoPhong']) ?></strong><br>
                                <small style="color: #64748b; font-size: 0.75rem;"><?= e($row['DiaChi']) ?></small>
                            </td>
                            <td>
                                <strong style="color: #1e293b;"><?= e($row['TenKhachThue'] ?: 'Chưa cập nhật') ?></strong><br>
                                <small style="color: #64748b; font-size: 0.75rem;"><?= e($row['SdtKhachThue'] ?: '-') ?></small>
                            </td>
                            <td>
                                <div style="font-size: 0.8rem; color: #475569; display: flex; flex-direction: column; gap: 2px;">
                                    <span style="display: flex; align-items: center; gap: 4px;">
                                        <span style="color: #2563eb;"><?= svgIcon('electric', '', 12) ?></span>
                                        Điện: <strong><?= formatMoney($donGiaDienChuan) ?></strong>/kWh
                                    </span>
                                    <span style="display: flex; align-items: center; gap: 4px;">
                                        <span style="color: #06b6d4;"><?= svgIcon('water', '', 12) ?></span>
                                        Nước: <strong><?= formatMoney($donGiaNuocChuan) ?></strong>/tháng
                                    </span>
                                </div>
                            </td>

                            <!-- FORM POST ĐƯỢC ĐẶT NGOÀI HOẶC LIÊN KẾT FORM ID -->
                            <td>
                                <form id="<?= $formId ?>" method="POST" action="" style="display: none;">
                                    <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="save_meter">
                                    <input type="hidden" name="ma_can_ho" value="<?= (int)$row['MaCanHo'] ?>">
                                    <input type="hidden" name="thang_nam" value="<?= e($selectedMonth) ?>">
                                </form>

                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: #64748b;">
                                        <span style="width: 32px;">Cũ:</span>
                                        <input type="number" form="<?= $formId ?>" name="dien_cu" class="form-control meter-dien-cu" style="padding: 0.25rem 0.5rem; height: 30px; font-size: 0.85rem;" value="<?= (int)$dienCu ?>" required>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 600; color: #0f172a;">
                                        <span style="width: 32px;">Mới:</span>
                                        <input type="number" form="<?= $formId ?>" name="dien_moi" class="form-control meter-dien-moi" style="padding: 0.25rem 0.5rem; height: 30px; font-size: 0.85rem; border-color: #93c5fd; background-color: #ffffff;" value="<?= (int)$dienMoi ?>" required>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <input type="text" form="<?= $formId ?>" name="tien_nuoc" class="form-control currency-mask meter-tien-nuoc" style="padding: 0.25rem 0.5rem; height: 32px; font-size: 0.85rem; border-color: #a5f3fc; background-color: #ffffff; font-weight: 600; text-align: right;" value="<?= number_format($tienNuocHienThi, 0, '', '.') ?>" required>
                                    <small style="color: #64748b; font-size: 0.72rem; text-align: right; display: block;">VNĐ / tháng</small>
                                </div>
                            </td>

                            <td>
                                <div style="display: flex; flex-direction: column; gap: 3px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #475569;">
                                        <span class="calc-label-dien">Điện (<?= $dienTT ?> kWh):</span>
                                        <strong class="calc-val-dien" style="color: #2563eb;"><?= formatMoney($tienDienUocTinh) ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #475569;">
                                        <span>Nước (khoán):</span>
                                        <strong class="calc-val-nuoc" style="color: #0891b2;"><?= formatMoney($tienNuocUocTinh) ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 800; border-top: 1px dashed #cbd5e1; padding-top: 3px; margin-top: 2px; color: #059669;">
                                        <span>Tổng:</span>
                                        <span class="calc-val-tong"><?= formatMoney($tongDienNuoc) ?></span>
                                    </div>
                                    <div style="margin-top: 3px;">
                                        <?php if ($isRecorded): ?>
                                            <span class="badge badge-success" style="font-size: 0.72rem; padding: 2px 8px;">Đã chốt số</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning" style="font-size: 0.72rem; padding: 2px 8px;">Chưa chốt</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td style="text-align: center; vertical-align: middle;">
                                <div style="display: flex; flex-direction: column; gap: 0.45rem; align-items: center; justify-content: center;">
                                    <label style="font-size: 0.75rem; color: #64748b; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; margin: 0;">
                                        <input type="checkbox" form="<?= $formId ?>" name="create_invoice" value="1" checked style="cursor: pointer;">
                                        Cập nhật hóa đơn
                                    </label>
                                    
                                    <div style="display: flex; gap: 0.35rem; align-items: center; justify-content: center;">
                                        <button type="submit" form="<?= $formId ?>" class="btn btn-sm <?= $isRecorded ? 'btn-success' : 'btn-primary' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                                            <?= svgIcon('check', '', 14) ?>
                                            <span><?= $isRecorded ? 'Cập nhật' : 'Lưu chốt số' ?></span>
                                        </button>
                                        <?php if ($isRecorded): ?>
                                            <form id="del_<?= $formId ?>" method="POST" action="" style="display: inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa chỉ số đã chốt của phòng này?');">
                                                <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="delete_meter">
                                                <input type="hidden" name="ma_can_ho" value="<?= (int)$row['MaCanHo'] ?>">
                                                <input type="hidden" name="thang_nam" value="<?= e($selectedMonth) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline" style="padding: 0.4rem 0.55rem; color: #ef4444; border-color: #fecaca; background: #fff;" title="Xóa chỉ số chốt để nhập lại">
                                                    <?= svgIcon('trash', '', 14) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function formatVND(val) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(val)) + ' đ';
}

function updateRowCalc(row) {
    const donGiaDien = parseFloat(row.dataset.donGiaDien) || 0;
    const inputDienCu = row.querySelector('.meter-dien-cu');
    const inputDienMoi = row.querySelector('.meter-dien-moi');
    const inputTienNuoc = row.querySelector('.meter-tien-nuoc');

    const dienCu = parseInt(inputDienCu ? inputDienCu.value : 0, 10) || 0;
    const dienMoi = parseInt(inputDienMoi ? inputDienMoi.value : 0, 10) || 0;
    const rawNuoc = inputTienNuoc ? inputTienNuoc.value.replace(/\D/g, '') : '';
    const tienNuoc = parseInt(rawNuoc, 10) || 0;

    const dienTT = Math.max(0, dienMoi - dienCu);
    const tienDien = dienTT * donGiaDien;
    const tong = tienDien + tienNuoc;

    const elLabelDien = row.querySelector('.calc-label-dien');
    const elValDien = row.querySelector('.calc-val-dien');
    const elValNuoc = row.querySelector('.calc-val-nuoc');
    const elValTong = row.querySelector('.calc-val-tong');

    if (elLabelDien) elLabelDien.textContent = `Điện (${dienTT} kWh):`;
    if (elValDien) elValDien.textContent = formatVND(tienDien);
    if (elValNuoc) elValNuoc.textContent = formatVND(tienNuoc);
    if (elValTong) elValTong.textContent = formatVND(tong);

    // Cảnh báo nếu điện mới < điện cũ
    if (inputDienMoi) {
        if (dienMoi < dienCu && dienMoi > 0) {
            inputDienMoi.style.borderColor = '#ef4444';
            inputDienMoi.style.backgroundColor = '#fef2f2';
        } else {
            inputDienMoi.style.borderColor = '#93c5fd';
            inputDienMoi.style.backgroundColor = '#ffffff';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('tr[data-meter-row]').forEach(row => {
        const inputs = row.querySelectorAll('.meter-dien-cu, .meter-dien-moi, .meter-tien-nuoc');
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                if (input.classList.contains('currency-mask')) {
                    const raw = input.value.replace(/\D/g, '');
                    input.value = raw ? Number(raw).toLocaleString('vi-VN') : '';
                }
                updateRowCalc(row);
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
