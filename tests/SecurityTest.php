<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\CsrfException;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('CSRF rejection', function (): void {
        Session::start();
        Csrf::token();
        $threw = false;
        try {
            Csrf::validate('invalid-token');
        } catch (CsrfException) {
            $threw = true;
        }
        test_assert($threw, 'Expected CsrfException');
    });

    $runner->add('CSRF regenerates when TTL expired', function (): void {
        Session::start();
        $old = Csrf::token();
        Session::set('_csrf_token_time', time() - 10_000);
        $fresh = Csrf::token();
        test_assert($fresh !== $old, 'Expired CSRF token should be regenerated on read');
        Csrf::validate($fresh);
    });

    $runner->add('Validation rules', function (): void {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
        test_assert($v->fails());
    });

    $runner->add('SQL injection attempts blocked', function (): void {
        test_transaction(function () {
            $db = test_db();
            $row = $db->fetch('SELECT id FROM users WHERE email = :email LIMIT 1', [
                'email' => "admin@stockvel.local' OR '1'='1",
            ]);
            test_assert($row === null);
        });
    });
};
