<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use App\Services\Auth\PasswordResetService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Registration and login', function (): void {
        test_transaction(function () {
            $auth = App::getInstance()->container()->get(AuthService::class);
            $email = 'test_' . bin2hex(random_bytes(4)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);
            test_assert(isset($user['id']));
            $loggedIn = $auth->login($email, 'Password123!');
            test_assert((int) $loggedIn['id'] === (int) $user['id']);
        });
    });

    $runner->add('Password reset token flow', function (): void {
        test_transaction(function () {
            \App\Core\Session::forget('user_id');
            App::getInstance()->container()->get(\App\Core\TenantContext::class)->setUser(null);
            $container = App::getInstance()->container();
            $auth = $container->get(AuthService::class);
            $reset = $container->get(PasswordResetService::class);
            $email = 'reset_' . bin2hex(random_bytes(4)) . '@example.com';
            $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Reset',
                'last_name' => 'User',
            ]);
            $token = $reset->createToken($email);
            test_assert(is_string($token) && $token !== '');
            $reset->consume($token, 'NewPassword123!');
            test_assert($auth->attempt($email, 'NewPassword123!') !== null);
        });
    });
};
