<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Exceptions\HttpException;

abstract class Controller
{
    protected function tenant(): TenantContext
    {
        return app()->container()->get(TenantContext::class);
    }

    protected function db(): Database
    {
        return app()->container()->get(Database::class);
    }

    protected function user(): ?array
    {
        $fromTenant = $this->tenant()->user();
        if ($fromTenant !== null) {
            return $fromTenant;
        }
        $userId = Session::get('user_id');
        if ($userId === null) {
            return null;
        }
        return $this->db()->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => (int) $userId]);
    }

    protected function userId(): ?int
    {
        $id = Session::get('user_id');
        if ($id !== null) {
            return (int) $id;
        }
        return $this->tenant()->userId();
    }

    protected function stokvelId(): ?int
    {
        $id = Session::get('active_stokvel_id');
        if ($id !== null) {
            return (int) $id;
        }
        return $this->tenant()->stokvelId();
    }

    protected function requireAuth(): void
    {
        if ($this->userId() === null) {
            throw new HttpException(401, 'Please sign in to continue.');
        }
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();
        $this->tenant()->requirePermission($permission);
    }

    protected function requireStokvel(): int
    {
        $this->requireAuth();
        return $this->tenant()->requireStokvelId();
    }

    protected function render(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        $data['user'] = $data['user'] ?? $this->user();
        $data['stokvel'] = $data['stokvel'] ?? $this->tenant()->stokvel();
        $data['activeNav'] = $data['activeNav'] ?? '';
        $data['navContext'] = $data['navContext'] ?? 'app';

        if (!isset($data['memberships']) && $this->userId() !== null && class_exists(\App\Services\Auth\AuthService::class)) {
            try {
                $auth = app()->container()->get(\App\Services\Auth\AuthService::class);
                if (method_exists($auth, 'membershipsForUser')) {
                    $data['memberships'] = $auth->membershipsForUser((int) $this->userId());
                }
            } catch (\Throwable) {
                $data['memberships'] = [];
            }
        }

        if (!isset($data['unreadNotifications']) && $this->userId() !== null && $this->stokvelId() !== null) {
            try {
                $row = $this->db()->fetch(
                    "SELECT COUNT(*) AS c FROM notifications
                     WHERE stokvel_id = :sid AND user_id = :uid AND read_at IS NULL",
                    ['sid' => $this->stokvelId(), 'uid' => $this->userId()]
                );
                $data['unreadNotifications'] = (int) ($row['c'] ?? 0);
            } catch (\Throwable) {
                $data['unreadNotifications'] = 0;
            }
        }

        return Response::html(view($template, $data, $layout));
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(url($path), $status);
    }

    protected function redirectBack(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->header('Referer');
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($referer !== null && ($base === '' || str_starts_with($referer, $base))) {
            return Response::redirect($referer);
        }
        return $this->redirect($fallback);
    }

    protected function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    protected function perPage(Request $request, int $default = 25): int
    {
        return max(1, min(100, (int) $request->query('per_page', $default)));
    }

    protected function flashErrors(array $errors): void
    {
        Session::flash('errors', $errors);
    }

    protected function flashOld(Request $request): void
    {
        Session::flash('_old', $request->all());
    }

    /** @template T of object */
    protected function service(string $class, callable $fallback): object
    {
        if (class_exists($class)) {
            $container = app()->container();
            if ($container->has($class)) {
                return $container->get($class);
            }
            return new $class($this->db());
        }
        return $fallback($this->db());
    }
}
