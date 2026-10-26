<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\HtmlSanitizer;

final class ConstitutionController extends Controller
{
    public function show(Request $request): Response
    {
        $this->requirePermission('constitution.manage');
        $sid = $this->requireStokvel();
        $constitution = $this->ensureConstitution($sid);
        $current = null;
        if (!empty($constitution['current_version_id'])) {
            $current = $this->db()->fetch(
                'SELECT * FROM constitution_versions WHERE id = :id AND stokvel_id = :sid LIMIT 1',
                ['id' => (int) $constitution['current_version_id'], 'sid' => $sid]
            );
        }
        $versions = $this->db()->fetchAll(
            'SELECT id, version_number, status, effective_from, approved_at, created_at
             FROM constitution_versions
             WHERE constitution_id = :cid AND stokvel_id = :sid
             ORDER BY version_number DESC',
            ['cid' => (int) $constitution['id'], 'sid' => $sid]
        );
        $draft = $this->db()->fetch(
            "SELECT * FROM constitution_versions
             WHERE constitution_id = :cid AND stokvel_id = :sid AND status = 'draft'
             ORDER BY version_number DESC LIMIT 1",
            ['cid' => (int) $constitution['id'], 'sid' => $sid]
        );

        return $this->render('stokvel/constitution/show', [
            'title' => 'Constitution',
            'activeNav' => 'constitution',
            'navContext' => 'app',
            'constitution' => $constitution,
            'current' => $current,
            'draft' => $draft,
            'versions' => $versions,
        ]);
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('constitution.manage');
        $sid = $this->requireStokvel();
        $constitution = $this->ensureConstitution($sid);
        $draft = $this->ensureDraft($sid, $constitution);

        return $this->render('stokvel/constitution/edit', [
            'title' => 'Edit constitution draft',
            'activeNav' => 'constitution',
            'navContext' => 'app',
            'constitution' => $constitution,
            'draft' => $draft,
        ]);
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('constitution.manage');
        $sid = $this->requireStokvel();
        $constitution = $this->ensureConstitution($sid);
        $draft = $this->ensureDraft($sid, $constitution);

        $title = trim((string) $request->input('title', ''));
        $body = HtmlSanitizer::clean((string) $request->input('body_html', ''));
        if ($body === '') {
            $this->flashOld($request);
            Session::flash('error', 'Constitution body is required.');
            return $this->redirect('/app/constitution/edit');
        }

        $now = now_utc();
        if ($title !== '') {
            $this->db()->exec(
                'UPDATE constitutions SET title = :title, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                ['title' => $title, 'ua' => $now, 'id' => (int) $constitution['id'], 'sid' => $sid]
            );
        }
        $this->db()->exec(
            'UPDATE constitution_versions SET body_html = :body WHERE id = :id AND stokvel_id = :sid',
            ['body' => $body, 'id' => (int) $draft['id'], 'sid' => $sid]
        );
        $this->db()->exec(
            'UPDATE constitutions SET updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['ua' => $now, 'id' => (int) $constitution['id'], 'sid' => $sid]
        );

        Session::flash('success', 'Draft saved.');
        return $this->redirect('/app/constitution/edit');
    }

    public function publish(Request $request): Response
    {
        $this->requirePermission('constitution.manage');
        $sid = $this->requireStokvel();
        $constitution = $this->ensureConstitution($sid);
        $draft = $this->db()->fetch(
            "SELECT * FROM constitution_versions
             WHERE constitution_id = :cid AND stokvel_id = :sid AND status = 'draft'
             ORDER BY version_number DESC LIMIT 1",
            ['cid' => (int) $constitution['id'], 'sid' => $sid]
        );
        if (!$draft || trim((string) ($draft['body_html'] ?? '')) === '') {
            Session::flash('error', 'Save a draft with content before publishing.');
            return $this->redirect('/app/constitution/edit');
        }

        $effectiveFrom = trim((string) $request->input('effective_from', date('Y-m-d')));
        if ($effectiveFrom === '') {
            $effectiveFrom = date('Y-m-d');
        }
        $now = now_utc();

        $this->db()->transaction(function () use ($sid, $constitution, $draft, $effectiveFrom, $now): void {
            $this->db()->exec(
                "UPDATE constitution_versions
                 SET status = 'published', effective_from = :ef, approved_at = :aa, approved_by_user_id = :uid
                 WHERE id = :id AND stokvel_id = :sid",
                [
                    'ef' => $effectiveFrom,
                    'aa' => $now,
                    'uid' => (int) $this->userId(),
                    'id' => (int) $draft['id'],
                    'sid' => $sid,
                ]
            );
            $this->db()->exec(
                "UPDATE constitutions
                 SET status = 'published', current_version_id = :vid, updated_at = :ua
                 WHERE id = :id AND stokvel_id = :sid",
                [
                    'vid' => (int) $draft['id'],
                    'ua' => $now,
                    'id' => (int) $constitution['id'],
                    'sid' => $sid,
                ]
            );
        });

        Session::flash('success', 'Constitution version published.');
        return $this->redirect('/app/constitution');
    }

    private function ensureConstitution(int $stokvelId): array
    {
        $row = $this->db()->fetch(
            'SELECT * FROM constitutions WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        if ($row) {
            return $row;
        }
        $now = now_utc();
        $id = $this->db()->insert(
            'INSERT INTO constitutions (stokvel_id, title, status, created_at, updated_at)
             VALUES (:sid, :title, :st, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'title' => 'Stokvel Constitution',
                'st' => 'draft',
                'ca' => $now,
                'ua' => $now,
            ]
        );
        return $this->db()->fetch(
            'SELECT * FROM constitutions WHERE id = :id LIMIT 1',
            ['id' => $id]
        ) ?? [];
    }

    /** @param array<string, mixed> $constitution */
    private function ensureDraft(int $stokvelId, array $constitution): array
    {
        $draft = $this->db()->fetch(
            "SELECT * FROM constitution_versions
             WHERE constitution_id = :cid AND stokvel_id = :sid AND status = 'draft'
             ORDER BY version_number DESC LIMIT 1",
            ['cid' => (int) $constitution['id'], 'sid' => $stokvelId]
        );
        if ($draft) {
            return $draft;
        }

        $max = (int) ($this->db()->fetch(
            'SELECT COALESCE(MAX(version_number), 0) AS m FROM constitution_versions
             WHERE constitution_id = :cid',
            ['cid' => (int) $constitution['id']]
        )['m'] ?? 0);

        $body = '';
        if (!empty($constitution['current_version_id'])) {
            $current = $this->db()->fetch(
                'SELECT body_html FROM constitution_versions WHERE id = :id LIMIT 1',
                ['id' => (int) $constitution['current_version_id']]
            );
            $body = (string) ($current['body_html'] ?? '');
        }

        $id = $this->db()->insert(
            'INSERT INTO constitution_versions
                (stokvel_id, constitution_id, version_number, body_html, status, created_by_user_id, created_at)
             VALUES
                (:sid, :cid, :ver, :body, :st, :uid, :ca)',
            [
                'sid' => $stokvelId,
                'cid' => (int) $constitution['id'],
                'ver' => $max + 1,
                'body' => $body,
                'st' => 'draft',
                'uid' => (int) $this->userId(),
                'ca' => now_utc(),
            ]
        );

        return $this->db()->fetch(
            'SELECT * FROM constitution_versions WHERE id = :id LIMIT 1',
            ['id' => $id]
        ) ?? [];
    }
}
