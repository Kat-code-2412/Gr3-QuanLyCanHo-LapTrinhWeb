<?php

declare(strict_types=1);

// Cấu hình gửi mail qua Gmail SMTP
// Hướng dẫn lấy Mật khẩu ứng dụng Google (App Password):
// 1. Truy cập: https://myaccount.google.com/security
// 2. Bật "Xác minh 2 bước" (2-Step Verification)
// 3. Vào mục "Mật khẩu ứng dụng" (App Passwords) tại: https://myaccount.google.com/apppasswords
// 4. Đặt tên ứng dụng (ví dụ: "QuanLyCanHo") và nhấn "Tạo"
// 5. Sao chép chuỗi 16 chữ số được cấp và dán vào trường 'password' dưới đây.

return [
    'driver' => 'smtp',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'huuluan04743@gmail.com', // Email Gmail gửi đi
    'password' => '', // Dán Mật khẩu ứng dụng (App Password 16 chữ số) vào đây
    'from_address' => 'huuluan04743@gmail.com',
    'from_name' => 'Hệ Thống Quản Lý Căn Hộ Dịch Vụ',
    'debug_mode' => false,
];
