<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ITwoFactorRepository;
use App\Repositories\IUserRepository;
use App\Services\IEmailService;
use App\Services\ITwoFactorService;

class TwoFactorController extends BaseController
{
    private ITwoFactorService $twoFactorService;
    private ITwoFactorRepository $twoFactorRepo;
    private IUserRepository $userRepo;
    private IEmailService $emailService;

    public function __construct(
        ITwoFactorService $twoFactorService,
        ITwoFactorRepository $twoFactorRepo,
        IUserRepository $userRepo,
        IEmailService $emailService
    ) {
        $this->twoFactorService = $twoFactorService;
        $this->twoFactorRepo = $twoFactorRepo;
        $this->userRepo = $userRepo;
        $this->emailService = $emailService;
    }

    public function generateSecret(): Response
    {
        return $this->executeWithAuth(function () {
            $userId = $this->getCurrentUserId();
            $user = $this->userRepo->findById($userId);

            if (!$user) {
                return $this->handleServiceResult(['success' => false, 'message' => 'Пользователь не найден']);
            }

            $secret = $this->twoFactorService->generateSecret();

            $_SESSION['pending_2fa_secret'] = $secret;

            $qrUrl = $this->twoFactorService->generateQRCodeUrl($secret, $user['email']);

            $qrImage = $this->twoFactorService->generateQRCodeImage($qrUrl);

            $this->twoFactorService->logAction($userId, 'setup', 'totp', ['secret_generated' => true]);

            return $this->handleServiceResult([
                'success' => true,
                'secret' => $secret,
                'qr_url' => $qrUrl,
                'qr_image' => $qrImage,
                'account_name' => $user['email'],
                'issuer' => 'Cloud Storage'
            ]);
        });
    }

    public function setupEmail(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $data = $request->getData();
            $isEnabling = $data['enable'] ?? false;
            $userId = $this->getCurrentUserId();

            if ($isEnabling) {
                $this->userRepo->updateUser($userId, ['two_factor_enabled' => 1, 'two_factor_method' => 'email']);
                $this->twoFactorService->logAction($userId, 'setup', 'email', ['enabled' => true]);
                return $this->handleServiceResult(['success' => true, 'message' => '2FA по email включена']);
            } else {
                $this->userRepo->updateUser($userId, ['two_factor_enabled' => 0]);
                $this->twoFactorService->logAction($userId, 'setup', 'email', ['enabled' => false]);
                return $this->handleServiceResult(['success' => true, 'message' => '2FA по email отключена']);
            }
        });
    }

    public function sendEmailCode(): Response
    {
        $userId = $_SESSION['user_id'] ?? $_SESSION['user_id_for_2fa'] ?? $_SESSION['temp_user_data']['user']['id'] ?? null;

        if (!$userId) {
            return new Response(['success' => false, 'message' => 'Пользователь не авторизован для этого действия'], 401);
        }

        $user = $this->userRepo->findById((int) $userId);

        if (!$user) {
            return new Response(['success' => false, 'message' => 'Пользователь не найден'], 404);
        }

        $code = $this->twoFactorService->generateEmailCode();
        $this->twoFactorRepo->saveTwoFactorCode($userId, $code, 'email', 10);

        $subject = 'Код подтверждения - Cloud Storage';
        $message = "
            <h2>Код подтверждения</h2>
            <p>Ваш код для входа в Cloud Storage:</p>
            <h1 style='text-align: center; color: #4f46e5; font-size: 2rem; letter-spacing: 0.5rem;'>{$code}</h1>
            <p>Код действителен в течение 10 минут.</p>
            <p>Если вы не запрашивали этот код, просто проигнорируйте это письмо.</p>
        ";

        $emailSent = $this->emailService->sendEmail($user['email'], $subject, $message);

        $this->twoFactorService->logAction($userId, 'code_generated', 'email', [
            'email_sent' => $emailSent,
            'code_length' => strlen($code)
        ]);

        if ($emailSent) {
            return new Response(['success' => true, 'message' => 'Код отправлен на email'], 200);
        } else {
            return new Response(['success' => false, 'message' => 'Ошибка отправки email'], 500);
        }
    }

    public function verifyTotp(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $data = $request->getData();
            $code = $data['code'] ?? '';

            if (empty($code)) {
                return $this->handleServiceResult(['success' => false, 'message' => 'Код обязателен']);
            }

            $userId = $this->getCurrentUserId();

            $secret = $_SESSION['pending_2fa_secret'] ?? null;

            if (empty($secret)) {
                return $this->handleServiceResult(['success' => false, 'message' => 'TOTP не настроен']);
            }

            $isValid = $this->twoFactorService->verifyTOTP($secret, $code);

            $this->twoFactorService->logAction($userId, 'setup', 'totp', [
                'code_valid' => $isValid,
                'verification_attempt' => true
            ]);

            if ($isValid) {
                return $this->handleServiceResult(['success' => true, 'message' => 'Код подтвержден']);
            } else {
                return $this->handleServiceResult(['success' => false, 'message' => 'Неверный код']);
            }
        });
    }

    public function verifyEmailSetup(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $data = $request->getData();
            $code = $data['code'] ?? '';

            if (empty($code)) {
                return $this->handleServiceResult(['success' => false, 'message' => 'Код обязателен']);
            }

            $userId = $this->getCurrentUserId();
            $isValid = $this->twoFactorRepo->verifyAndUseTwoFactorCode($userId, $code, 'email');

            $this->twoFactorService->logAction($userId, 'setup', 'email', [
                'code_valid' => $isValid,
                'verification_attempt' => true
            ]);

            if ($isValid) {
                return $this->handleServiceResult(['success' => true, 'message' => 'Код подтвержден']);
            } else {
                return $this->handleServiceResult(['success' => false, 'message' => 'Неверный или истекший код']);
            }
        });
    }

    public function generateBackupCodes(): Response
    {
        return $this->executeWithAuth(function () {
            $userId = $this->getCurrentUserId();
            $backupCodes = $this->twoFactorService->generateBackupCodes();

            $this->twoFactorService->logAction($userId, 'setup', 'backup_code', [
                'codes_generated' => count($backupCodes)
            ]);

            return $this->handleServiceResult([
                'success' => true,
                'backup_codes' => $backupCodes
            ]);
        });
    }

    public function completeSetup(Request $request): Response
    {
        return $this->executeWithAuth(function () use ($request) {
            $data = $request->getData();
            $method = $data['method'] ?? '';
            $backupCodes = $data['backup_codes'] ?? [];

            if (empty($method) || !in_array($method, ['email', 'totp'])) {
                return $this->handleServiceResult(['success' => false, 'message' => 'Неверный метод 2FA']);
            }

            $userId = $this->getCurrentUserId();

            $secret = null;
            if ($method === 'totp') {
                $secret = $_SESSION['pending_2fa_secret'] ?? null;
                if (empty($secret)) {
                    return $this->handleServiceResult(['success' => false, 'message' => 'Секрет не найден']);
                }
            }

            $success = $this->twoFactorRepo->updateUserTwoFactorSettings($userId, [
                'two_factor_enabled' => 1,
                'two_factor_method' => $method,
                'two_factor_secret' => $method === 'totp' ? $secret : null,
                'two_factor_backup_codes' => !empty($backupCodes) ? json_encode($backupCodes) : null,
                'two_factor_setup_completed' => 1
            ]);

            if ($method === 'totp') {
                unset($_SESSION['pending_2fa_secret']);
            }

            if ($success) {
                $this->twoFactorService->logAction($userId, 'setup', $method, [
                    'setup_completed' => true,
                    'backup_codes_count' => count($backupCodes)
                ]);

                return $this->handleServiceResult([
                    'success' => true,
                    'message' => 'Двухфакторная аутентификация настроена'
                ]);
            } else {
                return $this->handleServiceResult(['success' => false, 'message' => 'Ошибка сохранения настроек']);
            }
        });
    }

    public function getStatus(): Response
    {
        return $this->executeWithAuth(function () {
            $userId = $this->getCurrentUserId();
            $settings = $this->twoFactorRepo->getUserTwoFactorSettings($userId);

            return $this->handleServiceResult([
                'success' => true,
                'enabled' => (bool) ($settings['two_factor_enabled'] ?? false),
                'method' => $settings['two_factor_method'] ?? null,
                'setup_completed' => (bool) ($settings['two_factor_setup_completed'] ?? false)
            ]);
        });
    }

    public function verifyEmailLogin(Request $request): Response
    {
        $data = $request->getData();
        $code = $data['code'] ?? '';

        if (empty($code)) {
            return $this->handleServiceResult(['success' => false, 'message' => 'Код обязателен']);
        }

        if (!isset($_SESSION['temp_user_data'])) {
            return $this->handleServiceResult(['success' => false, 'message' => 'Сессия истекла']);
        }

        $tempData = $_SESSION['temp_user_data'];
        $user = $tempData['user'];
        $role = $tempData['role'];

        $isValid = $this->twoFactorRepo->verifyAndUseTwoFactorCode($user['id'], $code, 'email');

        $this->twoFactorService->logAction($user['id'], $isValid ? 'login_success' : 'login_failed', 'email', [
            'code_valid' => $isValid,
            'login_attempt' => true
        ]);

        if ($isValid) {

            $this->completeLogin($user, $role);

            return $this->handleServiceResult([
                'success' => true,
                'message' => 'Вход выполнен',
                'user' => $user
            ]);
        } else {
            return $this->handleServiceResult(['success' => false, 'message' => 'Неверный или истекший код']);
        }
    }

    public function verifyTotpLogin(Request $request): Response
    {
        $data = $request->getData();
        $code = $data['code'] ?? '';

        if (empty($code)) {
            return $this->handleServiceResult(['success' => false, 'message' => 'Код обязателен']);
        }

        if (!isset($_SESSION['temp_user_data'])) {
            return $this->handleServiceResult(['success' => false, 'message' => 'Сессия истекла']);
        }

        $tempData = $_SESSION['temp_user_data'];
        $user = $tempData['user'];
        $role = $tempData['role'];

        $settings = $this->twoFactorRepo->getUserTwoFactorSettings($user['id']);
        $secret = $settings['two_factor_secret'] ?? '';

        if (empty($secret)) {
            return $this->handleServiceResult(['success' => false, 'message' => 'TOTP не настроен']);
        }

        $isValid = $this->twoFactorService->verifyTOTP($secret, $code);

        $this->twoFactorService->logAction($user['id'], $isValid ? 'login_success' : 'login_failed', 'totp', [
            'code_valid' => $isValid,
            'login_attempt' => true
        ]);

        if ($isValid) {

            $this->completeLogin($user, $role);

            return $this->handleServiceResult([
                'success' => true,
                'message' => 'Вход выполнен',
                'user' => $user
            ]);
        } else {
            return $this->handleServiceResult(['success' => false, 'message' => 'Неверный код']);
        }
    }

    public function verifyBackupCode(Request $request): Response
    {
        $data = $request->getData();
        $code = $data['code'] ?? '';

        if (empty($code)) {
            return $this->handleServiceResult(['success' => false, 'message' => 'Код обязателен']);
        }

        if (!isset($_SESSION['temp_user_data'])) {
            return $this->handleServiceResult(['success' => false, 'message' => 'Сессия истекла']);
        }

        $tempData = $_SESSION['temp_user_data'];
        $user = $tempData['user'];
        $role = $tempData['role'];

        $backupCodes = $this->twoFactorRepo->getBackupCodes($user['id']);

        if (empty($backupCodes)) {
            return $this->handleServiceResult(['success' => false, 'message' => 'Резервные коды не настроены']);
        }

        $isValid = $this->twoFactorService->verifyBackupCode($backupCodes, $code);

        if ($isValid) {

            $updatedCodes = $this->twoFactorService->removeUsedBackupCode($backupCodes, $code);
            $this->twoFactorRepo->updateBackupCodes($user['id'], $updatedCodes);

            $this->twoFactorService->logAction($user['id'], 'login_success', 'backup_code', [
                'code_used' => true,
                'remaining_codes' => count($updatedCodes)
            ]);

            $this->completeLogin($user, $role);

            return $this->handleServiceResult([
                'success' => true,
                'message' => 'Вход выполнен с резервным кодом',
                'user' => $user,
                'remaining_backup_codes' => count($updatedCodes)
            ]);
        } else {
            return $this->handleServiceResult(['success' => false, 'message' => 'Неверный резервный код']);
        }
    }

    private function completeLogin(array $user, string $role): void
    {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $role;
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];

        unset($_SESSION['temp_user_data']);
    }
}