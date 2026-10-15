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

        $this->db()->exec(
            'UPDATE users SET first_name = :fn, last_name = :ln, mobile = :mobile, updated_at = :ua WHERE id = :id',
            ['fn' => $data['first_name'], 'ln' => $data['last_name'], 'mobile' => $data['mobile'] ?: null, 'ua' => now_utc(), 'id' => $userId]
        );

        Session::flash('success', 'Profile updated.');
        return $this->redirect('/member/profile');
    }
}
