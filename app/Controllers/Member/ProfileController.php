<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Auth\AuthService;
use App\Support\SensitiveData;

final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $user = $this->db()->fetch(
            'SELECT u.id, u.email, u.mobile, p.first_name, p.last_name, p.id_number_encrypted
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.id = :id LIMIT 1',
            ['id' => $userId]
        );
        $idPlain = SensitiveData::decrypt($user['id_number_encrypted'] ?? null);

        return $this->render('member/profile', [
            'title' => 'My profile',
            'activeNav' => 'member-profile',
            'navContext' => 'member',
            'profile' => $user,
            'idMasked' => SensitiveData::maskId($idPlain),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $data = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'id_number' => trim((string) $request->input('id_number', '')),
        ];
        $validator = Validator::make($data, [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Could not update profile.');
            return $this->redirect('/member/profile');
        }

        $now = now_utc();
        $this->db()->exec(
            'UPDATE users SET mobile = :mobile, updated_at = :ua WHERE id = :id',
            ['mobile' => $data['mobile'] ?: null, 'ua' => $now, 'id' => $userId]
        );

        $enc = null;
        $hash = null;
        if ($data['id_number'] !== '' && !str_contains($data['id_number'], '•')) {
            $enc = SensitiveData::encrypt($data['id_number']);
            $hash = SensitiveData::hash($data['id_number']);
        }

        $profile = $this->db()->fetch('SELECT id FROM user_profiles WHERE user_id = :id LIMIT 1', ['id' => $userId]);
        if ($profile) {
            if ($enc !== null) {
                $this->db()->exec(
                    'UPDATE user_profiles SET first_name = :fn, last_name = :ln,
                        id_number = NULL, id_number_encrypted = :enc, id_number_hash = :hash, updated_at = :ua
                     WHERE user_id = :id',
                    ['fn' => $data['first_name'], 'ln' => $data['last_name'], 'enc' => $enc, 'hash' => $hash, 'ua' => $now, 'id' => $userId]
                );
            } else {
                $this->db()->exec(
                    'UPDATE user_profiles SET first_name = :fn, last_name = :ln, updated_at = :ua WHERE user_id = :id',
                    ['fn' => $data['first_name'], 'ln' => $data['last_name'], 'ua' => $now, 'id' => $userId]
                );
            }
        } else {
            $this->db()->insert(
                'INSERT INTO user_profiles (user_id, first_name, last_name, id_number_encrypted, id_number_hash, created_at, updated_at)
                 VALUES (:id, :fn, :ln, :enc, :hash, :ca, :ua)',
                [
                    'id' => $userId,
                    'fn' => $data['first_name'],
                    'ln' => $data['last_name'],
                    'enc' => $enc,
                    'hash' => $hash,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }

        Session::flash('success', 'Profile updated.');
        return $this->redirect('/member/profile');
    }

    public function changePassword(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $current = (string) $request->input('current_password', '');
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');

        $user = $this->db()->fetch('SELECT password_hash FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$user || !password_verify($current, (string) $user['password_hash'])) {
            Session::flash('error', 'Current password is incorrect.');
            return $this->redirect('/member/profile');
        }
        if (strlen($password) < 8 || $password !== $confirm) {
            Session::flash('error', 'New passwords must match and be at least 8 characters.');
            return $this->redirect('/member/profile');
        }

        $this->db()->exec(
            'UPDATE users SET password_hash = :ph, must_change_password = 0, updated_at = :ua WHERE id = :id',
            ['ph' => AuthService::hashPassword($password), 'ua' => now_utc(), 'id' => $userId]
        );
        Session::flash('success', 'Password updated.');
        return $this->redirect('/member/profile');
    }
}
