<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

/**
 * In-process HTTP client for route tests (no external server required).
 */
final class HttpClient
{
    private string $cookieFile;

    public function __construct(private readonly string $baseUrl = 'http://127.0.0.1:8090')
    {
        $this->cookieFile = sys_get_temp_dir() . '/stokvel-http-' . bin2hex(random_bytes(8)) . '.txt';
    }

    public function __destruct()
    {
        if (is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function resetSession(): void
    {
        Session::forget('user_id');
        Session::forget('active_stokvel_id');
        Session::forget('stokvel_id');
        if (is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = sys_get_temp_dir() . '/stokvel-http-' . bin2hex(random_bytes(8)) . '.txt';
    }

    /**
     * @param array<string, string> $headers
     * @return array{status:int, headers:array<string,string>, body:string, location:?string}
     */
    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, [], $headers);
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $headers
     * @return array{status:int, headers:array<string,string>, body:string, location:?string}
     */
    public function post(string $path, array $form = [], array $headers = [], bool $withCsrf = true): array
    {
        if ($withCsrf && !isset($form['_token'])) {
            Session::start();
            $form['_token'] = Csrf::token();
        }

        return $this->request('POST', $path, $form, $headers);
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $headers
     * @return array{status:int, headers:array<string,string>, body:string, location:?string}
     */
    public function request(string $method, string $path, array $form = [], array $headers = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $method = strtoupper($method);

        // Default to in-process dispatch so AuthService session state is shared.
        // Opt into real HTTP with STOKVEL_HTTP_CLIENT=curl when a server is running.
        if (env('STOKVEL_HTTP_CLIENT') === 'curl' && $this->serverReachable()) {
            return $this->curl($method, $url, $form, $headers);
        }

        return $this->dispatchInProcess($method, $path, $form, $headers);
    }

    private function serverReachable(): bool
    {
        static $reachable = null;
        if ($reachable !== null) {
            return $reachable;
        }
        $ch = curl_init(rtrim($this->baseUrl, '/') . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $reachable = $code > 0;
        return $reachable;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $headers
     * @return array{status:int, headers:array<string,string>, body:string, location:?string}
     */
    private function curl(string $method, string $url, array $form, array $headers): array
    {
        $ch = curl_init($url);
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 15,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($form);
        }
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerText = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        $parsed = [];
        foreach (explode("\r\n", $headerText) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $parsed[strtolower(trim($k))] = trim($v);
            }
        }

        return [
            'status' => $status,
            'headers' => $parsed,
            'body' => $body,
            'location' => $parsed['location'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $headers
     * @return array{status:int, headers:array<string,string>, body:string, location:?string}
     */
    private function dispatchInProcess(string $method, string $path, array $form, array $headers): array
    {
        Session::start();
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_GET = [];
        $_POST = $method === 'POST' ? $form : [];
        $_REQUEST = array_merge($_GET, $_POST);
        foreach ($headers as $k => $v) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }

        $container = App::getInstance()->container();
        $router = $container->get(Router::class);
        $request = Request::capture();
        try {
            $response = $router->dispatch($request, $container);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatus') ? (int) $e->getStatus() : 500;
            return [
                'status' => $status,
                'headers' => [],
                'body' => $e->getMessage(),
                'location' => null,
            ];
        }

        if (!$response instanceof Response) {
            return ['status' => 500, 'headers' => [], 'body' => 'Invalid response', 'location' => null];
        }

        $headersOut = [];
        foreach ($response->headers() as $k => $v) {
            $headersOut[strtolower((string) $k)] = (string) $v;
        }

        return [
            'status' => $response->status(),
            'headers' => $headersOut,
            'body' => $response->body(),
            'location' => $headersOut['location'] ?? null,
        ];
    }
}
