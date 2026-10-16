<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->requireAuth();
        $user = $this->user();
        return $this->render('member/profile', [
            'title' => 'My profile',
            'activeNav' => 'member-profile',
            'navContext' => 'member',
            'profile' => $user,
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
        $profile = $this->db()->fetch(
            'SELECT id FROM user_profiles WHERE user_id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($profile) {
            $this->db()->exec(
                'UPDATE user_profiles SET first_name = :fn, last_name = :ln, updated_at = :ua WHERE user_id = :id',
                ['fn' => $data['first_name'], 'ln' => $data['last_name'], 'ua' => $now, 'id' => $userId]
            );
        } else {
            $this->db()->insert(
                'INSERT INTO user_profiles (user_id, first_name, last_name, created_at, updated_at)
                 VALUES (:id, :fn, :ln, :ca, :ua)',
                [
                    'id' => $userId,
                    'fn' => $data['first_name'],
                    'ln' => $data['last_name'],
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }

        Session::flash('success', 'Profile updated.');
        return $this->redirect('/member/profile');
    }
}
