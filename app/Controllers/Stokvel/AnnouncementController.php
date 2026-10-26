<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Support\HtmlSanitizer;

final class AnnouncementController extends Controller
{
    /** @var list<string> */
    private const ROLE_CODES = [
        'chairperson',
        'treasurer',
        'secretary',
        'committee_member',
        'ordinary_member',
        'auditor',
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('announcements.manage');
        $sid = $this->requireStokvel();
        $this->promoteDueScheduled($sid);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $status = trim((string) $request->query('status', ''));

        $where = 'a.stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, ['draft', 'scheduled', 'published', 'expired'], true)) {
            $where .= ' AND a.status = :status';
            $params['status'] = $status;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' ? $status : null,
        ], static fn ($v) => $v !== null);

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM announcements a WHERE ' . $where,
            'SELECT a.*,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS author_name
             FROM announcements a
             LEFT JOIN users u ON u.id = a.created_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ' . $where . ' ORDER BY a.created_at DESC',
            $params,
            $page,
            $perPage,
            url('/app/announcements'),
            $filterQuery
        );

        return $this->render('stokvel/announcements/index', [
            'title' => 'Announcements',
            'activeNav' => 'announcements',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterStatus' => $status,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('announcements.manage');
        $this->requireStokvel();
        return $this->render('stokvel/announcements/form', [
            'title' => 'New announcement',
            'activeNav' => 'announcements',
            'navContext' => 'app',
            'item' => null,
            'roleOptions' => $this->roleOptions(),
            'selectedRoles' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('announcements.manage');
        $sid = $this->requireStokvel();
        $payload = $this->validatedPayload($request);
        if ($payload === null) {
            return $this->redirect('/app/announcements/create');
        }

        $now = now_utc();
        $id = $this->db()->insert(
            'INSERT INTO announcements
                (stokvel_id, title, body_html, priority, audience, status, publish_at, expire_at,
                 created_by_user_id, created_at, updated_at)
             VALUES
                (:sid, :title, :body, :pri, :aud, :st, :pa, :ea, :uid, :ca, :ua)',
            [
                'sid' => $sid,
                'title' => $payload['title'],
                'body' => $payload['body_html'],
                'pri' => $payload['priority'],
                'aud' => $payload['audience'],
                'st' => $payload['status'],
                'pa' => $payload['publish_at'],
                'ea' => $payload['expire_at'],
                'uid' => (int) $this->userId(),
                'ca' => $now,
                'ua' => $now,
            ]
        );

        Session::flash('success', $this->successMessage($payload['status']));
        return $this->redirect('/app/announcements/' . $id);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('announcements.manage');
        $sid = $this->requireStokvel();
        $item = $this->findAnnouncement($sid, (int) $id);
        if ($item === null) {
            Session::flash('error', 'Announcement not found.');
            return $this->redirect('/app/announcements');
        }
        if (!in_array((string) ($item['status'] ?? ''), ['draft', 'scheduled'], true)) {
            Session::flash('error', 'Only draft or scheduled announcements can be edited.');
            return $this->redirect('/app/announcements/' . (int) $id);
        }

        return $this->render('stokvel/announcements/form', [
            'title' => 'Edit announcement',
            'activeNav' => 'announcements',
            'navContext' => 'app',
            'item' => $item,
            'roleOptions' => $this->roleOptions(),
            'selectedRoles' => $this->decodeAudienceRoles((string) ($item['audience'] ?? 'all')),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('announcements.manage');
        $sid = $this->requireStokvel();
        $item = $this->findAnnouncement($sid, (int) $id);
        if ($item === null) {
            Session::flash('error', 'Announcement not found.');
            return $this->redirect('/app/announcements');
        }
        if (!in_array((string) ($item['status'] ?? ''), ['draft', 'scheduled'], true)) {
            Session::flash('error', 'Only draft or scheduled announcements can be edited.');
            return $this->redirect('/app/announcements/' . (int) $id);
        }

        $payload = $this->validatedPayload($request);
        if ($payload === null) {
            return $this->redirect('/app/announcements/' . (int) $id . '/edit');
        }

        $this->db()->exec(
            'UPDATE announcements
             SET title = :title, body_html = :body, priority = :pri, audience = :aud,
                 status = :st, publish_at = :pa, expire_at = :ea, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'title' => $payload['title'],
                'body' => $payload['body_html'],
                'pri' => $payload['priority'],
                'aud' => $payload['audience'],
                'st' => $payload['status'],
                'pa' => $payload['publish_at'],
                'ea' => $payload['expire_at'],
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
            ]
        );

        Session::flash('success', $this->successMessage($payload['status']));
        return $this->redirect('/app/announcements/' . (int) $id);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('announcements.manage');
        $sid = $this->requireStokvel();
        $this->promoteDueScheduled($sid);
        $item = $this->findAnnouncement($sid, (int) $id);
        if ($item === null) {
            Session::flash('error', 'Announcement not found.');
            return $this->redirect('/app/announcements');
        }

        $readCount = (int) ($this->db()->fetch(
            'SELECT COUNT(*) AS c FROM announcement_reads WHERE announcement_id = :id AND stokvel_id = :sid',
            ['id' => (int) $id, 'sid' => $sid]
        )['c'] ?? 0);

        return $this->render('stokvel/announcements/show', [
            'title' => (string) ($item['title'] ?? 'Announcement'),
            'activeNav' => 'announcements',
            'navContext' => 'app',
            'item' => $item,
            'readCount' => $readCount,
            'audienceLabel' => $this->audienceLabel((string) ($item['audience'] ?? 'all')),
        ]);
    }

    public function publish(Request $request, string $id): Response
    {
        $this->requirePermission('announcements.manage');
        $sid = $this->requireStokvel();
        $item = $this->findAnnouncement($sid, (int) $id);
        if ($item === null) {
            Session::flash('error', 'Announcement not found.');
            return $this->redirect('/app/announcements');
        }
        if (($item['status'] ?? '') === 'published') {
            Session::flash('success', 'Already published.');
            return $this->redirect('/app/announcements/' . (int) $id);
        }

        $now = now_utc();
        $this->db()->exec(
            "UPDATE announcements
             SET status = 'published', publish_at = COALESCE(publish_at, :pa), updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid",
            ['pa' => $now, 'ua' => $now, 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Announcement published.');
        return $this->redirect('/app/announcements/' . (int) $id);
    }

    /**
     * @return array{title:string,body_html:string,priority:string,audience:string,status:string,publish_at:?string,expire_at:?string}|null
     */
    private function validatedPayload(Request $request): ?array
    {
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'body_html' => (string) $request->input('body_html', ''),
            'priority' => trim((string) $request->input('priority', 'normal')),
        ];
        if (!in_array($data['priority'], ['low', 'normal', 'high', 'urgent'], true)) {
            $data['priority'] = 'normal';
        }

        $validator = Validator::make($data, [
            'title' => 'required|min:2',
            'body_html' => 'required|min:1',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Title and body are required.');
            return null;
        }

        $audienceMode = trim((string) $request->input('audience_mode', 'all'));
        $rolesInput = $request->input('audience_roles', []);
        if (!is_array($rolesInput)) {
            $rolesInput = [];
        }
        $roles = array_values(array_intersect(
            array_values(array_unique(array_filter(array_map(
                static fn ($v) => strtolower(trim((string) $v)),
                $rolesInput
            )))),
            self::ROLE_CODES
        ));

        if ($audienceMode === 'roles') {
            if ($roles === []) {
                $this->flashOld($request);
                Session::flash('error', 'Select at least one role for the audience.');
                return null;
            }
            $audience = json_encode($roles, JSON_THROW_ON_ERROR);
        } else {
            $audience = 'all';
        }

        $action = trim((string) $request->input('action', 'draft'));
        $scheduleAt = trim((string) $request->input('schedule_at', ''));
        $expireAt = trim((string) $request->input('expire_at', ''));
        $now = now_utc();

        $status = 'draft';
        $publishAt = null;
        if ($action === 'publish') {
            $status = 'published';
            $publishAt = $now;
        } elseif ($action === 'schedule') {
            if ($scheduleAt === '' || strtotime($scheduleAt) === false) {
                $this->flashOld($request);
                Session::flash('error', 'Choose a valid schedule date and time.');
                return null;
            }
            $publishAt = date('Y-m-d H:i:s', strtotime($scheduleAt));
            if ($publishAt <= $now) {
                $status = 'published';
            } else {
                $status = 'scheduled';
            }
        }

        $expireAtNorm = null;
        if ($expireAt !== '' && strtotime($expireAt) !== false) {
            $expireAtNorm = date('Y-m-d H:i:s', strtotime($expireAt));
        }

        return [
            'title' => $data['title'],
            'body_html' => HtmlSanitizer::clean($data['body_html']),
            'priority' => $data['priority'],
            'audience' => $audience,
            'status' => $status,
            'publish_at' => $publishAt,
            'expire_at' => $expireAtNorm,
        ];
    }

    /** @return array<string, mixed>|null */
    private function findAnnouncement(int $stokvelId, int $id): ?array
    {
        $row = $this->db()->fetch(
            'SELECT a.*,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS author_name
             FROM announcements a
             LEFT JOIN users u ON u.id = a.created_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE a.id = :id AND a.stokvel_id = :sid LIMIT 1',
            ['id' => $id, 'sid' => $stokvelId]
        );

        return $row ?: null;
    }

    private function promoteDueScheduled(int $stokvelId): void
    {
        $this->db()->exec(
            "UPDATE announcements
             SET status = 'published', updated_at = :ua
             WHERE stokvel_id = :sid AND status = 'scheduled'
               AND publish_at IS NOT NULL AND publish_at <= :now",
            ['ua' => now_utc(), 'sid' => $stokvelId, 'now' => now_utc()]
        );
    }

    /** @return list<array{code:string,name:string}> */
    private function roleOptions(): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT code, name FROM roles
             WHERE stokvel_id IS NULL AND code IN ('" . implode("','", self::ROLE_CODES) . "')
             ORDER BY FIELD(code,'" . implode("','", self::ROLE_CODES) . "')"
        );
        if ($rows !== []) {
            return array_map(
                static fn (array $r): array => [
                    'code' => (string) $r['code'],
                    'name' => (string) ($r['name'] ?? $r['code']),
                ],
                $rows
            );
        }

        return array_map(
            static fn (string $code): array => [
                'code' => $code,
                'name' => ucwords(str_replace('_', ' ', $code)),
            ],
            self::ROLE_CODES
        );
    }

    /** @return list<string> */
    private function decodeAudienceRoles(string $audience): array
    {
        if ($audience === '' || $audience === 'all' || in_array($audience, ['members', 'admins'], true)) {
            return [];
        }
        $decoded = json_decode($audience, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_intersect(
            array_map(static fn ($v) => strtolower(trim((string) $v)), $decoded),
            self::ROLE_CODES
        ));
    }

    private function audienceLabel(string $audience): string
    {
        if ($audience === 'all' || $audience === '') {
            return 'Everyone';
        }
        if ($audience === 'members') {
            return 'Members (legacy)';
        }
        if ($audience === 'admins') {
            return 'Admins (legacy)';
        }
        $roles = $this->decodeAudienceRoles($audience);
        if ($roles === []) {
            return $audience;
        }
        $labels = $this->roleOptions();
        $map = [];
        foreach ($labels as $row) {
            $map[$row['code']] = $row['name'];
        }

        return implode(', ', array_map(static fn (string $c) => $map[$c] ?? $c, $roles));
    }

    private function successMessage(string $status): string
    {
        return match ($status) {
            'published' => 'Announcement published.',
            'scheduled' => 'Announcement scheduled.',
            default => 'Announcement saved as draft.',
        };
    }
}
