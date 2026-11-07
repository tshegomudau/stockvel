<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\HttpException;
use App\Services\Stokvel\StokvelCreationRequestService;
use App\Support\StokvelTypeCatalog;

final class RegisterStokvelController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->render('public/start-stokvel', [
            'title' => 'Create a stokvel',
            'stokvelTypeOptions' => StokvelTypeCatalog::activeOptions($this->db()),
            'user' => $this->user(),
        ], 'layouts/guest');
    }

    public function store(Request $request): Response
    {
        $user = $this->user();
        $data = [
            'first_name' => trim((string) $request->input('first_name', $user['first_name'] ?? '')),
            'last_name' => trim((string) $request->input('last_name', $user['last_name'] ?? '')),
            'email' => trim((string) $request->input('email', $user['email'] ?? '')),
            'stokvel_name' => trim((string) $request->input('stokvel_name', '')),
            'stokvel_type' => trim((string) $request->input('stokvel_type', 'general_savings')),
            'description' => trim((string) $request->input('description', '')),
        ];
        if ($user) {
            // Prefer profile names when signed in.
            $profile = $this->db()->fetch(
                'SELECT first_name, last_name FROM user_profiles WHERE user_id = :id LIMIT 1',
                ['id' => (int) $user['id']]
            );
            if ($profile) {
                $data['first_name'] = (string) ($profile['first_name'] ?: $data['first_name']);
                $data['last_name'] = (string) ($profile['last_name'] ?: $data['last_name']);
            }
            $data['email'] = (string) ($user['email'] ?? $data['email']);
        }

        $validator = Validator::make($data, [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
            'email' => 'required|email',
            'stokvel_name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Please check the form and try again.');
            return $this->redirect('/start-stokvel');
        }

        try {
            /** @var StokvelCreationRequestService $service */
            $service = app()->container()->get(StokvelCreationRequestService::class);
            $result = $service->submit($data);
            if (!empty($result['duplicate'])) {
                Session::flash('info', 'A similar request is already pending or approved. Tracking page opened.');
            } else {
                Session::flash('success', 'Your request was submitted for platform approval. Check your email for updates.');
            }
            return $this->redirect('/stokvel-requests/' . $result['public_token']);
        } catch (HttpException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/start-stokvel');
        }
    }

    public function status(Request $request, string $token): Response
    {
        /** @var StokvelCreationRequestService $service */
        $service = app()->container()->get(StokvelCreationRequestService::class);
        $row = $service->findByPublicToken($token);
        if (!$row) {
            Session::flash('error', 'Request not found.');
            return $this->redirect('/start-stokvel');
        }

        // Expire claim window when viewing status.
        if ((string) $row['status'] === 'approved'
            && !empty($row['claim_expires_at'])
            && (string) $row['claim_expires_at'] < now_utc()
        ) {
            $this->db()->exec(
                "UPDATE stokvel_creation_requests SET status = 'expired', updated_at = :ua WHERE id = :id AND status = 'approved'",
                ['ua' => now_utc(), 'id' => (int) $row['id']]
            );
            $row['status'] = 'expired';
        }

        return $this->render('public/stokvel-request-status', [
            'title' => 'Request status',
            'request' => $row,
        ], 'layouts/guest');
    }

    /** Legacy alias used by older links. */
    public function start(Request $request): Response
    {
        return $this->show($request);
    }
}
