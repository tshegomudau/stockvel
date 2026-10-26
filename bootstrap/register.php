<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Router;
use App\Core\TenantContext;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PlatformAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\AuditRepository;
use App\Repositories\MembershipRepository;
use App\Repositories\StokvelRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\MfaService;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\RememberTokenService;
use App\Services\Finance\ChargeService;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Finance\ContributionService;
use App\Services\Finance\FeeStructureService;
use App\Services\Finance\AutoFineService;
use App\Services\Finance\FineService;
use App\Services\Finance\FundService;
use App\Services\Finance\JoiningFeeService;
use App\Services\Members\ProfileCompletenessService;
use App\Services\Analytics\GroupHealthService;
use App\Services\Analytics\TrendsService;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\SavingGoalService;
use App\Services\Finance\LedgerService;
use App\Services\Notifications\Channels\MailChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\NotificationProcessor;
use App\Services\Notifications\NotificationQueueService;
use App\Services\Notifications\NotificationService;
use App\Services\PermissionService;
use App\Services\Documents\DocumentService;
use App\Services\Finance\CsvImportService;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\ReconciliationService;
use App\Services\Loans\LoanProductService;
use App\Services\Loans\LoanScheduleCalculator;
use App\Services\Loans\LoanService;
use App\Services\Meetings\MeetingService;
use App\Services\Membership\InvitationService;
use App\Services\Membership\MemberChangeRequestService;
use App\Services\Membership\MembershipService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentWebhookService;
use App\Services\Payouts\PayoutService;
use App\Services\RateLimitService;
use App\Services\Reports\ReportService;
use App\Services\Rules\RuleService;
use App\Services\TenantContext as LegacyTenantContext;

return static function (Container $container, string $basePath, array $dbConfig): void {
    $container->singleton(Logger::class, static fn () => new Logger($basePath . '/storage/logs'));
    $container->singleton(Database::class, static fn () => new Database($dbConfig));
    $container->singleton(Router::class, static fn () => new Router());
    $container->singleton(TenantContext::class, static fn () => new TenantContext());

    $container->singleton(UserRepository::class, static fn (Container $c) => new UserRepository($c->get(Database::class), $c->get(TenantContext::class)));
    $container->singleton(AuditRepository::class, static fn (Container $c) => new AuditRepository($c->get(Database::class)));
    $container->singleton(StokvelRepository::class, static fn (Container $c) => new StokvelRepository($c->get(Database::class), $c->get(TenantContext::class)));
    $container->singleton(LegacyTenantContext::class, static fn (Container $c) => new LegacyTenantContext(
        $c->get(Database::class),
        $c->get(StokvelRepository::class),
        $c->get(TenantContext::class)
    ));
    $container->singleton(MembershipRepository::class, static fn (Container $c) => new MembershipRepository($c->get(Database::class), $c->get(TenantContext::class)));

    $container->singleton(PermissionService::class, static fn (Container $c) => new PermissionService($c->get(TenantContext::class), $c->get(Database::class)));
    $container->singleton(AuditService::class, static fn (Container $c) => new AuditService($c->get(AuditRepository::class), $c->get(TenantContext::class)));
    $container->singleton(RememberTokenService::class, static fn (Container $c) => new RememberTokenService($c->get(Database::class)));
    $container->singleton(AuthService::class, static fn (Container $c) => new AuthService(
        $c->get(Database::class),
        $c->get(UserRepository::class),
        $c->get(RememberTokenService::class),
        $c->get(TenantContext::class),
        $c->get(PermissionService::class),
        $c->get(AuditService::class),
        $c->get(Logger::class)
    ));
    $container->singleton(PasswordResetService::class, static fn (Container $c) => new PasswordResetService($c->get(Database::class), $c->get(UserRepository::class), $c->get(AuditService::class)));
    $container->singleton(EmailVerificationService::class, static fn (Container $c) => new EmailVerificationService($c->get(Database::class), $c->get(UserRepository::class), $c->get(AuditService::class)));
    $container->singleton(MfaService::class, static fn (Container $c) => new MfaService($c->get(Database::class)));

    $container->singleton(ChartOfAccountsService::class, static fn (Container $c) => new ChartOfAccountsService($c->get(Database::class)));
    $container->singleton(LedgerService::class, static fn (Container $c) => new LedgerService($c->get(Database::class)));
    $container->singleton(FeeStructureService::class, static fn (Container $c) => new FeeStructureService($c->get(Database::class)));
    $container->singleton(ChargeService::class, static fn (Container $c) => new ChargeService($c->get(Database::class), $c->get(FeeStructureService::class)));
    $container->singleton(FundService::class, static fn (Container $c) => new FundService($c->get(Database::class)));
    $container->singleton(FineService::class, static fn (Container $c) => new FineService($c->get(Database::class)));
    $container->singleton(AutoFineService::class, static fn (Container $c) => new AutoFineService(
        $c->get(Database::class),
        $c->get(FineService::class)
    ));
    $container->singleton(ProfileCompletenessService::class, static fn (Container $c) => new ProfileCompletenessService($c->get(Database::class)));
    $container->singleton(GroupHealthService::class, static fn (Container $c) => new GroupHealthService(
        $c->get(Database::class),
        $c->get(ProfileCompletenessService::class)
    ));
    $container->singleton(TrendsService::class, static fn (Container $c) => new TrendsService($c->get(Database::class)));
    $container->singleton(SavingGoalService::class, static fn (Container $c) => new SavingGoalService(
        $c->get(Database::class),
        $c->get(FundService::class)
    ));
    $container->singleton(JoiningFeeService::class, static fn (Container $c) => new JoiningFeeService(
        $c->get(Database::class),
        $c->get(ChargeService::class)
    ));
    $container->singleton(ContributionService::class, static fn (Container $c) => new ContributionService(
        $c->get(Database::class),
        $c->get(ChargeService::class),
        $c->get(LedgerService::class),
        $c->get(ChartOfAccountsService::class)
    ));

    $container->singleton(MailChannel::class, static fn () => new MailChannel());
    $container->singleton(SmsChannel::class, static fn (Container $c) => new SmsChannel($c->get(Logger::class)));
    $container->singleton(WhatsAppChannel::class, static fn (Container $c) => new WhatsAppChannel($c->get(Logger::class)));
    $container->singleton(NotificationQueueService::class, static fn (Container $c) => new NotificationQueueService(
        $c->get(Database::class),
        $c->get(TenantContext::class),
        $c->get(MailChannel::class),
        $c->get(SmsChannel::class),
        $c->get(WhatsAppChannel::class)
    ));
    $container->singleton(NotificationProcessor::class, static fn (Container $c) => new NotificationProcessor($c->get(NotificationQueueService::class)));
    $container->singleton(NotificationService::class, static fn (Container $c) => new NotificationService($c->get(Database::class), $c->get(TenantContext::class), $c->get(NotificationQueueService::class)));

    $container->singleton(RateLimitService::class, static fn (Container $c) => new RateLimitService($c->get(Database::class)));
    $container->singleton(CsrfMiddleware::class, static fn () => new CsrfMiddleware());
    $container->singleton(AuthMiddleware::class, static fn (Container $c) => new AuthMiddleware($c->get(AuthService::class)));
    $container->singleton(GuestMiddleware::class, static fn (Container $c) => new GuestMiddleware($c->get(AuthService::class)));
    $container->singleton(TenantMiddleware::class, static fn (Container $c) => new TenantMiddleware(
        $c->get(AuthService::class),
        $c->get(LegacyTenantContext::class),
        $c->get(TenantContext::class),
        $c->get(PermissionService::class),
        $c->get(Database::class)
    ));
    $container->singleton(PlatformAuthMiddleware::class, static fn (Container $c) => new PlatformAuthMiddleware($c->get(AuthService::class)));
    $container->singleton(SecurityHeadersMiddleware::class, static fn () => new SecurityHeadersMiddleware());
    $container->singleton(RateLimitMiddleware::class, static fn (Container $c) => new RateLimitMiddleware($c->get(RateLimitService::class)));

    $container->singleton(ReportService::class, static fn (Container $c) => new ReportService($c->get(Database::class)));
    $container->singleton(ExpenseService::class, static fn (Container $c) => new ExpenseService(
        $c->get(Database::class),
        $c->get(LedgerService::class),
        $c->get(ChartOfAccountsService::class)
    ));
    $container->singleton(LoanProductService::class, static fn (Container $c) => new LoanProductService($c->get(Database::class)));
    $container->singleton(LoanScheduleCalculator::class, static fn () => new LoanScheduleCalculator());
    $container->singleton(LoanService::class, static fn (Container $c) => new LoanService(
        $c->get(Database::class),
        $c->get(LoanProductService::class),
        $c->get(LoanScheduleCalculator::class),
        $c->get(LedgerService::class),
        $c->get(ChartOfAccountsService::class)
    ));
    $container->singleton(ApprovalService::class, static fn (Container $c) => new ApprovalService(
        $c->get(Database::class)
    ));
    $container->singleton(PayoutService::class, static fn (Container $c) => new PayoutService(
        $c->get(Database::class),
        $c->get(LedgerService::class),
        $c->get(ChartOfAccountsService::class)
    ));
    $container->singleton(CsvImportService::class, static fn (Container $c) => new CsvImportService(
        $c->get(ContributionService::class),
        $c->get(Database::class)
    ));
    $container->singleton(ReconciliationService::class, static fn (Container $c) => new ReconciliationService($c->get(Database::class)));
    $container->singleton(DocumentService::class, static fn (Container $c) => new DocumentService(
        $c->get(Database::class),
        $c->get(TenantContext::class),
        $c->get(AuditService::class)
    ));
    $container->singleton(InvitationService::class, static fn (Container $c) => new InvitationService(
        $c->get(MembershipRepository::class),
        $c->get(AuditService::class),
        $c->get(NotificationService::class)
    ));
    $container->singleton(MembershipService::class, static fn (Container $c) => new MembershipService(
        $c->get(Database::class),
        $c->get(MembershipRepository::class),
        $c->get(UserRepository::class),
        $c->get(InvitationService::class),
        $c->get(TenantContext::class),
        $c->get(AuditService::class),
        $c->get(JoiningFeeService::class)
    ));
    $container->singleton(MemberChangeRequestService::class, static fn (Container $c) => new MemberChangeRequestService(
        $c->get(Database::class)
    ));
    $container->singleton(MeetingService::class, static fn (Container $c) => new MeetingService(
        $c->get(Database::class),
        $c->get(AuditService::class),
        $c->get(NotificationService::class)
    ));
    $container->singleton(RuleService::class, static fn (Container $c) => new RuleService(
        $c->get(Database::class),
        $c->get(AuditService::class)
    ));
    $container->singleton(OnboardingService::class, static fn (Container $c) => new OnboardingService(
        $c->get(StokvelRepository::class),
        $c->get(AuditService::class)
    ));
    $container->singleton(PaymentGatewayInterface::class, static fn () => new MockPaymentGateway());
    $container->singleton(PaymentWebhookService::class, static fn (Container $c) => new PaymentWebhookService(
        $c->get(Database::class),
        $c->get(PaymentGatewayInterface::class),
        $c->get(ContributionService::class)
    ));
};
