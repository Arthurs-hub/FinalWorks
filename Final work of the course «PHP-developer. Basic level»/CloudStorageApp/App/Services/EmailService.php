<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService implements IEmailService
{
    private PHPMailer $mailer;
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }

    private function setupMailer(): void
    {
        $smtpConfig = $this->config['smtp'];

        $this->mailer->isSMTP();
        $this->mailer->Host = $smtpConfig['host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $smtpConfig['username'];
        $this->mailer->Password = $smtpConfig['password'];
        $this->mailer->SMTPSecure = $smtpConfig['encryption'];
        $this->mailer->Port = $smtpConfig['port'];

        if (!empty($smtpConfig['ssl_cafile'])) {
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'cafile' => $smtpConfig['ssl_cafile'],
                ]
            ];
        }

        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $this->mailer->isHTML(true);
    }


    /**
     * Отправляет приветственное письмо новому пользователю.
     *
     * @param string $to Email получателя
     * @param string $name Имя получателя
     * @return bool
     */
    public function sendWelcomeEmail(string $to, string $name): bool
    {
        $subject = 'Добро пожаловать в CloudStorageApp!';

        $body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <h2>Здравствуйте, {$name}!</h2>
                <p>Благодарим вас за регистрацию в <strong>CloudStorageApp</strong>.</p>
                <p>Мы рады, что вы с нами! Теперь вы можете безопасно хранить и делиться своими файлами.</p>
                <p>Чтобы начать, просто войдите в свой аккаунт:</p>
                <p style='text-align: center;'>
                    <a href='http://localhost:8080/login.html' style='background-color: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Войти</a>
                </p>
                <br>
                <p>С уважением,<br>Команда CloudStorageApp</p>
            </div>
        ";

        return $this->sendEmail($to, $subject, $body);
    }

    public function sendEmail(string $to, string $subject, string $message): bool
    {
        try {
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;

            $this->mailer->send();
            Logger::info('Email sent successfully', ['to' => $to, 'subject' => $subject]);
            return true;
        } catch (Exception $e) {
            Logger::error('Email sending failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $this->mailer->ErrorInfo
            ]);
            return false;
        }
    }

    public function sendPasswordResetEmail(string $to, string $token, string $username): bool
    {
        $resetLink = "http://{$_SERVER['HTTP_HOST']}/reset-password.html?token={$token}";
        $subject = 'Сброс пароля - Cloud Storage';
        $message = "
    <h2>Сброс пароля</h2>
    <p>Здравствуйте, {$username}!</p>
    <p>Вы запросили сброс пароля для вашего аккаунта в Cloud Storage. Нажмите на кнопку ниже, чтобы установить новый пароль:</p>
    <p style='text-align: center;'>
        <a href='{$resetLink}' style='display: inline-block; padding: 12px 24px; font-size: 16px; color: #ffffff; background-color: #4f46e5; text-decoration: none; border-radius: 5px;'>
            Сбросить пароль
        </a>
    </p>
    <p>Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.</p>
    <p>Ссылка действительна в течение 1 часа.</p>
";

        return $this->sendEmail($to, $subject, $message);
    }
}
