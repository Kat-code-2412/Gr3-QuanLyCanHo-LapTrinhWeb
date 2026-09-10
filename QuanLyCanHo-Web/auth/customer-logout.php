<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

unset($_SESSION['MaKhach'], $_SESSION['HoTenKhach']);
redirect('/auth/customer-login.php');
