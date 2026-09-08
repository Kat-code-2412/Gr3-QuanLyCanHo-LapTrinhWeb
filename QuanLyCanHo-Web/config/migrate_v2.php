<?php

declare(strict_types=1);

$pdo = require __DIR__ . '/database.php';

try {
    // 1. Add DiaChi to CanHo table if missing
    $canHoCols = $pdo->query("SHOW COLUMNS FROM CanHo")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('DiaChi', $canHoCols)) {
        $pdo->exec("ALTER TABLE CanHo ADD COLUMN DiaChi VARCHAR(255) NULL DEFAULT 'Tòa nhà A - 123 Nguyễn Trãi, Q.1'");
    }

    // Update existing rooms with sample realistic addresses
    $pdo->exec("UPDATE CanHo SET DiaChi = 'Tòa nhà A - 123 Nguyễn Trãi, Q.1' WHERE MaCanHo IN (1,2,3,4)");
    $pdo->exec("UPDATE CanHo SET DiaChi = 'Tòa nhà B - 456 Lê Văn Sỹ, Q.3' WHERE MaCanHo IN (5,6,7,8)");
    $pdo->exec("UPDATE CanHo SET DiaChi = 'Tòa nhà C - 789 Điện Biên Phủ, Q.Bình Thạnh' WHERE MaCanHo IN (9,10,11,12)");

    // 2. Add custom service price columns to HopDong table if missing
    $hopDongCols = $pdo->query("SHOW COLUMNS FROM HopDong")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('GiaDien', $hopDongCols)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN GiaDien DECIMAL(18,2) NOT NULL DEFAULT 3500");
    }
    if (!in_array('GiaNuoc', $hopDongCols)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN GiaNuoc DECIMAL(18,2) NOT NULL DEFAULT 15000");
    }
    if (!in_array('GiaXeMay', $hopDongCols)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN GiaXeMay DECIMAL(18,2) NOT NULL DEFAULT 150000");
    }
    if (!in_array('GiaOto', $hopDongCols)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN GiaOto DECIMAL(18,2) NOT NULL DEFAULT 1200000");
    }
    if (!in_array('GiaInternet', $hopDongCols)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN GiaInternet DECIMAL(18,2) NOT NULL DEFAULT 200000");
    }
    if (!in_array('GiaVeSinh', $hopDongCols)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN GiaVeSinh DECIMAL(18,2) NOT NULL DEFAULT 100000");
    }

    echo "MIGRATION_V2_SUCCESS\n";
} catch (Throwable $ex) {
    echo "MIGRATION_V2_ERROR: " . $ex->getMessage() . "\n";
}
