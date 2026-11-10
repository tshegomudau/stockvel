<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Exceptions\FinanceException;
use App\Exceptions\TenantException;
use App\Middleware\TenantMiddleware;
use App\Services\Auth\AuthService;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Finance\ContributionService;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\PaymentWebhookService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Payment webhook rejects missing signature', function (): void {
        test_transaction(function (): void {
            /** @var PaymentWebhookService $svc */
            $svc = App::getInstance()->container()->get(PaymentWebhookService::class);
            $threw = false;
            try {
                $svc->handle('{"event_id":"x"}', null);
            } catch (FinanceException $e) {
                $threw = str_contains(strtolower($e->getMessage()), 'signature');
            }
            test_assert($threw, 'Unsigned webhook must be rejected');
        });
    }, 'integration');

    $runner->add('Payment webhook with valid signature is idempotent and records event', function (): void {
        test_transaction(function (): void {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null);
            $sid = (int) $stokvel['id'];
            $member = $db->fetch(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($member !== null);
            App::getInstance()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($sid);

            /** @var MockPaymentGateway $gateway */
            $gateway = App::getInstance()->container()->get(\App\Services\Payments\PaymentGatewayInterface::class);
            $payload = $gateway->buildPaidWebhookPayload(
                $sid,
                (int) $member['id'],
                'mock_pay_' . bin2hex(random_bytes(4)),
                'ref-' . bin2hex(random_bytes(4)),
                '50.00'
            );
            $raw = json_encode($payload, JSON_THROW_ON_ERROR);
            $sig = $gateway->signPayload($raw);

            /** @var PaymentWebhookService $svc */
            $svc = App::getInstance()->container()->get(PaymentWebhookService::class);
            $first = $svc->handle($raw, $sig);
            test_assert(empty($first['duplicate']), 'First webhook should process');
            test_assert(!empty($first['payment_id']), 'Expected payment_id from webhook');

            $second = $svc->handle($raw, $sig);
            test_assert(!empty($second['duplicate']), 'Replay must be duplicate');

            $row = $db->fetch(
                'SELECT status, external_id FROM webhook_events WHERE provider = :p AND external_id = :e LIMIT 1',
                ['p' => MockPaymentGateway::PROVIDER, 'e' => $payload['event_id']]
            );
            test_assert($row !== null && (string) $row['status'] === 'processed');
        });
    }, 'integration');

    $runner->add('HTTP payment webhook returns 401 without signature', function (): void {
        Request::forceRawBody(json_encode([
            'event_id' => 'http-unsigned-' . bin2hex(random_bytes(4)),
            'reference' => 'r1',
            'stokvel_id' => 1,
        ], JSON_THROW_ON_ERROR));
        try {
            $client = new HttpClient();
            $res = $client->post('/webhooks/payments', [], [], false);
            test_assert((int) $res['status'] === 401, 'Expected 401, got ' . $res['status'] . ' body=' . $res['body']);
            test_assert(str_contains($res['body'], 'Invalid signature') || str_contains($res['body'], 'signature'));
        } finally {
            Request::forceRawBody(null);
        }
    }, 'http');

    $runner->add('Contribution capture with same idempotency key does not duplicate', function (): void {
        test_transaction(function (): void {
            $db = test_db();
            $sid = (int) $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1")['id'];
            $member = $db->fetch(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' LIMIT 1",
                ['sid' => $sid]
            );
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            App::getInstance()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($sid);
            /** @var ContributionService $contrib */
            $contrib = App::getInstance()->container()->get(ContributionService::class);
            $key = 'contrib_manual_test_' . bin2hex(random_bytes(8));
            $a = $contrib->capturePayment($sid, (int) $member['id'], '25.00', 'cash', (int) $user['id'], $key, 't1', date('Y-m-d'));
            $b = $contrib->capturePayment($sid, (int) $member['id'], '25.00', 'cash', (int) $user['id'], $key, 't1', date('Y-m-d'));
            test_assert((int) $a['id'] === (int) $b['id'], 'Idempotent capture must return same payment');
            $count = (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM payment_transactions WHERE stokvel_id = :sid AND idempotency_key = :k',
                ['sid' => $sid, 'k' => $key]
            )['c'] ?? 0);
            test_assert($count === 1, 'Expected exactly one payment row for key');
        });
    }, 'integration');

    $runner->add('Suspended membership is denied by TenantMiddleware', function (): void {
        test_transaction(function (): void {
            $db = test_db();
            $auth = App::getInstance()->container()->get(AuthService::class);
            $auth->logout();
            $membership = $db->fetch(
                "SELECT m.id, m.user_id, m.stokvel_id, u.email
                 FROM memberships m
                 INNER JOIN users u ON u.id = m.user_id
                 WHERE m.status = 'active'
                 LIMIT 1"
            );
            test_assert($membership !== null);
            $db->exec(
                "UPDATE memberships SET status = 'suspended', updated_at = :u WHERE id = :id",
                ['u' => now_utc(), 'id' => (int) $membership['id']]
            );
            // Prefer known demo password; fall back to ensuring session via login helper if needed.
            try {
                $auth->login((string) $membership['email'], 'Password123!');
            } catch (\Throwable) {
                // If password unknown, set session user directly for middleware unit check.
                \App\Core\Session::start();
                \App\Core\Session::set('user_id', (int) $membership['user_id']);
                \App\Core\Session::set('active_stokvel_id', (int) $membership['stokvel_id']);
                $auth->hydrateTenantFromSession();
            }
            \App\Core\Session::set('active_stokvel_id', (int) $membership['stokvel_id']);

            $mw = App::getInstance()->container()->get(TenantMiddleware::class);
            $req = new Request('GET', '/app/dashboard', [], [], $_SERVER, [], []);
            $threw = false;
            try {
                $mw->handle($req, static fn () => 'ok');
            } catch (TenantException $e) {
                $threw = str_contains(strtolower($e->getMessage()), 'suspended');
            }
            test_assert($threw, 'Suspended membership must be blocked');
            $auth->logout();
        });
    }, 'integration');
};
