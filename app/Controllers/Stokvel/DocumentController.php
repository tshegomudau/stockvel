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
use App\Services\Storage\StorageManager;
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
        /** @var StorageManager $storage */
        $storage = app()->container()->get(StorageManager::class);
        return $this->render('stokvel/documents/create', [
            'title' => 'New document',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'categories' => $this->activeDocumentCategories($sid),
            'meetings' => $this->meetingOptions($sid),
            'uploadsEnabled' => $storage->uploadsEnabled(),
        ]);
    }

    public function store(Request $request): Response
    {
        $mode = trim((string) $request->input('content_mode', 'typed'));
        if ($mode === 'file') {
            return $this->storeFile($request);
        }

        return $this->storeTyped($request);
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
        Session::flash('success', 'Document saved.');
        return $this->redirect('/app/documents/' . $id);
    }

    public function storeFile(Request $request): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();
        $this->ensureDocumentCategories($sid);

        /** @var StorageManager $storage */
        $storage = app()->container()->get(StorageManager::class);
        if (!$storage->uploadsEnabled()) {
            Session::flash('error', 'File uploads are disabled. Ask the platform admin to enable Features & storage.');
            return $this->redirect('/app/documents/create');
        }

        $categoryMeta = $this->resolveDocumentCategory($sid, $request);
        if ($categoryMeta['id'] === null) {
            $this->flashOld($request);
            Session::flash('error', 'Select an active document category.');
            return $this->redirect('/app/documents/create');
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Title is required.');
            return $this->redirect('/app/documents/create');
        }

        $file = $request->file('upload_file');
        if ($file === null) {
            Session::flash('error', 'Choose a file to upload.');
            return $this->redirect('/app/documents/create');
        }

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $result = $documents->upload(
                $file,
                $title,
                null,
                (int) $this->userId(),
                $categoryMeta['legacy'],
                $categoryMeta['id']
            );
            $meetingId = $this->resolveMeetingId($sid, $request);
            if ($meetingId !== null) {
                $this->db()->exec(
                    'UPDATE documents SET meeting_id = :mid, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                    ['mid' => $meetingId, 'ua' => now_utc(), 'id' => $result['document_id'], 'sid' => $sid]
                );
            }
            // File uploads are private to the uploader + admins with documents.view — do not broadcast.
            Session::flash('success', 'File uploaded. Only you and admins with document access can view it.');
            return $this->redirect('/app/documents/' . $result['document_id']);
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/documents/create');
        }
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
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND status <> 'deleted' LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            return $this->redirect('/app/documents');
        }
        /** @var StorageManager $storage */
        $storage = app()->container()->get(StorageManager::class);
        return $this->render('stokvel/documents/create', [
            'title' => 'Edit document',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'document' => $doc,
            'categories' => $this->activeDocumentCategories($sid),
            'meetings' => $this->meetingOptions($sid),
            'uploadsEnabled' => $storage->uploadsEnabled(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();
        $doc = $this->db()->fetch(
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND status <> 'deleted' LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            Session::flash('error', 'Document not found.');
            return $this->redirect('/app/documents');
        }

        if ((string) ($doc['content_type'] ?? '') === 'typed') {
            return $this->updateTyped($request, $id);
        }

        return $this->updateFile($request, $id);
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
        $affected = $this->db()->exec(
            "UPDATE documents SET title = :title, body_html = :body, category = :cat, document_category_id = :dcat,
                meeting_id = :mid, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND content_type = 'typed' AND status <> 'deleted'",
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
        if ($affected < 1) {
            Session::flash('error', 'Document not found.');
            return $this->redirect('/app/documents');
        }
        Session::flash('success', 'Document updated.');
        return $this->redirect('/app/documents/' . $id);
    }

    public function updateFile(Request $request, string $id): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();
        $categoryMeta = $this->resolveDocumentCategory($sid, $request);
        $meetingId = $this->resolveMeetingId($sid, $request);
        $file = $request->file('upload_file');

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $documents->updateFileDocument(
                (int) $id,
                $sid,
                (int) $this->userId(),
                trim((string) $request->input('title', '')),
                $categoryMeta['legacy'],
                $categoryMeta['id'],
                $meetingId,
                $file,
                true
            );
            Session::flash('success', 'Document updated.');
            return $this->redirect('/app/documents/' . $id);
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/documents/' . $id . '/edit');
        }
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $documents->softDelete((int) $id, $sid, (int) $this->userId(), true);
            Session::flash('success', 'Document deleted.');
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/documents/' . $id);
        }

        return $this->redirect('/app/documents');
    }

    public function download(Request $request, string $id): Response
    {
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();
        $doc = $this->db()->fetch(
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND status <> 'deleted' LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            Session::flash('error', 'Document not found.');
            return $this->redirect('/app/documents');
        }
        if (($doc['content_type'] ?? '') === 'typed') {
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
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Download failed.');
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
            ['id_document', 'ID / identity'],
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
