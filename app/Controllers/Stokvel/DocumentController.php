<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\HttpException;
use App\Services\Documents\DocumentService;
use App\Support\HtmlSanitizer;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();
        $categoryFilter = (int) $request->query('category_id', 0);
        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $allowedStatuses = ['active'];

        $params = ['sid' => $sid];
        $where = "d.stokvel_id = :sid AND d.status <> 'deleted'";
        if ($categoryFilter > 0) {
            $where .= ' AND d.document_category_id = :cat';
            $params['cat'] = $categoryFilter;
        }
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where .= ' AND d.status = :status';
            $params['status'] = $status;
        }
        if ($q !== '') {
            $where .= ' AND (d.title LIKE :q_title OR d.document_ref LIKE :q_ref OR d.category LIKE :q_cat)';
            $like = '%' . $q . '%';
            $params['q_title'] = $like;
            $params['q_ref'] = $like;
            $params['q_cat'] = $like;
        }

        $documents = $this->db()->fetchAll(
            "SELECT d.*, dc.name AS category_name, dc.code AS category_code
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
             WHERE {$where}
             ORDER BY d.created_at DESC LIMIT 100",
            $params
        );
        $filterCategories = $this->db()->fetchAll(
            'SELECT id, name FROM document_categories WHERE stokvel_id = :sid AND is_active = 1 ORDER BY name ASC',
            ['sid' => $sid]
        );

        return $this->render('stokvel/documents/index', [
            'title' => 'Documents',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'documents' => $documents,
            'filterCategories' => $filterCategories,
            'filterCategoryId' => $categoryFilter,
            'filterStatus' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : '',
            'filterQ' => $q,
            'statusOptions' => $allowedStatuses,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();
        $this->ensureDocumentCategories($sid);
        return $this->render('stokvel/documents/create', [
            'title' => 'New document',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'categories' => $this->activeDocumentCategories($sid),
            'meetings' => $this->meetingOptions($sid),
        ]);
    }

    public function storeTyped(Request $request): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();
        $this->ensureDocumentCategories($sid);
        $categoryMeta = $this->resolveDocumentCategory($sid, $request);
        if ($categoryMeta['id'] === null) {
            $this->flashOld($request);
            Session::flash('error', 'Select an active document category.');
            return $this->redirect('/app/documents/create');
        }
        $meetingId = $this->resolveMeetingId($sid, $request);
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'body_html' => (string) $request->input('body_html', ''),
            'category' => $categoryMeta['legacy'],
            'document_category_id' => $categoryMeta['id'],
        ];
        $validator = Validator::make($data, ['title' => 'required|min:2']);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Title is required.');
            return $this->redirect('/app/documents/create');
        }

        $safeHtml = $this->sanitizeHtml($data['body_html']);
        $now = now_utc();
        $ref = 'DOC-' . strtoupper(bin2hex(random_bytes(4)));
        $id = (int) $this->db()->insert(
            'INSERT INTO documents
                (stokvel_id, document_ref, title, category, document_category_id, meeting_id, status, body_html, content_type, uploaded_by_user_id, created_at, updated_at)
             VALUES
                (:sid, :ref, :title, :cat, :dcat, :mid, :status, :body, :ctype, :by, :ca, :ua)',
            [
                'sid' => $sid,
                'ref' => $ref,
                'title' => $data['title'],
                'cat' => $data['category'] ?: 'general',
                'dcat' => $data['document_category_id'],
                'mid' => $meetingId,
                'status' => 'active',
                'body' => $safeHtml,
                'ctype' => 'typed',
                'by' => $this->userId(),
                'ca' => $now,
                'ua' => $now,
            ]
        );

        $this->notifyMembers($sid, 'document', 'New document published', $data['title']);
        Session::flash('success', 'Document saved. File uploads are a paid Phase 2 feature.');
        return $this->redirect('/app/documents/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();
        $doc = $this->db()->fetch(
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND status <> 'deleted' LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            return $this->redirect('/app/documents');
        }
        return $this->render('stokvel/documents/show', [
            'title' => (string) $doc['title'],
            'activeNav' => 'documents',
            'navContext' => 'app',
            'document' => $doc,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();
        $doc = $this->db()->fetch(
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND content_type = 'typed' LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            return $this->redirect('/app/documents');
        }
        return $this->render('stokvel/documents/create', [
            'title' => 'Edit document',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'document' => $doc,
            'categories' => $this->activeDocumentCategories($sid),
            'meetings' => $this->meetingOptions($sid),
        ]);
    }

    public function updateTyped(Request $request, string $id): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();
        $title = trim((string) $request->input('title', ''));
        $body = $this->sanitizeHtml((string) $request->input('body_html', ''));
        if ($title === '') {
            Session::flash('error', 'Title is required.');
            return $this->redirect('/app/documents/' . $id . '/edit');
        }
        $categoryMeta = $this->resolveDocumentCategory($sid, $request);
        $meetingId = $this->resolveMeetingId($sid, $request);
        $this->db()->exec(
            "UPDATE documents SET title = :title, body_html = :body, category = :cat, document_category_id = :dcat,
                meeting_id = :mid, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND content_type = 'typed'",
            [
                'title' => $title,
                'body' => $body,
                'cat' => $categoryMeta['legacy'],
                'dcat' => $categoryMeta['id'],
                'mid' => $meetingId,
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
            ]
        );
        Session::flash('success', 'Document updated.');
        return $this->redirect('/app/documents/' . $id);
    }

    public function download(Request $request, string $id): Response
    {
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();
        $doc = $this->db()->fetch(
            'SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if ($doc && ($doc['content_type'] ?? '') === 'typed') {
            return $this->redirect('/app/documents/' . $id);
        }

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $resolved = $documents->resolveDownloadPath((int) $id, 1, $sid);
            return Response::download(
                $resolved['path'],
                (string) ($resolved['document']['title'] ?? $resolved['file_name']),
                (string) $resolved['mime_type']
            );
        } catch (HttpException) {
            Session::flash('error', 'File upload downloads are a Phase 2 paid feature. Use typed documents for now.');
            return $this->redirect('/app/documents');
        }
    }

    /** @return list<array<string, mixed>> */
    private function activeDocumentCategories(int $stokvelId): array
    {
        return $this->db()->fetchAll(
            'SELECT id, code, name FROM document_categories WHERE stokvel_id = :sid AND is_active = 1 ORDER BY name ASC',
            ['sid' => $stokvelId]
        );
    }

    /** Seed starter document categories if the stokvel has none (tenant-owned thereafter). */
    private function ensureDocumentCategories(int $stokvelId): void
    {
        $count = (int) ($this->db()->fetch(
            'SELECT COUNT(*) AS c FROM document_categories WHERE stokvel_id = :sid',
            ['sid' => $stokvelId]
        )['c'] ?? 0);
        if ($count > 0) {
            return;
        }
        $now = now_utc();
        $starters = [
            ['general', 'General'],
            ['constitution', 'Constitution'],
            ['minutes', 'Meeting minutes'],
            ['policy', 'Policy'],
            ['financial', 'Financial report'],
        ];
        foreach ($starters as [$code, $name]) {
            $this->db()->insert(
                'INSERT INTO document_categories (stokvel_id, code, name, is_active, created_at, updated_at)
                 VALUES (:sid, :code, :name, 1, :ca, :ua)',
                ['sid' => $stokvelId, 'code' => $code, 'name' => $name, 'ca' => $now, 'ua' => $now]
            );
        }
    }

    /** @return array{id: ?int, legacy: string} */
    private function resolveDocumentCategory(int $stokvelId, Request $request): array
    {
        $categoryId = (int) $request->input('document_category_id', 0);
        if ($categoryId > 0) {
            $row = $this->db()->fetch(
                'SELECT id, code, name FROM document_categories
                 WHERE id = :id AND stokvel_id = :sid AND is_active = 1 LIMIT 1',
                ['id' => $categoryId, 'sid' => $stokvelId]
            );
            if ($row) {
                return [
                    'id' => (int) $row['id'],
                    'legacy' => (string) ($row['code'] ?? $row['name'] ?? 'general'),
                ];
            }
        }

        return ['id' => null, 'legacy' => 'general'];
    }

    private function sanitizeHtml(string $html): string
    {
        return HtmlSanitizer::clean($html);
    }

    /** @return list<array{id: int, title: string, scheduled_at: ?string}> */
    private function meetingOptions(int $stokvelId): array
    {
        try {
            return $this->db()->fetchAll(
                'SELECT id, title, scheduled_at FROM meetings
                 WHERE stokvel_id = :sid
                 ORDER BY scheduled_at DESC
                 LIMIT 100',
                ['sid' => $stokvelId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveMeetingId(int $stokvelId, Request $request): ?int
    {
        $meetingId = (int) $request->input('meeting_id', 0);
        if ($meetingId < 1) {
            return null;
        }
        $row = $this->db()->fetch(
            'SELECT id FROM meetings WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $meetingId, 'sid' => $stokvelId]
        );

        return $row ? $meetingId : null;
    }

    private function notifyMembers(int $stokvelId, string $type, string $title, string $body): void
    {
        try {
            $members = $this->db()->fetchAll(
                "SELECT user_id FROM memberships WHERE stokvel_id = :sid AND status = 'active'",
                ['sid' => $stokvelId]
            );
            $now = now_utc();
            foreach ($members as $m) {
                $this->db()->insert(
                    'INSERT INTO notifications
                        (stokvel_id, user_id, channel, notification_type, title, body, status, created_at, updated_at)
                     VALUES
                        (:sid, :uid, :ch, :type, :title, :body, :st, :ca, :ua)',
                    [
                        'sid' => $stokvelId,
                        'uid' => (int) $m['user_id'],
                        'ch' => 'in_app',
                        'type' => $type,
                        'title' => $title,
                        'body' => $body,
                        'st' => 'unread',
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
            }
        } catch (\Throwable) {
            // Notification schema variants should not block document save.
        }
    }
}
