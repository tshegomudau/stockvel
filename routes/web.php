<?php

declare(strict_types=1);

use App\Controllers\Api\WebhookController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Member\DashboardController as MemberDashboardController;
use App\Controllers\Member\DocumentController as MemberDocumentController;
use App\Controllers\Member\MeetingController as MemberMeetingController;
use App\Controllers\Member\NotificationController as MemberNotificationController;
use App\Controllers\Member\ProfileController as MemberProfileController;
use App\Controllers\Member\StatementController as MemberStatementController;
use App\Controllers\Platform\AuditController as PlatformAuditController;
use App\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Controllers\Platform\JobController as PlatformJobController;
use App\Controllers\Platform\TenantController as PlatformTenantController;
use App\Controllers\Platform\UserController as PlatformUserController;
use App\Controllers\Public\HomeController;
use App\Controllers\Public\InvitationController;
use App\Controllers\Public\RegisterStokvelController;
use App\Controllers\Stokvel\ApprovalController;
use App\Controllers\Stokvel\ContributionController;
use App\Controllers\Stokvel\DashboardController as StokvelDashboardController;
use App\Controllers\Stokvel\DocumentController as StokvelDocumentController;
use App\Controllers\Stokvel\ExpenseController;
use App\Controllers\Stokvel\FeeStructureController;
use App\Controllers\Stokvel\LoanController;
use App\Controllers\Stokvel\MeetingController as StokvelMeetingController;
use App\Controllers\Stokvel\MemberController;
use App\Controllers\Stokvel\NotificationController as StokvelNotificationController;
use App\Controllers\Stokvel\OnboardingController;
use App\Controllers\Stokvel\PayoutController;
use App\Controllers\Stokvel\ReportController;
use App\Controllers\Stokvel\RuleController;
use App\Controllers\Stokvel\SettingsController;
use App\Controllers\Stokvel\SwitchStokvelController;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PlatformAuthMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\TenantMiddleware;

/** @var Router $router */

$global = [SecurityHeadersMiddleware::class];
$csrf = [CsrfMiddleware::class];
$auth = [AuthMiddleware::class];
$tenant = [AuthMiddleware::class, TenantMiddleware::class];
$guest = [GuestMiddleware::class];

$router->get('/', [HomeController::class, 'landing'], $global);
$router->get('/health', static fn () => Response::json(['ok' => true, 'app' => env('APP_NAME')]), $global);

$router->get('/login', [AuthController::class, 'showLogin'], array_merge($global, $guest));
$router->post('/login', [AuthController::class, 'login'], array_merge($global, $guest, $csrf));
$router->get('/register', [AuthController::class, 'showRegister'], array_merge($global, $guest));
$router->post('/register', [AuthController::class, 'register'], array_merge($global, $guest, $csrf));
$router->post('/logout', [AuthController::class, 'logout'], array_merge($global, $auth, $csrf));
$router->get('/forgot-password', [AuthController::class, 'showForgot'], array_merge($global, $guest));
$router->post('/forgot-password', [AuthController::class, 'forgot'], array_merge($global, $guest, $csrf));
$router->get('/reset-password/{token}', [AuthController::class, 'showReset'], array_merge($global, $guest));
$router->post('/reset-password/{token}', [AuthController::class, 'reset'], array_merge($global, $guest, $csrf));
$router->get('/verify-email/{token}', [AuthController::class, 'verifyEmail'], $global);
$router->get('/verify-email/notice', [AuthController::class, 'verifyNotice'], array_merge($global, $auth));
$router->post('/verify-email/resend', [AuthController::class, 'resendVerification'], array_merge($global, $auth, $csrf));

$router->get('/invitations/{token}', [InvitationController::class, 'show'], $global);
$router->post('/invitations/{token}/accept', [InvitationController::class, 'accept'], array_merge($global, $auth, $csrf));

$router->get('/start-stokvel', [RegisterStokvelController::class, 'show'], array_merge($global, $auth));
$router->post('/start-stokvel', [RegisterStokvelController::class, 'store'], array_merge($global, $auth, $csrf));

$router->get('/dashboard', static fn () => Response::redirect(url('/app')));
$router->get('/app', [StokvelDashboardController::class, 'index'], array_merge($global, $tenant));
$router->post('/app/switch-stokvel', [SwitchStokvelController::class, 'switch'], array_merge($global, $auth, $csrf));

$appRead = array_merge($global, $tenant);
$appMut = array_merge($global, $tenant, $csrf);
$platform = array_merge($global, $auth, [PlatformAuthMiddleware::class]);
$platformMut = array_merge($platform, $csrf);
$memberRead = array_merge($global, $tenant);
$memberMut = array_merge($global, $tenant, $csrf);

$router->get('/platform', [PlatformDashboardController::class, 'index'], $platform);
$router->get('/platform/tenants', [PlatformTenantController::class, 'index'], $platform);
$router->get('/platform/tenants/{id}', [PlatformTenantController::class, 'show'], $platform);
$router->post('/platform/tenants/{id}/suspend', [PlatformTenantController::class, 'suspend'], $platformMut);
$router->post('/platform/tenants/{id}/activate', [PlatformTenantController::class, 'activate'], $platformMut);
$router->get('/platform/users', [PlatformUserController::class, 'index'], $platform);
$router->get('/platform/audit', [PlatformAuditController::class, 'index'], $platform);
$router->get('/platform/jobs', [PlatformJobController::class, 'index'], $platform);

$router->post('/invitations/{token}/reject', [InvitationController::class, 'reject'], array_merge($global, $auth, $csrf));

$router->post('/webhooks/payments', [WebhookController::class, 'payments'], $global);

$router->get('/app/members', [MemberController::class, 'index'], $appRead);
$router->get('/app/members/invite', [MemberController::class, 'inviteForm'], $appRead);
$router->post('/app/members/invite', [MemberController::class, 'invite'], $appMut);
$router->get('/app/members/{id}', [MemberController::class, 'show'], $appRead);
$router->get('/app/members/{id}/statement', [MemberController::class, 'statement'], $appRead);
$router->post('/app/members/{id}/suspend', [MemberController::class, 'suspend'], $appMut);
$router->post('/app/members/{id}/reinstate', [MemberController::class, 'reinstate'], $appMut);
$router->post('/app/members/{id}/resign', [MemberController::class, 'resign'], $appMut);

$router->get('/app/contributions', [ContributionController::class, 'index'], $appRead);
$router->get('/app/contributions/create', [ContributionController::class, 'create'], $appRead);
$router->post('/app/contributions', [ContributionController::class, 'store'], $appMut);
$router->get('/app/contributions/bulk', [ContributionController::class, 'bulkForm'], $appRead);
$router->post('/app/contributions/bulk', [ContributionController::class, 'bulkStore'], $appMut);
$router->get('/app/contributions/import', [ContributionController::class, 'importForm'], $appRead);
$router->post('/app/contributions/import', [ContributionController::class, 'importStore'], $appMut);
$router->get('/app/contributions/arrears', [ContributionController::class, 'arrears'], $appRead);

$router->get('/app/fees', [FeeStructureController::class, 'index'], $appRead);
$router->get('/app/fees/create', [FeeStructureController::class, 'create'], $appRead);
$router->post('/app/fees', [FeeStructureController::class, 'store'], $appMut);
$router->get('/app/fees/{id}', [FeeStructureController::class, 'show'], $appRead);
$router->get('/app/fees/{id}/edit', [FeeStructureController::class, 'edit'], $appRead);
$router->post('/app/fees/{id}', [FeeStructureController::class, 'update'], $appMut);
$router->delete('/app/fees/{id}', [FeeStructureController::class, 'destroy'], $appMut);

$router->get('/app/expenses', [ExpenseController::class, 'index'], $appRead);
$router->get('/app/expenses/create', [ExpenseController::class, 'create'], $appRead);
$router->post('/app/expenses', [ExpenseController::class, 'store'], $appMut);
$router->get('/app/expenses/{id}', [ExpenseController::class, 'show'], $appRead);
$router->get('/app/expenses/{id}/edit', [ExpenseController::class, 'edit'], $appRead);
$router->post('/app/expenses/{id}', [ExpenseController::class, 'update'], $appMut);
$router->delete('/app/expenses/{id}', [ExpenseController::class, 'destroy'], $appMut);

$router->get('/app/loans', [LoanController::class, 'index'], $appRead);
$router->get('/app/loans/create', [LoanController::class, 'create'], $appRead);
$router->post('/app/loans', [LoanController::class, 'store'], $appMut);
$router->get('/app/loans/{id}', [LoanController::class, 'show'], $appRead);
$router->get('/app/loans/{id}/edit', [LoanController::class, 'edit'], $appRead);
$router->post('/app/loans/{id}', [LoanController::class, 'update'], $appMut);
$router->delete('/app/loans/{id}', [LoanController::class, 'destroy'], $appMut);

$router->get('/app/payouts', [PayoutController::class, 'index'], $appRead);
$router->get('/app/payouts/create', [PayoutController::class, 'create'], $appRead);
$router->post('/app/payouts', [PayoutController::class, 'store'], $appMut);
$router->get('/app/payouts/{id}', [PayoutController::class, 'show'], $appRead);
$router->get('/app/payouts/{id}/edit', [PayoutController::class, 'edit'], $appRead);
$router->post('/app/payouts/{id}', [PayoutController::class, 'update'], $appMut);
$router->delete('/app/payouts/{id}', [PayoutController::class, 'destroy'], $appMut);

$router->get('/app/meetings', [StokvelMeetingController::class, 'index'], $appRead);
$router->get('/app/meetings/create', [StokvelMeetingController::class, 'create'], $appRead);
$router->post('/app/meetings', [StokvelMeetingController::class, 'store'], $appMut);
$router->get('/app/meetings/{id}', [StokvelMeetingController::class, 'show'], $appRead);
$router->get('/app/meetings/{id}/edit', [StokvelMeetingController::class, 'edit'], $appRead);
$router->post('/app/meetings/{id}', [StokvelMeetingController::class, 'update'], $appMut);
$router->delete('/app/meetings/{id}', [StokvelMeetingController::class, 'destroy'], $appMut);

$router->get('/app/rules', [RuleController::class, 'index'], $appRead);
$router->get('/app/rules/create', [RuleController::class, 'create'], $appRead);
$router->post('/app/rules', [RuleController::class, 'store'], $appMut);
$router->get('/app/rules/{id}', [RuleController::class, 'show'], $appRead);
$router->get('/app/rules/{id}/edit', [RuleController::class, 'edit'], $appRead);
$router->post('/app/rules/{id}', [RuleController::class, 'update'], $appMut);
$router->delete('/app/rules/{id}', [RuleController::class, 'destroy'], $appMut);

$router->get('/app/documents', [StokvelDocumentController::class, 'index'], $appRead);
$router->get('/app/documents/upload', [StokvelDocumentController::class, 'uploadForm'], $appRead);
$router->post('/app/documents/upload', [StokvelDocumentController::class, 'upload'], $appMut);
$router->get('/app/documents/{id}/download', [StokvelDocumentController::class, 'download'], $appRead);

$router->get('/app/reports', [ReportController::class, 'index'], $appRead);
$router->get('/app/reports/{type}.csv', [ReportController::class, 'export'], $appRead);
$router->get('/app/reports/{type}', [ReportController::class, 'show'], $appRead);

$router->get('/app/approvals', [ApprovalController::class, 'index'], $appRead);
$router->post('/app/approvals/{id}/approve', [ApprovalController::class, 'approve'], $appMut);
$router->post('/app/approvals/{id}/reject', [ApprovalController::class, 'reject'], $appMut);

$router->get('/app/notifications', [StokvelNotificationController::class, 'index'], $appRead);
$router->post('/app/notifications/read-all', [StokvelNotificationController::class, 'markAllRead'], $appMut);
$router->post('/app/notifications/{id}/read', [StokvelNotificationController::class, 'markRead'], $appMut);

$router->get('/app/settings', [SettingsController::class, 'edit'], $appRead);
$router->post('/app/settings', [SettingsController::class, 'update'], $appMut);

$router->get('/app/onboarding', [OnboardingController::class, 'index'], $appRead);
$router->post('/app/onboarding/complete', [OnboardingController::class, 'complete'], $appMut);
$router->post('/app/onboarding/{step}', [OnboardingController::class, 'saveStep'], $appMut);

$router->get('/member', [MemberDashboardController::class, 'index'], $memberRead);
$router->get('/member/statement', [MemberStatementController::class, 'show'], $memberRead);
$router->get('/member/meetings', [MemberMeetingController::class, 'index'], $memberRead);
$router->post('/member/meetings/{id}/acknowledge', [MemberMeetingController::class, 'acknowledge'], $memberMut);
$router->get('/member/documents', [MemberDocumentController::class, 'index'], $memberRead);
$router->get('/member/documents/{id}/download', [MemberDocumentController::class, 'download'], $memberRead);
$router->get('/member/notifications', [MemberNotificationController::class, 'index'], $memberRead);
$router->post('/member/notifications/{id}/read', [MemberNotificationController::class, 'markRead'], $memberMut);
$router->get('/member/profile', [MemberProfileController::class, 'edit'], $memberRead);
$router->post('/member/profile', [MemberProfileController::class, 'update'], $memberMut);
