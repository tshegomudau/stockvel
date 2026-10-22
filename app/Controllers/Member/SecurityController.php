<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\MfaService;
use App\Support\QrSvg;
use App\Support\Totp;

final class SecurityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);

        $setup = Session::get('mfa_totp_setup_secret');
        $pendingSecret = null;
        $otpauthUri = null;
        $qrSvg = null;
        if (is_array($setup) && (int) ($setup['user_id'] ?? 0) === $userId) {
            $pendingSecret = (string) ($setup['secret'] ?? '');
            $user = $this->user();
            $account = (string) ($user['email'] ?? ('user-' . $userId));
            $issuer = (string) env('APP_NAME', 'stockvel');
            $otpauthUri = Totp::otpAuthUri($pendingSecret, $account, $issuer);
            try {
                $qrSvg = QrSvg::render($otpauthUri, 3, 2);
            } catch (\Throwable) {
                $qrSvg = null;
            }
        }

        $recoveryCodes = Session::getFlash('mfa_recovery_codes');

        return $this->render('member/security/index', [
            'title' => 'Security & MFA',
            'activeNav' => 'member-profile',
            'navContext' => 'member',
            'securityBase' => '/member/security',
            'profileBackUrl' => '/member/profile',
            'totpEnabled' => $mfa->isTotpEnabled($userId),
            'passkeys' => $mfa->listPasskeys($userId),
            'pendingSecret' => $pendingSecret,
            'otpauthUri' => $otpauthUri,
            'qrSvg' => $qrSvg,
            'recoveryCodes' => is_array($recoveryCodes) ? $recoveryCodes : null,
        ]);
    }

    public function beginTotp(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $user = $this->user();
        $account = (string) ($user['email'] ?? ('user-' . $userId));
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        $mfa->beginTotpSetup($userId, $account);
        Session::flash('success', 'Scan the QR code or enter the secret in your authenticator app, then confirm with a code.');
        return $this->redirect('/member/security');
    }

    public function confirmTotp(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $code = (string) $request->input('code', '');
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        try {
            $recovery = $mfa->confirmTotpSetup($userId, $code);
            Session::flash('mfa_recovery_codes', $recovery);
            Session::flash('success', 'Authenticator app enabled. Save your recovery codes now — they will not be shown again.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/member/security');
    }

    public function disableTotp(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $code = (string) $request->input('code', '');
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        try {
            $mfa->disableTotp($userId, $code);
            Session::flash('success', 'Authenticator app disabled.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/member/security');
    }

    public function passkeyOptions(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $user = $this->user() ?? [];
        $email = (string) ($user['email'] ?? '');
        $display = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        $options = $mfa->beginPasskeyRegistration($userId, $email, $display);
        return Response::json(['ok' => true, 'options' => $options]);
    }

    public function passkeyRegister(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $payload = $this->jsonBody($request);
        $wantsJson = str_contains((string) $request->header('Accept', ''), 'application/json')
            || str_contains((string) $request->header('Content-Type', ''), 'application/json');

        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        try {
            $mfa->completePasskeyRegistration($userId, $payload);
            if ($wantsJson) {
                return Response::json(['ok' => true]);
            }
            Session::flash('success', 'Passkey registered.');
        } catch (\Throwable $e) {
            if ($wantsJson) {
                return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/member/security');
    }

    public function passkeyDelete(Request $request, string $id): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        $mfa->deletePasskey($userId, (int) $id);
        Session::flash('success', 'Passkey removed.');
        return $this->redirect('/member/security');
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $raw = $request->rawBody();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $request->all();
    }
}
