<?php

declare(strict_types=1);

use App\Controllers\Api\WebhookController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Auth\MfaController;
use App\Controllers\Member\DashboardController as MemberDashboardController;
use App\Controllers\Member\SecurityController as MemberSecurityController;
use App\Controllers\Member\DocumentController as MemberDocumentController;
use App\Controllers\Member\LoanController as MemberLoanController;
use App\Controllers\Member\MeetingController as MemberMeetingController;
use App\Controllers\Member\NotificationController as MemberNotificationController;
use App\Controllers\Member\BeneficiaryController as MemberBeneficiaryController;
use App\Controllers\Member\ChangeRequestController as MemberChangeRequestController;
use App\Controllers\Member\InviteController as MemberInviteController;
use App\Controllers\Member\ProfileController as MemberProfileController;
use App\Controllers\Member\StatementController as MemberStatementController;
use App\Controllers\Member\SupportController as MemberSupportController;
use App\Controllers\Platform\AuditController as PlatformAuditController;
use App\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Controllers\Platform\JobController as PlatformJobController;
use App\Controllers\Platform\ProfileController as PlatformProfileController;
use App\Controllers\Platform\RoleController as PlatformRoleController;
use App\Controllers\Platform\SecurityController as PlatformSecurityController;
use App\Controllers\Platform\StokvelTypeController as PlatformStokvelTypeController;
use App\Controllers\Platform\SupportTicketController as PlatformSupportTicketController;
use App\Controllers\Platform\TenantController as PlatformTenantController;
use App\Controllers\Platform\UserController as PlatformUserController;
use App\Controllers\Public\HomeController;
use App\Controllers\Public\InvitationController;
use App\Controllers\Public\RegisterStokvelController;
use App\Controllers\Stokvel\ApprovalController;
use App\Controllers\Stokvel\BeneficiaryController;
use App\Controllers\Stokvel\BurialClaimController;
use App\Controllers\Stokvel\ContributionController;
use App\Controllers\Stokvel\GroceryCycleController;
use App\Controllers\Stokvel\DashboardController as StokvelDashboardController;
use App\Controllers\Stokvel\DocumentCategoryController;
use App\Controllers\Stokvel\DocumentController as StokvelDocumentController;
use App\Controllers\Stokvel\CategorySettingsController;
use App\Controllers\Stokvel\ExpenseCategoryController;
use App\Controllers\Stokvel\ExpenseController;
use App\Controllers\Stokvel\FeeCategoryController;
use App\Controllers\Stokvel\FeeStructureController;
use App\Controllers\Stokvel\LoanController;
use App\Controllers\Stokvel\LoanProductController;
use App\Controllers\Stokvel\MeetingCategoryController;
use App\Controllers\Stokvel\MeetingController as StokvelMeetingController;
use App\Controllers\Stokvel\MemberController;
use App\Controllers\Stokvel\NotificationController as StokvelNotificationController;
use App\Controllers\Stokvel\OnboardingController;
use App\Controllers\Stokvel\PayoutCategoryController;
use App\Controllers\Stokvel\PayoutController;
use App\Controllers\Stokvel\ReportController;
use App\Controllers\Stokvel\RoleManagementController;
use App\Controllers\Stokvel\RuleCategoryController;
use App\Controllers\Stokvel\RuleController;
use App\Controllers\Stokvel\SettingsController;
use App\Controllers\Stokvel\SupportTicketController;
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
$router->get('/login/mfa', [MfaController::class, 'show'], $global);
$router->post('/login/mfa', [MfaController::class, 'verifyTotp'], array_merge($global, $csrf));
$router->get('/login/mfa/passkey/options', [MfaController::class, 'passkeyOptions'], $global);
$router->post('/login/mfa/passkey', [MfaController::class, 'verifyPasskey'], array_merge($global, $csrf));
$router->post('/login/mfa/cancel', [MfaController::class, 'cancel'], array_merge($global, $csrf));
$router->get('/register', [AuthController::class, 'showRegister'], array_merge($global, $guest));
$router->post('/register', [AuthController::class, 'register'], array_merge($global, $guest, $csrf));
$router->get('/logout', [AuthController::class, 'showLogout'], array_merge($global, $auth));
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

$router->get('/dashboard', static function () {
    $auth = app()->container()->get(\App\Services\Auth\AuthService::class);
    return Response::redirect(url(home_path_for_user($auth->user())));
}, $global);
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
$router->post('/platform/tenants/{id}/modules', [PlatformTenantController::class, 'updateModules'], $platformMut);
$router->post('/platform/tenants/{id}/governance', [PlatformTenantController::class, 'updateGovernance'], $platformMut);
$router->post('/platform/tenants/{id}/suspend', [PlatformTenantController::class, 'suspend'], $platformMut);
$router->post('/platform/tenants/{id}/activate', [PlatformTenantController::class, 'activate'], $platformMut);
$router->post('/platform/tenants/{id}/appoint-chairperson', [PlatformUserController::class, 'appointChairperson'], $platformMut);
$router->get('/platform/users', [PlatformUserController::class, 'index'], $platform);
$router->get('/platform/users/appoint/{stokvelId}', [PlatformUserController::class, 'appointForm'], $platform);
$router->post('/platform/users/appoint/{stokvelId}', [PlatformUserController::class, 'appoint'], $platformMut);
$router->post('/platform/users/{id}/grant-all', [PlatformUserController::class, 'grantAll'], $platformMut);
$router->post('/platform/roles/{id}/delete', [PlatformRoleController::class, 'destroy'], $platformMut);
$router->get('/platform/roles', [PlatformRoleController::class, 'index'], $platform);
$router->get('/platform/roles/create', [PlatformRoleController::class, 'create'], $platform);
$router->post('/platform/roles', [PlatformRoleController::class, 'store'], $platformMut);
$router->get('/platform/roles/{id}/edit', [PlatformRoleController::class, 'edit'], $platform);
$router->post('/platform/roles/{id}', [PlatformRoleController::class, 'update'], $platformMut);
$router->get('/platform/stokvel-types', [PlatformStokvelTypeController::class, 'index'], $platform);
$router->get('/platform/stokvel-types/create', [PlatformStokvelTypeController::class, 'create'], $platform);
$router->post('/platform/stokvel-types', [PlatformStokvelTypeController::class, 'store'], $platformMut);
$router->get('/platform/stokvel-types/{id}/edit', [PlatformStokvelTypeController::class, 'edit'], $platform);
$router->post('/platform/stokvel-types/{id}', [PlatformStokvelTypeController::class, 'update'], $platformMut);
$router->post('/platform/stokvel-types/{id}/delete', [PlatformStokvelTypeController::class, 'destroy'], $platformMut);
$router->get('/platform/audit', [PlatformAuditController::class, 'index'], $platform);
$router->get('/platform/jobs', [PlatformJobController::class, 'index'], $platform);
$router->post('/platform/jobs/{id}/retry', [PlatformJobController::class, 'retry'], $platformMut);
$router->get('/platform/support', [PlatformSupportTicketController::class, 'index'], $platform);
$router->get('/platform/support/{id}', [PlatformSupportTicketController::class, 'show'], $platform);
$router->post('/platform/support/{id}', [PlatformSupportTicketController::class, 'update'], $platformMut);

$router->get('/platform/profile', [PlatformProfileController::class, 'edit'], $platform);
$router->post('/platform/profile', [PlatformProfileController::class, 'update'], $platformMut);
$router->post('/platform/profile/password', [PlatformProfileController::class, 'changePassword'], $platformMut);
$router->get('/platform/security', [PlatformSecurityController::class, 'index'], $platform);
$router->post('/platform/security/totp/begin', [PlatformSecurityController::class, 'beginTotp'], $platformMut);
$router->post('/platform/security/totp/confirm', [PlatformSecurityController::class, 'confirmTotp'], $platformMut);
$router->post('/platform/security/totp/disable', [PlatformSecurityController::class, 'disableTotp'], $platformMut);
$router->get('/platform/security/passkey/options', [PlatformSecurityController::class, 'passkeyOptions'], $platform);
$router->post('/platform/security/passkey', [PlatformSecurityController::class, 'passkeyRegister'], $platformMut);
$router->post('/platform/security/passkey/{id}/delete', [PlatformSecurityController::class, 'passkeyDelete'], $platformMut);

$router->post('/invitations/{token}/reject', [InvitationController::class, 'reject'], array_merge($global, $auth, $csrf));

$router->post('/webhooks/payments', [WebhookController::class, 'payments'], $global);

$router->get('/app/beneficiaries', [BeneficiaryController::class, 'index'], $appRead);
$router->get('/app/beneficiaries/create', [BeneficiaryController::class, 'create'], $appRead);
$router->post('/app/beneficiaries', [BeneficiaryController::class, 'store'], $appMut);
$router->get('/app/beneficiaries/{id}', [BeneficiaryController::class, 'show'], $appRead);
$router->get('/app/beneficiaries/{id}/edit', [BeneficiaryController::class, 'edit'], $appRead);
$router->post('/app/beneficiaries/{id}', [BeneficiaryController::class, 'update'], $appMut);
$router->delete('/app/beneficiaries/{id}', [BeneficiaryController::class, 'destroy'], $appMut);
$router->post('/app/beneficiaries/{id}/destroy', [BeneficiaryController::class, 'destroy'], $appMut);
$router->post('/app/beneficiaries/{id}/deactivate', [BeneficiaryController::class, 'deactivate'], $appMut);
$router->post('/app/beneficiaries/{id}/reactivate', [BeneficiaryController::class, 'reactivate'], $appMut);

$router->get('/app/members', [MemberController::class, 'index'], $appRead);
$router->get('/app/members/invite', [MemberController::class, 'inviteForm'], $appRead);
$router->post('/app/members/invite', [MemberController::class, 'invite'], $appMut);
$router->get('/app/members/create', [MemberController::class, 'createForm'], $appRead);
$router->post('/app/members', [MemberController::class, 'store'], $appMut);
$router->get('/app/members/import', [MemberController::class, 'importForm'], $appRead);
$router->get('/app/members/import/template', [MemberController::class, 'importTemplate'], $appRead);
$router->get('/app/members/import/template.csv', [MemberController::class, 'importTemplate'], $appRead);
$router->post('/app/members/import', [MemberController::class, 'importStore'], $appMut);
$router->get('/app/members/{id}/edit', [MemberController::class, 'editForm'], $appRead);
$router->post('/app/members/{id}', [MemberController::class, 'update'], $appMut);
$router->post('/app/members/{id}/beneficiaries', [MemberController::class, 'storeBeneficiary'], $appMut);
$router->post('/app/members/{id}/beneficiaries/{beneficiaryId}', [MemberController::class, 'updateBeneficiary'], $appMut);
$router->delete('/app/members/{id}/beneficiaries/{beneficiaryId}', [MemberController::class, 'deleteBeneficiary'], $appMut);
$router->post('/app/members/{id}/beneficiaries/{beneficiaryId}/delete', [MemberController::class, 'deleteBeneficiary'], $appMut);
$router->post('/app/members/{id}/permissions', [MemberController::class, 'updatePermissions'], $appMut);
$router->post('/app/members/{id}/minimum-contribution', [MemberController::class, 'updateMinimumContribution'], $appMut);
$router->get('/app/members/{id}', [MemberController::class, 'show'], $appRead);
$router->get('/app/members/{id}/statement', [MemberController::class, 'statement'], $appRead);
$router->post('/app/members/{id}/approve', [MemberController::class, 'approveMembership'], $appMut);
$router->post('/app/members/{id}/suspend', [MemberController::class, 'suspend'], $appMut);
$router->post('/app/members/{id}/reinstate', [MemberController::class, 'reinstate'], $appMut);
$router->post('/app/members/{id}/resign', [MemberController::class, 'resign'], $appMut);

$router->get('/app/contributions', [ContributionController::class, 'index'], $appRead);
$router->get('/app/contributions/create', [ContributionController::class, 'create'], $appRead);
$router->post('/app/contributions', [ContributionController::class, 'store'], $appMut);
$router->get('/app/contributions/bulk', [ContributionController::class, 'bulkForm'], $appRead);
$router->post('/app/contributions/bulk', [ContributionController::class, 'bulkStore'], $appMut);
$router->get('/app/contributions/import', [ContributionController::class, 'importForm'], $appRead);
$router->get('/app/contributions/import/template', [ContributionController::class, 'importTemplate'], $appRead);
$router->get('/app/contributions/import/template.csv', [ContributionController::class, 'importTemplate'], $appRead);
$router->post('/app/contributions/import', [ContributionController::class, 'importStore'], $appMut);
$router->get('/app/contributions/arrears', [ContributionController::class, 'arrears'], $appRead);
$router->get('/app/contributions/arrears/capture', [ContributionController::class, 'arrearsCaptureForm'], $appRead);
$router->post('/app/contributions/arrears/capture', [ContributionController::class, 'arrearsCaptureStore'], $appMut);
$router->get('/app/contributions/arrears/import', [ContributionController::class, 'arrearsImportForm'], $appRead);
$router->get('/app/contributions/arrears/import/template', [ContributionController::class, 'arrearsImportTemplate'], $appRead);
$router->post('/app/contributions/arrears/import', [ContributionController::class, 'arrearsImportStore'], $appMut);

$router->get('/app/fees/categories', [FeeCategoryController::class, 'index'], $appRead);
$router->post('/app/fees/categories', [FeeCategoryController::class, 'store'], $appMut);
$router->post('/app/fees/categories/{id}', [FeeCategoryController::class, 'update'], $appMut);
$router->post('/app/fees/categories/{id}/activate', [FeeCategoryController::class, 'activate'], $appMut);
$router->post('/app/fees/categories/{id}/deactivate', [FeeCategoryController::class, 'deactivate'], $appMut);
$router->get('/app/fees', [FeeStructureController::class, 'index'], $appRead);
$router->get('/app/fees/create', [FeeStructureController::class, 'create'], $appRead);
$router->post('/app/fees', [FeeStructureController::class, 'store'], $appMut);
$router->get('/app/fees/{id}', [FeeStructureController::class, 'show'], $appRead);
$router->get('/app/fees/{id}/edit', [FeeStructureController::class, 'edit'], $appRead);
$router->post('/app/fees/{id}', [FeeStructureController::class, 'update'], $appMut);
$router->delete('/app/fees/{id}', [FeeStructureController::class, 'destroy'], $appMut);

$router->get('/app/expenses/categories', [ExpenseCategoryController::class, 'index'], $appRead);
$router->post('/app/expenses/categories', [ExpenseCategoryController::class, 'store'], $appMut);
$router->post('/app/expenses/categories/{id}', [ExpenseCategoryController::class, 'update'], $appMut);
$router->delete('/app/expenses/categories/{id}', [ExpenseCategoryController::class, 'destroy'], $appMut);
$router->post('/app/expenses/categories/{id}/activate', [ExpenseCategoryController::class, 'activate'], $appMut);
$router->post('/app/expenses/categories/{id}/deactivate', [ExpenseCategoryController::class, 'deactivate'], $appMut);

$router->get('/app/expenses', [ExpenseController::class, 'index'], $appRead);
$router->get('/app/expenses/create', [ExpenseController::class, 'create'], $appRead);
$router->post('/app/expenses', [ExpenseController::class, 'store'], $appMut);
$router->get('/app/expenses/{id}', [ExpenseController::class, 'show'], $appRead);
$router->get('/app/expenses/{id}/edit', [ExpenseController::class, 'edit'], $appRead);
$router->post('/app/expenses/{id}', [ExpenseController::class, 'update'], $appMut);
$router->delete('/app/expenses/{id}', [ExpenseController::class, 'destroy'], $appMut);

$router->get('/app/loans/products', [LoanProductController::class, 'index'], $appRead);
$router->get('/app/loans/products/create', [LoanProductController::class, 'create'], $appRead);
$router->post('/app/loans/products', [LoanProductController::class, 'store'], $appMut);
$router->get('/app/loans/products/{id}/edit', [LoanProductController::class, 'edit'], $appRead);
$router->post('/app/loans/products/{id}', [LoanProductController::class, 'update'], $appMut);

$router->get('/app/loans', [LoanController::class, 'index'], $appRead);
$router->get('/app/loans/create', [LoanController::class, 'create'], $appRead);
$router->post('/app/loans', [LoanController::class, 'store'], $appMut);
$router->post('/app/loans/{id}/approve', [LoanController::class, 'approve'], $appMut);
$router->post('/app/loans/{id}/reject', [LoanController::class, 'reject'], $appMut);
$router->post('/app/loans/{id}/disburse', [LoanController::class, 'disburse'], $appMut);
$router->get('/app/loans/{id}', [LoanController::class, 'show'], $appRead);
$router->get('/app/loans/{id}/edit', [LoanController::class, 'edit'], $appRead);
$router->post('/app/loans/{id}', [LoanController::class, 'update'], $appMut);
$router->delete('/app/loans/{id}', [LoanController::class, 'destroy'], $appMut);

$router->get('/app/burial-claims', [BurialClaimController::class, 'index'], $appRead);
$router->get('/app/burial-claims/create', [BurialClaimController::class, 'create'], $appRead);
$router->post('/app/burial-claims', [BurialClaimController::class, 'store'], $appMut);

$router->get('/app/grocery', [GroceryCycleController::class, 'index'], $appRead);
$router->get('/app/grocery/create', [GroceryCycleController::class, 'create'], $appRead);
$router->post('/app/grocery', [GroceryCycleController::class, 'store'], $appMut);
$router->get('/app/grocery/{id}', [GroceryCycleController::class, 'show'], $appRead);

$router->get('/app/payouts/categories', [PayoutCategoryController::class, 'index'], $appRead);
$router->get('/app/payouts/categories/create', [PayoutCategoryController::class, 'create'], $appRead);
$router->post('/app/payouts/categories', [PayoutCategoryController::class, 'store'], $appMut);
$router->get('/app/payouts/categories/{id}/edit', [PayoutCategoryController::class, 'edit'], $appRead);
$router->post('/app/payouts/categories/{id}', [PayoutCategoryController::class, 'update'], $appMut);
$router->post('/app/payouts/categories/{id}/activate', [PayoutCategoryController::class, 'activate'], $appMut);
$router->post('/app/payouts/categories/{id}/deactivate', [PayoutCategoryController::class, 'deactivate'], $appMut);

$router->get('/app/payouts', [PayoutController::class, 'index'], $appRead);
$router->get('/app/payouts/create', [PayoutController::class, 'create'], $appRead);
$router->post('/app/payouts', [PayoutController::class, 'store'], $appMut);
$router->get('/app/payouts/{id}', [PayoutController::class, 'show'], $appRead);
$router->get('/app/payouts/{id}/edit', [PayoutController::class, 'edit'], $appRead);
$router->post('/app/payouts/{id}', [PayoutController::class, 'update'], $appMut);
$router->delete('/app/payouts/{id}', [PayoutController::class, 'destroy'], $appMut);

$router->get('/app/meetings/categories', [MeetingCategoryController::class, 'index'], $appRead);
$router->post('/app/meetings/categories', [MeetingCategoryController::class, 'store'], $appMut);
$router->post('/app/meetings/categories/{id}', [MeetingCategoryController::class, 'update'], $appMut);
$router->post('/app/meetings/categories/{id}/activate', [MeetingCategoryController::class, 'activate'], $appMut);
$router->post('/app/meetings/categories/{id}/deactivate', [MeetingCategoryController::class, 'deactivate'], $appMut);
$router->get('/app/meetings', [StokvelMeetingController::class, 'index'], $appRead);
$router->get('/app/meetings/create', [StokvelMeetingController::class, 'create'], $appRead);
$router->post('/app/meetings', [StokvelMeetingController::class, 'store'], $appMut);
$router->get('/app/meetings/{id}', [StokvelMeetingController::class, 'show'], $appRead);
$router->get('/app/meetings/{id}/edit', [StokvelMeetingController::class, 'edit'], $appRead);
$router->post('/app/meetings/{id}', [StokvelMeetingController::class, 'update'], $appMut);
$router->post('/app/meetings/{id}/attendance', [StokvelMeetingController::class, 'saveAttendance'], $appMut);
$router->post('/app/meetings/{id}/notes', [StokvelMeetingController::class, 'saveNotes'], $appMut);
$router->post('/app/meetings/{id}/minutes', [StokvelMeetingController::class, 'saveMinutes'], $appMut);
$router->delete('/app/meetings/{id}', [StokvelMeetingController::class, 'destroy'], $appMut);

$router->get('/app/rules/categories', [RuleCategoryController::class, 'index'], $appRead);
$router->post('/app/rules/categories', [RuleCategoryController::class, 'store'], $appMut);
$router->post('/app/rules/categories/{id}', [RuleCategoryController::class, 'update'], $appMut);
$router->post('/app/rules/categories/{id}/activate', [RuleCategoryController::class, 'activate'], $appMut);
$router->post('/app/rules/categories/{id}/deactivate', [RuleCategoryController::class, 'deactivate'], $appMut);
$router->get('/app/rules', [RuleController::class, 'index'], $appRead);
$router->get('/app/rules/create', [RuleController::class, 'create'], $appRead);
$router->post('/app/rules', [RuleController::class, 'store'], $appMut);
$router->get('/app/rules/{id}', [RuleController::class, 'show'], $appRead);
$router->get('/app/rules/{id}/edit', [RuleController::class, 'edit'], $appRead);
$router->post('/app/rules/{id}', [RuleController::class, 'update'], $appMut);
$router->delete('/app/rules/{id}', [RuleController::class, 'destroy'], $appMut);

$router->get('/app/documents/categories', [DocumentCategoryController::class, 'index'], $appRead);
$router->get('/app/documents/categories/create', [DocumentCategoryController::class, 'create'], $appRead);
$router->post('/app/documents/categories', [DocumentCategoryController::class, 'store'], $appMut);
$router->get('/app/documents/categories/{id}/edit', [DocumentCategoryController::class, 'edit'], $appRead);
$router->post('/app/documents/categories/{id}', [DocumentCategoryController::class, 'update'], $appMut);
$router->post('/app/documents/categories/{id}/activate', [DocumentCategoryController::class, 'activate'], $appMut);
$router->post('/app/documents/categories/{id}/deactivate', [DocumentCategoryController::class, 'deactivate'], $appMut);

$router->get('/app/documents', [StokvelDocumentController::class, 'index'], $appRead);
$router->get('/app/documents/create', [StokvelDocumentController::class, 'create'], $appRead);
$router->post('/app/documents', [StokvelDocumentController::class, 'storeTyped'], $appMut);
$router->get('/app/documents/{id}', [StokvelDocumentController::class, 'show'], $appRead);
$router->get('/app/documents/{id}/edit', [StokvelDocumentController::class, 'edit'], $appRead);
$router->post('/app/documents/{id}', [StokvelDocumentController::class, 'updateTyped'], $appMut);
$router->get('/app/documents/{id}/download', [StokvelDocumentController::class, 'download'], $appRead);

$router->get('/app/reports', [ReportController::class, 'index'], $appRead);
$router->get('/app/reports/{type}.csv', [ReportController::class, 'export'], $appRead);
$router->get('/app/reports/{type}/export', [ReportController::class, 'export'], $appRead);
$router->get('/app/reports/{type}', [ReportController::class, 'show'], $appRead);

$router->get('/app/support', [SupportTicketController::class, 'index'], $appRead);
$router->get('/app/support/create', [SupportTicketController::class, 'create'], $appRead);
$router->post('/app/support', [SupportTicketController::class, 'store'], $appMut);
$router->get('/app/support/{id}', [SupportTicketController::class, 'show'], $appRead);
$router->post('/app/support/{id}/reply', [SupportTicketController::class, 'reply'], $appMut);

$router->get('/app/approvals', [ApprovalController::class, 'index'], $appRead);
$router->post('/app/approvals/{id}/approve', [ApprovalController::class, 'approve'], $appMut);
$router->post('/app/approvals/{id}/reject', [ApprovalController::class, 'reject'], $appMut);

$router->get('/app/notifications', [StokvelNotificationController::class, 'index'], $appRead);
$router->post('/app/notifications/read-all', [StokvelNotificationController::class, 'markAllRead'], $appMut);
$router->post('/app/notifications/{id}/read', [StokvelNotificationController::class, 'markRead'], $appMut);

$router->get('/app/settings', [SettingsController::class, 'edit'], $appRead);
$router->post('/app/settings', [SettingsController::class, 'update'], $appMut);
$router->get('/app/settings/categories', [CategorySettingsController::class, 'index'], $appRead);
$router->get('/app/settings/roles', [RoleManagementController::class, 'index'], $appRead);
$router->get('/app/settings/roles/{roleCode}', [RoleManagementController::class, 'show'], $appRead);
$router->post('/app/settings/roles/{roleCode}', [RoleManagementController::class, 'update'], $appMut);
$router->post('/app/settings/roles/{roleCode}/assign', [RoleManagementController::class, 'assign'], $appMut);
$router->post('/app/settings/roles/{roleCode}/remove', [RoleManagementController::class, 'remove'], $appMut);

// Setup wizard removed from day-to-day UI — configuration lives in Settings.
$router->get('/app/onboarding', static fn () => Response::redirect(url('/app/settings')), $appRead);
$router->post('/app/onboarding/complete', [OnboardingController::class, 'complete'], $appMut);
$router->post('/app/onboarding/{step}', [OnboardingController::class, 'saveStep'], $appMut);

$router->get('/member', [MemberDashboardController::class, 'index'], $memberRead);
$router->get('/member/loans/apply', [MemberLoanController::class, 'applyForm'], $memberRead);
$router->post('/member/loans/apply', [MemberLoanController::class, 'applyStore'], $memberMut);

$router->get('/member/statement', [MemberStatementController::class, 'show'], $memberRead);
$router->get('/member/meetings', [MemberMeetingController::class, 'index'], $memberRead);
$router->get('/member/meetings/{id}', [MemberMeetingController::class, 'show'], $memberRead);
$router->post('/member/meetings/{id}/acknowledge', [MemberMeetingController::class, 'acknowledge'], $memberMut);
$router->get('/member/documents', [MemberDocumentController::class, 'index'], $memberRead);
$router->get('/member/documents/{id}/download', [MemberDocumentController::class, 'download'], $memberRead);
$router->get('/member/documents/{id}', [MemberDocumentController::class, 'show'], $memberRead);
$router->get('/member/notifications', [MemberNotificationController::class, 'index'], $memberRead);
$router->post('/member/notifications/{id}/read', [MemberNotificationController::class, 'markRead'], $memberMut);
$router->get('/member/profile', [MemberProfileController::class, 'edit'], $memberRead);
$router->post('/member/profile', [MemberProfileController::class, 'update'], $memberMut);
$router->post('/member/profile/password', [MemberProfileController::class, 'changePassword'], $memberMut);
$router->get('/member/beneficiaries', [MemberBeneficiaryController::class, 'index'], $memberRead);
$router->post('/member/beneficiaries', [MemberBeneficiaryController::class, 'store'], $memberMut);
$router->post('/member/beneficiaries/{id}', [MemberBeneficiaryController::class, 'update'], $memberMut);
$router->post('/member/beneficiaries/{id}/delete', [MemberBeneficiaryController::class, 'destroy'], $memberMut);
$router->get('/member/invite', [MemberInviteController::class, 'form'], $memberRead);
$router->post('/member/invite', [MemberInviteController::class, 'store'], $memberMut);
$router->get('/member/support', [MemberSupportController::class, 'index'], $memberRead);
$router->get('/member/support/create', [MemberSupportController::class, 'create'], $memberRead);
$router->post('/member/support', [MemberSupportController::class, 'store'], $memberMut);
$router->get('/member/support/{id}', [MemberSupportController::class, 'show'], $memberRead);
$router->post('/member/support/{id}/reply', [MemberSupportController::class, 'reply'], $memberMut);
$router->get('/member/security', [MemberSecurityController::class, 'index'], $memberRead);
$router->get('/app/profile/security', [MemberSecurityController::class, 'index'], $memberRead);
$router->post('/member/security/totp/begin', [MemberSecurityController::class, 'beginTotp'], $memberMut);
$router->post('/member/security/totp/confirm', [MemberSecurityController::class, 'confirmTotp'], $memberMut);
$router->post('/member/security/totp/disable', [MemberSecurityController::class, 'disableTotp'], $memberMut);
$router->get('/member/security/passkey/options', [MemberSecurityController::class, 'passkeyOptions'], $memberRead);
$router->post('/member/security/passkey', [MemberSecurityController::class, 'passkeyRegister'], $memberMut);
$router->post('/member/security/passkey/{id}/delete', [MemberSecurityController::class, 'passkeyDelete'], $memberMut);
$router->get('/member/change-requests', [MemberChangeRequestController::class, 'index'], $memberRead);
$router->get('/member/change-requests/{id}', [MemberChangeRequestController::class, 'show'], $memberRead);
$router->post('/member/change-requests/{id}/approve', [MemberChangeRequestController::class, 'approve'], $memberMut);
$router->post('/member/change-requests/{id}/reject', [MemberChangeRequestController::class, 'reject'], $memberMut);
