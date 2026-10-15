<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class WebhookController extends Controller
{
    public function payments(Request $request): Response
    {
        return $this->payment($request);
    }

    public function payment(Request $request): Response
    {
        $payload = json_decode($request->rawBody(), true);
        if (!is_array($payload)) {
            return Response::json(['error' => 'Invalid payload'], 400);
        }

        $signature = $request->header('X-Webhook-Signature', '');
        $processor = $this->service(\App\Services\Payments\WebhookProcessor::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function handle(array $payload, string $signature): array
            {
                $reference = (string) ($payload['reference'] ?? $payload['id'] ?? '');
                if ($reference === '') {
                    return ['status' => 'ignored'];
                }
                $existing = $this->db->fetch('SELECT id FROM payment_webhooks WHERE external_ref = :ref LIMIT 1', ['ref' => $reference]);
                if ($existing) {
                    return ['status' => 'duplicate'];
                }
                $this->db->insert(
                    'INSERT INTO payment_webhooks (external_ref, payload_json, signature, processed_at, created_at) VALUES (:ref, :pj, :sig, :pa, :ca)',
                    [
                        'ref' => $reference,
                        'pj' => json_encode($payload),
                        'sig' => $signature,
                        'pa' => now_utc(),
                        'ca' => now_utc(),
                    ]
                );
                return ['status' => 'processed'];
            }
        });

        $result = $processor->handle($payload, (string) $signature);
        return Response::json($result);
    }
}
