<?php

declare(strict_types=1);

namespace Services;

use Throwable;

class SmtpMailer
{
    /**
     * Gửi email thông qua Gmail SMTP (TLS 587 hoặc SSL 465)
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        ?array $customConfig = null
    ): array {
        $config = $customConfig ?? (require __DIR__ . '/../config/mail.php');

        $host = $config['host'] ?? 'smtp.gmail.com';
        $port = (int)($config['port'] ?? 587);
        $encryption = strtolower($config['encryption'] ?? 'tls');
        $username = trim((string)($config['username'] ?? ''));
        $password = trim((string)($config['password'] ?? ''));
        $fromEmail = !empty($config['from_address']) ? $config['from_address'] : $username;
        $fromName = !empty($config['from_name']) ? $config['from_name'] : 'Hệ Thống Quản Lý Căn Hộ Dịch Vụ';

        // Kiểm tra thông tin cấu hình
        if (empty($username) || empty($password)) {
            $err = 'Chưa cấu hình tài khoản Gmail hoặc Mật khẩu ứng dụng (App Password) trong config/mail.php.';
            self::logError($err);
            return ['success' => false, 'message' => $err];
        }

        // Loại bỏ khoảng trắng trong App Password nếu có (ví dụ 'abcd efgh ijkl mnop')
        $password = str_replace(' ', '', $password);

        try {
            $protocol = ($encryption === 'ssl' || $port === 465) ? 'ssl://' : 'tcp://';
            $timeout = 15;
            $errno = 0;
            $errstr = '';

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $socket = @stream_socket_client(
                $protocol . $host . ':' . $port,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                throw new \Exception("Không thể kết nối đến SMTP Server {$host}:{$port} - Lỗi: {$errstr} ({$errno})");
            }

            stream_set_timeout($socket, $timeout);

            // 1. Đọc lời chào mở đầu
            $response = self::readResponse($socket);
            if (!self::isCode($response, 220)) {
                throw new \Exception("Phản hồi mở đầu không hợp lệ: {$response}");
            }

            // 2. Gửi EHLO
            self::sendCommand($socket, "EHLO " . gethostname());
            $ehloResp = self::readResponse($socket);

            // 3. STARTTLS nếu sử dụng TLS (port 587)
            if ($encryption === 'tls' || $port === 587) {
                self::sendCommand($socket, "STARTTLS");
                $tlsResp = self::readResponse($socket);
                if (!self::isCode($tlsResp, 220)) {
                    throw new \Exception("Không thể kích hoạt STARTTLS: {$tlsResp}");
                }

                $cryptoSuccess = stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                );

                if (!$cryptoSuccess) {
                    throw new \Exception("Bắt tay mã hóa TLS với Gmail thất bại.");
                }

                // Gửi lại EHLO sau khi mã hóa TLS
                self::sendCommand($socket, "EHLO " . gethostname());
                self::readResponse($socket);
            }

            // 4. AUTH LOGIN
            self::sendCommand($socket, "AUTH LOGIN");
            $authResp = self::readResponse($socket);
            if (!self::isCode($authResp, 334)) {
                throw new \Exception("Máy chủ từ chối yêu cầu AUTH LOGIN: {$authResp}");
            }

            // Gửi Username (Base64)
            self::sendCommand($socket, base64_encode($username));
            $userResp = self::readResponse($socket);
            if (!self::isCode($userResp, 334)) {
                throw new \Exception("Tài khoản Gmail không hợp lệ: {$userResp}");
            }

            // Gửi App Password (Base64)
            self::sendCommand($socket, base64_encode($password));
            $passResp = self::readResponse($socket);
            if (!self::isCode($passResp, 235)) {
                throw new \Exception("Mật khẩu ứng dụng Gmail (App Password) không chính xác hoặc chưa bật: {$passResp}");
            }

            // 5. MAIL FROM
            self::sendCommand($socket, "MAIL FROM:<{$username}>");
            $mailFromResp = self::readResponse($socket);
            if (!self::isCode($mailFromResp, 250)) {
                throw new \Exception("Lỗi MAIL FROM: {$mailFromResp}");
            }

            // 6. RCPT TO
            self::sendCommand($socket, "RCPT TO:<{$toEmail}>");
            $rcptResp = self::readResponse($socket);
            if (!self::isCode($rcptResp, 250)) {
                throw new \Exception("Lỗi RCPT TO ({$toEmail}): {$rcptResp}");
            }

            // 7. DATA
            self::sendCommand($socket, "DATA");
            $dataResp = self::readResponse($socket);
            if (!self::isCode($dataResp, 354)) {
                throw new \Exception("Lỗi khởi tạo DATA: {$dataResp}");
            }

            // Xây dựng Header chuẩn MIME
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $encodedToName = !empty($toName) ? '=?UTF-8?B?' . base64_encode($toName) . '?=' : '';
            $toHeader = !empty($encodedToName) ? "{$encodedToName} <{$toEmail}>" : "<{$toEmail}>";
            $messageId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . parse_url($host, PHP_URL_HOST) . '>';

            $headers = [
                "Date: " . date('r'),
                "To: {$toHeader}",
                "From: {$encodedFromName} <{$username}>",
                "Reply-To: {$username}",
                "Subject: {$encodedSubject}",
                "Message-ID: {$messageId}",
                "X-Mailer: ServicedApartmentMailer/2.0",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "Content-Transfer-Encoding: base64",
            ];

            $bodyBase64 = chunk_split(base64_encode($htmlContent));
            $messageData = implode("\r\n", $headers) . "\r\n\r\n" . $bodyBase64 . "\r\n.\r\n";

            // Gửi toàn bộ nội dung email
            fwrite($socket, $messageData);
            $sendResp = self::readResponse($socket);
            if (!self::isCode($sendResp, 250)) {
                throw new \Exception("Gửi nội dung email thất bại: {$sendResp}");
            }

            // 8. QUIT
            self::sendCommand($socket, "QUIT");
            @fclose($socket);

            return ['success' => true, 'message' => 'Email đã được gửi thành công qua Gmail SMTP.'];
        } catch (Throwable $ex) {
            if (isset($socket) && is_resource($socket)) {
                @fclose($socket);
            }
            self::logError($ex->getMessage());
            return ['success' => false, 'message' => $ex->getMessage()];
        }
    }

    private static function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private static function readResponse($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // Nếu ký tự thứ 4 là khoảng trắng hoặc không có dấu gạch nối (-), kết thúc phản hồi nhiều dòng
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    private static function isCode(string $response, int $code): bool
    {
        return strncmp($response, (string)$code, 3) === 0;
    }

    private static function logError(string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logDir . '/mail_error.log', "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
    }
}
