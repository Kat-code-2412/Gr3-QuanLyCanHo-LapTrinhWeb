<?php

declare(strict_types=1);

namespace Services;

use PDO;
use Throwable;

class MailService
{
    /**
     * Tạo và lưu mã OTP 6 số vào database
     */
    public static function generateOtp(string $target, string $type = 'REGISTER'): string
    {
        $pdo = require __DIR__ . '/../config/database.php';
        $otp = sprintf('%06d', mt_rand(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // Có hiệu lực 5 phút

        // Hủy các OTP cũ chưa dùng cùng loại
        $stmtCancel = $pdo->prepare("UPDATE otp_codes SET used_at = NOW() WHERE email_or_phone = ? AND type = ? AND used_at IS NULL");
        $stmtCancel->execute([$target, $type]);

        // Thêm OTP mới
        $stmtIns = $pdo->prepare("
            INSERT INTO otp_codes (email_or_phone, otp_code, type, attempts, expires_at, created_at)
            VALUES (?, ?, ?, 0, ?, NOW())
        ");
        $stmtIns->execute([$target, $otp, $type, $expiresAt]);

        return $otp;
    }

    /**
     * Xác minh mã OTP
     */
    public static function verifyOtp(string $target, string $otp, string $type = 'REGISTER'): array
    {
        $pdo = require __DIR__ . '/../config/database.php';

        $stmt = $pdo->prepare("
            SELECT * FROM otp_codes 
            WHERE email_or_phone = ? AND type = ? AND used_at IS NULL 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$target, $type]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'Không tìm thấy mã xác minh hoặc mã đã được sử dụng.'];
        }

        // Kiểm tra quá số lần thử (tối đa 5 lần)
        if ((int)$record['attempts'] >= 5) {
            return ['success' => false, 'message' => 'Bạn đã nhập sai quá 5 lần. Vui lòng yêu cầu mã mới.'];
        }

        // Kiểm tra thời hạn
        if (strtotime($record['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Mã OTP đã hết hiệu lực (quá 5 phút). Vui lòng gửi lại mã mới.'];
        }

        // Kiểm tra mã OTP
        if ($record['otp_code'] !== trim($otp)) {
            // Tăng biến đếm số lần nhập sai
            $stmtUp = $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
            $stmtUp->execute([$record['id']]);
            $remaining = 5 - ((int)$record['attempts'] + 1);
            return ['success' => false, 'message' => "Mã OTP không chính xác. Bạn còn {$remaining} lần thử."];
        }

        // Đánh dấu đã sử dụng thành công
        $stmtUse = $pdo->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = ?");
        $stmtUse->execute([$record['id']]);

        return ['success' => true, 'message' => 'Xác minh OTP thành công!'];
    }

    /**
     * Gửi Email chứa mã OTP với template HTML chuyên nghiệp
     */
    public static function sendOtpEmail(string $recipientEmail, string $recipientName, string $otpCode, string $type = 'REGISTER'): bool
    {
        $config = require __DIR__ . '/../config/mail.php';
        $subject = ($type === 'REGISTER') 
            ? 'Mã Xác Minh Đăng Ký Tài Khoản - Hệ Thống Quản Lý Căn Hộ Dịch Vụ'
            : 'Mã Xác Minh Đặt Lại Mật Khẩu - Hệ Thống Quản Lý Căn Hộ Dịch Vụ';

        $actionText = ($type === 'REGISTER') 
            ? 'kích hoạt tài khoản nhân viên của bạn' 
            : 'xác nhận đặt lại mật khẩu mới';

        $htmlBody = "
        <div style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 30px 15px; color: #1e293b;'>
            <div style='max-width: 550px; margin: 0 auto; background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                <div style='text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 20px;'>
                    <h2 style='color: #2563eb; margin: 0; font-size: 20px;'>HỆ THỐNG QUẢN LÝ CĂN HỘ DỊCH VỤ</h2>
                    <p style='color: #64748b; font-size: 13px; margin: 5px 0 0;'>Serviced Apartment Management System</p>
                </div>
                <p>Xin chào <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                <p>Bạn đang thực hiện yêu cầu " . $actionText . ". Vui lòng nhập mã xác minh (OTP) dưới đây:</p>
                <div style='background-color: #f1f5f9; border: 2px dashed #3b82f6; border-radius: 8px; text-align: center; padding: 15px; margin: 25px 0;'>
                    <span style='font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #1d4ed8; font-family: monospace;'>" . $otpCode . "</span>
                </div>
                <p style='font-size: 13px; color: #ef4444; margin: 5px 0;'><strong>Lưu ý:</strong> Mã xác minh chỉ có hiệu lực trong vòng <strong>5 phút</strong> và chỉ sử dụng được 1 lần duy nhất.</p>
                <p style='font-size: 13px; color: #64748b; margin: 5px 0;'>Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email hoặc liên hệ với Ban quản trị.</p>
                <div style='border-top: 1px solid #e2e8f0; margin-top: 25px; padding-top: 15px; text-align: center; font-size: 12px; color: #94a3b8;'>
                    © " . date('Y') . " Hệ Thống Quản Lý Căn Hộ Dịch Vụ. Bảo lưu mọi quyền.
                </div>
            </div>
        </div>
        ";

        // Ghi log mã OTP vào file log
        file_put_contents(__DIR__ . '/../logs/otp_mail.log', "[" . date('Y-m-d H:i:s') . "] Target: {$recipientEmail} | OTP: {$otpCode} | Type: {$type}\n", FILE_APPEND);

        // Gửi email trực tiếp qua Gmail SMTP
        require_once __DIR__ . '/SmtpMailer.php';
        $sendResult = SmtpMailer::send($recipientEmail, $recipientName, $subject, $htmlBody, $config);

        // Nếu SMTP không thành công và server có sendmail native, thử fallback qua mail()
        if (!$sendResult['success']) {
            try {
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-type: text/html; charset=utf-8',
                    'From: ' . $config['from_name'] . ' <' . $config['from_address'] . '>',
                    'Reply-To: ' . $config['from_address'],
                    'X-Mailer: PHP/' . phpversion()
                ];
                @mail($recipientEmail, $subject, $htmlBody, implode("\r\n", $headers));
            } catch (Throwable $e) {
                // Ignore fallback error
            }
        }

        return $sendResult['success'];
    }
}
