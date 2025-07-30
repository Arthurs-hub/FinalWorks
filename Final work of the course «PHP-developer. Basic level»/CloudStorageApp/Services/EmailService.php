<?php

namespace App\Services;

use App\Core\Logger;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;


class EmailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
    }

    public function sendPasswordResetEmail(string $email, string $resetToken, string $userName): bool
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $emailConfig = $this->config['email'];
        $provider = $this->selectEmailProvider($emailConfig);
        $resetLink = $this->config['app']['url'] . '/reset-password.html?token=' . urlencode($resetToken);

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $provider['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $provider['smtp_username'];
        $mail->Password = $provider['smtp_password'];
        $mail->SMTPSecure = $provider['smtp_secure'];
        $mail->Port = $provider['smtp_port'];

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($email, $userName);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Сброс пароля - CloudStorageApp';
        $mail->Body = $this->buildPasswordResetMessage($userName, $resetLink, $resetToken);
        $mail->AltBody = "Здравствуйте, $userName!\n\nДля сброса пароля перейдите по ссылке: $resetLink\n\nЕсли вы не запрашивали сброс пароля, проигнорируйте это письмо.";

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];

        return $mail->send();
    }

    private function selectEmailProvider(array $emailConfig): array
    {

        if (isset($emailConfig['providers'])) {
            $defaultProvider = $emailConfig['default_provider'] ?? 'gmail';
            return $emailConfig['providers'][$defaultProvider];
        }

        return [
            'smtp_host' => $emailConfig['smtp_host'],
            'smtp_port' => $emailConfig['smtp_port'],
            'smtp_secure' => $emailConfig['smtp_secure'],
            'smtp_username' => $emailConfig['smtp_username'],
            'smtp_password' => $emailConfig['smtp_password'],
        ];
    }

    private function buildPasswordResetMessage(string $userName, string $resetLink, string $resetToken): string
    {
        $templatePath = __DIR__ . '/../templates/password_reset_email.html';
        if (!file_exists($templatePath)) {
            throw new Exception('Email template not found');
        }

        $template = file_get_contents($templatePath);

        $replacements = [
            '{{userName}}' => htmlspecialchars($userName, ENT_QUOTES | ENT_HTML5),
            '{{resetLink}}' => htmlspecialchars($resetLink, ENT_QUOTES | ENT_HTML5),
            '{{resetToken}}' => htmlspecialchars($resetToken, ENT_QUOTES | ENT_HTML5),
        ];

        return strtr($template, $replacements);
    }

    private function buildEmailHeaders(): string
    {
        $fromEmail = $this->config['email']['from_email'] ?? 'noreply@cloudstorage.local';
        $fromName = $this->config['email']['from_name'] ?? 'CloudStorageApp';

        return implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "X-Mailer: PHP/" . phpversion(),
            "X-Priority: 1",
            "X-MSMail-Priority: High",
            "Importance: High",
            "X-Spam-Status: No",
            "X-Spam-Score: 0.0",
            "Authentication-Results: pass",
            "DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed;",
            "List-Unsubscribe: <mailto:{$fromEmail}?subject=unsubscribe>",
        ]);
    }

    public function sendPasswordChangedNotification(string $email, string $userName): bool
    {
        try {
            $subject = 'Пароль изменен - CloudStorageApp';
            $message = $this->buildPasswordChangedMessage($userName);
            $headers = $this->buildEmailHeaders();

            $result = mail($email, $subject, $message, $headers);

            if ($result) {
                Logger::info("Password changed notification sent", ['email' => $email]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            Logger::error("Failed to send password changed notification", [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function buildPasswordChangedMessage(string $userName): string
    {
        return "
        <!DOCTYPE html>
        <html lang='ru'>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>CloudStorageApp</h1>
                    <h2>Пароль изменен</h2>
                </div>
                
                <div class='content'>
                    <p>Здравствуйте, {$userName}!</p>
                    
                    <p>Ваш пароль был успешно изменен.</p>
                    
                    <p>Если это были не вы, немедленно свяжитесь с администрацией.</p>
                    
                    <p>Дата изменения: " . date('d.m.Y H:i:s') . "</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; 2025 CloudStorageApp. Все права защищены.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
