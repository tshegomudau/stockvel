<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->service(\App\Services\Membership\InvitationService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function findByToken(string $token): ?array
            {
                return $this->db->fetch(
                    'SELECT i.*, s.name AS stokvel_name FROM member_invitations i JOIN stokvels s ON s.id = i.stokvel_id WHERE i.token_hash = :th AND i.status = :st LIMIT 1',
                    ['th' => hash('sha256', $token), 'st' => 'pending']
                );
            }
        })->findByToken($token);

        if ($invitation === null) {
            Session::flash('error', 'This invitation is invalid or has already been used.');
            return $this->redirect('/');
        }

        return $this->render('public/invitation', [
            'title' => 'Stokvel invitation',
            'token' => $token,
            'invitation' => $invitation,
        ], 'layouts/guest');
    }

    public function accept(Request $request, string $token): Response
    {
        $this->service(\App\Services\Membership\InvitationService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function accept(string $token, ?int $userId): void
            {
                $inv = $this->db->fetch('SELECT * FROM member_invitations WHERE token_hash = :th LIMIT 1', ['th' => hash('sha256', $token)]);
                if (!$inv) {
                    throw new \RuntimeException('Invalid invitation');
                }
                $this->db->exec('UPDATE member_invitations SET status = :st, responded_at = :ra WHERE id = :id', [
                    'st' => 'accepted',
                    'ra' => now_utc(),
                    'id' => $inv['id'],
                ]);
                if ($userId) {
                    $this->db->insert(
                        'INSERT INTO memberships (stokvel_id, user_id, role, status, joined_at, created_at, updated_at) VALUES (:sid, :uid, :role, :status, :ja, :ca, :ua)',
                        [
                            'sid' => $inv['stokvel_id'],
                            'uid' => $userId,
                            'role' => $inv['role'] ?? 'ordinary',
                            'status' => 'active',
                            'ja' => now_utc(),
                            'ca' => now_utc(),
                            'ua' => now_utc(),
                        ]
                    );
                }
            }
        })->accept($token, $this->userId());

        Session::flash('success', 'Invitation accepted. Welcome to the stokvel.');
        return $this->redirect($this->userId() ? '/app' : '/register');
    }

    public function reject(Request $request, string $token): Response
    {
        $this->db()->exec(
            'UPDATE member_invitations SET status = :st, responded_at = :ra WHERE token_hash = :th',
            ['st' => 'rejected', 'ra' => now_utc(), 'th' => hash('sha256', $token)]
        );
        Session::flash('success', 'Invitation declined.');
        return $this->redirect('/');
    }
}
