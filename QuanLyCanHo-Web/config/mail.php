<?php

declare(strict_types=1);

return [
    'driver' => 'smtp',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'system.canhodichvu@gmail.com',
    'password' => '', // App password khi cấu hình production
    'from_address' => 'noreply@canhodichvu.vn',
    'from_name' => 'Hệ Thống Quản Lý Căn Hộ Dịch Vụ',
    'debug_mode' => true, // Ở chế độ debug/local, lưu log và hiển thị flash để test trực tiếp
];
