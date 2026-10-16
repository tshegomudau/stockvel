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

        $signature = (string) $request->header('X-Webhook-Signature', '');
        $reference = (string) ($payload['reference'] ?? $payload['id'] ?? '');
        if ($reference === '') {
            return Response::json(['status' => 'ignored']);
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM webhook_events WHERE provider = :provider AND external_id = :ref LIMIT 1',
            ['provider' => 'payments', 'ref' => $reference]
        );
        if ($existing) {
            return Response::json(['status' => 'duplicate']);
        }

        $now = now_utc();
        $this->db()->insert(
            'INSERT INTO webhook_events
                (stokvel_id, provider, event_type, external_id, payload_json, status, processed_at, created_at, updated_at)
             VALUES
                (:sid, :provider, :event_type, :external_id, :payload_json, :status, :processed_at, :created_at, :updated_at)',
            [
                'sid' => isset($payload['stokvel_id']) ? (int) $payload['stokvel_id'] : null,
                'provider' => 'payments',
                'event_type' => (string) ($payload['event'] ?? 'payment'),
                'external_id' => $reference,
                'payload_json' => json_encode([
                    'payload' => $payload,
                    'signature' => $signature,
                ], JSON_THROW_ON_ERROR),
                'status' => 'processed',
                'processed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return Response::json(['status' => 'processed']);
    }
}
