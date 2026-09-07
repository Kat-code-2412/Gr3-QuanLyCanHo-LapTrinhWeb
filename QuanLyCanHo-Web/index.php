<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['MaNV'])) {
    if (($_SESSION['VaiTro'] ?? '') === 'Admin') {
        redirect('/admin/index.php');
    } else {
        redirect('/user/index.php');
    }
} else {
    redirect('/auth/login.php');
}
