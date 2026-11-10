<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\FinanceException;
use App\Services\Payments\PaymentWebhookService;

final class WebhookController extends Controller
{
    public function payments(Request $request): Response
    {
        return $this->payment($request);
    }

    public function payment(Request $request): Response
    {
        $raw = $request->rawBody();
        if (trim($raw) === '') {
            return Response::json(['error' => 'Invalid payload'], 400);
        }

        try {
            /** @var PaymentWebhookService $webhooks */
            $webhooks = app()->container()->get(PaymentWebhookService::class);
            $result = $webhooks->handle($raw, $request->header('X-Webhook-Signature'));
        } catch (FinanceException $e) {
            $message = $e->getMessage();
            if (str_contains(strtolower($message), 'signature')) {
                return Response::json(['error' => 'Invalid signature'], 401);
            }

            return Response::json(['error' => $message], 400);
        }

        if (!empty($result['duplicate'])) {
            return Response::json([
                'status' => 'duplicate',
                'event_id' => $result['event_id'] ?? null,
            ]);
        }

        return Response::json(array_merge(['status' => 'processed'], $result));
    }
}
