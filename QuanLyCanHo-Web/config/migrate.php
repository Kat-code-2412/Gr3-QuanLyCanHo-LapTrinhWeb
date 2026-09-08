<?php

declare(strict_types=1);

$pdo = require __DIR__ . '/database.php';

try {
    $columns = $pdo->query("SHOW COLUMNS FROM HopDong")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('SoNguoiOi', $columns)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN SoNguoiOi INT NOT NULL DEFAULT 1");
    }
    if (!in_array('SoXeMay', $columns)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN SoXeMay INT NOT NULL DEFAULT 0");
    }
    if (!in_array('SoOto', $columns)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN SoOto INT NOT NULL DEFAULT 0");
    }
    if (!in_array('FileHopDong', $columns)) {
        $pdo->exec("ALTER TABLE HopDong ADD COLUMN FileHopDong VARCHAR(255) NULL");
    }

    echo "MIGRATION_SUCCESS\n";
} catch (Throwable $ex) {
    echo "MIGRATION_ERROR: " . $ex->getMessage() . "\n";
}
