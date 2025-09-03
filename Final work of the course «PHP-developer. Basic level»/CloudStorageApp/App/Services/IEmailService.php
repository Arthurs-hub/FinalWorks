<?php

declare(strict_types=1);

namespace App\Services;

interface IEmailService
{
    public function sendEmail(string $to, string $subject, string $message): bool;
    public function sendPasswordResetEmail(string $to, string $token, string $username): bool;
    public function sendWelcomeEmail(string $to, string $name): bool;
}
