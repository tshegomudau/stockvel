<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\HttpException;
use App\Services\Auth\AuthService;
use App\Services\Documents\DocumentService;
use App\Services\Members\ProfileCompletenessService;
use App\Services\Storage\StorageManager;
use App\Support\SensitiveData;

final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        $sid = $this->requireStokvel();
        $user = $this->db()->fetch(
            'SELECT u.id, u.email, u.mobile,
                    p.first_name, p.last_name, p.id_number_encrypted,
                    p.address_line1, p.address_line2, p.suburb, p.city, p.province, p.postal_code,
                    p.emergency_contact_name, p.emergency_contact_phone
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.id = :id LIMIT 1',
            ['id' => $userId]
        );
        $idPlain = SensitiveData::decrypt($user['id_number_encrypted'] ?? null);

        $completeness = null;
        $membership = $this->db()->fetch(
            'SELECT id FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1',
            ['sid' => $sid, 'uid' => $userId]
        );
        if ($membership) {
            /** @var ProfileCompletenessService $profiler */
            $profiler = app()->container()->get(ProfileCompletenessService::class);
            $completeness = $profiler->forMembership($sid, (int) $membership['id']);
        }

        /** @var StorageManager $storage */
        $storage = app()->container()->get(StorageManager::class);
        $uploadsEnabled = $storage->uploadsEnabled();

        $idDocuments = [];
        try {
            $idDocuments = $this->db()->fetchAll(
                "SELECT d.id, d.title, d.created_at, dc.name AS category_name
                 FROM documents d
                 LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
                 WHERE d.stokvel_id = :sid
                   AND d.uploaded_by_user_id = :uid
                   AND d.status = 'active'
                   AND (
                        LOWER(COALESCE(d.category, '')) LIKE '%id%'
                     OR LOWER(COALESCE(d.title, '')) LIKE '%id%'
                     OR LOWER(COALESCE(d.title, '')) LIKE '%identity%'
                     OR LOWER(COALESCE(dc.code, '')) LIKE '%id%'
                     OR LOWER(COALESCE(dc.name, '')) LIKE '%id%'
                   )
                 ORDER BY d.created_at DESC LIMIT 10",
                ['sid' => $sid, 'uid' => $userId]
            );
        } catch (\Throwable) {
            $idDocuments = [];
        }

        $passwordPolicy = [];
        try {
            $passwordPolicy = app()->container()->get(\App\Services\Auth\PasswordPolicyService::class)->clientConfig();
        } catch (\Throwable) {
            $passwordPolicy = [];
        }

        return $this->render('member/profile', [
            'title' => 'My profile',
            'activeNav' => 'member-profile',
            'navContext' => 'member',
            'profile' => $user,
            'passwordPolicy' => $passwordPolicy,
            'idMasked' => SensitiveData::maskId($idPlain),
            'completeness' => $completeness,
            'uploadsEnabled' => $uploadsEnabled,
            'idDocuments' => $idDocuments,
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
            'address_line1' => trim((string) $request->input('address_line1', '')),
            'address_line2' => trim((string) $request->input('address_line2', '')),
            'suburb' => trim((string) $request->input('suburb', '')),
            'city' => trim((string) $request->input('city', '')),
            'province' => trim((string) $request->input('province', '')),
            'postal_code' => trim((string) $request->input('postal_code', '')),
            'emergency_contact_name' => trim((string) $request->input('emergency_contact_name', '')),
            'emergency_contact_phone' => trim((string) $request->input('emergency_contact_phone', '')),
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
        $addressParams = [
            'fn' => $data['first_name'],
            'ln' => $data['last_name'],
            'a1' => $data['address_line1'] ?: null,
            'a2' => $data['address_line2'] ?: null,
            'suburb' => $data['suburb'] ?: null,
            'city' => $data['city'] ?: null,
            'province' => $data['province'] ?: null,
            'postal' => $data['postal_code'] ?: null,
            'ecn' => $data['emergency_contact_name'] ?: null,
            'ecp' => $data['emergency_contact_phone'] ?: null,
            'ua' => $now,
            'id' => $userId,
        ];

        if ($profile) {
            if ($enc !== null) {
                $this->db()->exec(
                    'UPDATE user_profiles SET first_name = :fn, last_name = :ln,
                        address_line1 = :a1, address_line2 = :a2, suburb = :suburb, city = :city,
                        province = :province, postal_code = :postal,
                        emergency_contact_name = :ecn, emergency_contact_phone = :ecp,
                        id_number = NULL, id_number_encrypted = :enc, id_number_hash = :hash, updated_at = :ua
                     WHERE user_id = :id',
                    $addressParams + ['enc' => $enc, 'hash' => $hash]
                );
            } else {
                $this->db()->exec(
                    'UPDATE user_profiles SET first_name = :fn, last_name = :ln,
                        address_line1 = :a1, address_line2 = :a2, suburb = :suburb, city = :city,
                        province = :province, postal_code = :postal,
                        emergency_contact_name = :ecn, emergency_contact_phone = :ecp,
                        updated_at = :ua
                     WHERE user_id = :id',
                    $addressParams
                );
            }
        } else {
            $this->db()->insert(
                'INSERT INTO user_profiles (
                    user_id, first_name, last_name, id_number_encrypted, id_number_hash,
                    address_line1, address_line2, suburb, city, province, postal_code,
                    emergency_contact_name, emergency_contact_phone, created_at, updated_at
                 ) VALUES (
                    :id, :fn, :ln, :enc, :hash,
                    :a1, :a2, :suburb, :city, :province, :postal,
                    :ecn, :ecp, :ca, :ua
                 )',
                [
                    'id' => $userId,
                    'fn' => $data['first_name'],
                    'ln' => $data['last_name'],
                    'enc' => $enc,
                    'hash' => $hash,
                    'a1' => $data['address_line1'] ?: null,
                    'a2' => $data['address_line2'] ?: null,
                    'suburb' => $data['suburb'] ?: null,
                    'city' => $data['city'] ?: null,
                    'province' => $data['province'] ?: null,
                    'postal' => $data['postal_code'] ?: null,
                    'ecn' => $data['emergency_contact_name'] ?: null,
                    'ecp' => $data['emergency_contact_phone'] ?: null,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }

        Session::flash('success', 'Profile updated.');
        return $this->redirect('/member/profile');
    }

    public function uploadIdDocument(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        /** @var StorageManager $storage */
        $storage = app()->container()->get(StorageManager::class);
        if (!$storage->uploadsEnabled()) {
            Session::flash('error', 'File uploads are disabled. Ask the platform admin to enable them under Features & storage.');
            return $this->redirect('/member/profile');
        }

        $file = $request->file('id_document');
        if ($file === null) {
            Session::flash('error', 'Choose an ID document file to upload.');
            return $this->redirect('/member/profile');
        }

        $title = trim((string) $request->input('title', 'ID document'));
        if ($title === '') {
            $title = 'ID document';
        }

        $categoryId = $this->ensureIdentityCategory($sid);

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $documents->upload($file, $title, null, $userId, 'id_document', $categoryId);
            Session::flash('success', 'ID document uploaded.');
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Could not upload ID document.');
        }

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
        if ($password !== $confirm) {
            Session::flash('error', 'New passwords must match.');
            return $this->redirect('/member/profile');
        }
        $policyErrors = app()->container()->get(\App\Services\Auth\PasswordPolicyService::class)->validate($password);
        if ($policyErrors !== []) {
            Session::flash('error', $policyErrors[0]);
            return $this->redirect('/member/profile');
        }

        $this->db()->exec(
            'UPDATE users SET password_hash = :ph, must_change_password = 0, updated_at = :ua WHERE id = :id',
            ['ph' => AuthService::hashPassword($password), 'ua' => now_utc(), 'id' => $userId]
        );
        Session::flash('success', 'Password updated.');
        return $this->redirect('/member/profile');
    }

    private function ensureIdentityCategory(int $stokvelId): ?int
    {
        try {
            $existing = $this->db()->fetch(
                "SELECT id FROM document_categories
                 WHERE stokvel_id = :sid AND is_active = 1
                   AND (LOWER(code) LIKE '%id%' OR LOWER(name) LIKE '%id%' OR LOWER(code) LIKE '%identity%')
                 ORDER BY id ASC LIMIT 1",
                ['sid' => $stokvelId]
            );
            if ($existing) {
                return (int) $existing['id'];
            }
            $now = now_utc();
            return (int) $this->db()->insert(
                'INSERT INTO document_categories (stokvel_id, code, name, is_active, created_at, updated_at)
                 VALUES (:sid, :code, :name, 1, :ca, :ua)',
                ['sid' => $stokvelId, 'code' => 'id_document', 'name' => 'ID / identity', 'ca' => $now, 'ua' => $now]
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
