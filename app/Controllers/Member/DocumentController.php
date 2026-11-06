<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\Documents\DocumentService;
use App\Services\Storage\StorageManager;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        /** @var DocumentService $documents */
        $documents = app()->container()->get(DocumentService::class);
        $visibility = $documents->memberVisibilitySql('d');

        $rows = $this->db()->fetchAll(
            "SELECT d.*, dc.name AS category_name
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
             WHERE d.stokvel_id = :sid AND d.status = 'active' AND {$visibility}
             ORDER BY d.created_at DESC LIMIT 100",
            ['sid' => $sid, 'doc_uid' => $userId]
        );

        return $this->render('member/documents/index', [
            'title' => 'Documents',
            'activeNav' => 'member-documents',
            'navContext' => 'member',
            'documents' => $rows,
            'currentUserId' => $userId,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        $doc = $this->loadAccessible($sid, (int) $id, $userId);
        if ($doc === null) {
            Session::flash('error', 'Document not found.');
            return $this->redirect('/member/documents');
        }

        return $this->render('member/documents/show', [
            'title' => (string) ($doc['title'] ?? 'Document'),
            'activeNav' => 'member-documents',
            'navContext' => 'member',
            'document' => $doc,
            'canManage' => $this->memberOwnsFile($doc, $userId),
            'uploadsEnabled' => app()->container()->get(StorageManager::class)->uploadsEnabled(),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $doc = $this->loadAccessible($sid, (int) $id, $userId);
        if ($doc === null || !$this->memberOwnsFile($doc, $userId)) {
            Session::flash('error', 'Document not found.');
            return $this->redirect('/member/documents');
        }

        return $this->render('member/documents/edit', [
            'title' => 'Update document',
            'activeNav' => 'member-documents',
            'navContext' => 'member',
            'document' => $doc,
            'uploadsEnabled' => app()->container()->get(StorageManager::class)->uploadsEnabled(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $doc = $this->loadAccessible($sid, (int) $id, $userId);
        if ($doc === null || !$this->memberOwnsFile($doc, $userId)) {
            Session::flash('error', 'Document not found.');
            return $this->redirect('/member/documents');
        }

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $documents->updateFileDocument(
                (int) $id,
                $sid,
                $userId,
                trim((string) $request->input('title', '')),
                null,
                null,
                null,
                $request->file('upload_file'),
                false
            );
            Session::flash('success', 'Document updated.');
            return $this->redirect('/member/documents/' . $id);
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/member/documents/' . $id . '/edit');
        }
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $documents->softDelete((int) $id, $sid, $userId, false);
            Session::flash('success', 'Document deleted.');
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/member/documents/' . $id);
        }

        return $this->redirect('/member/documents');
    }

    public function download(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $resolved = $documents->resolveDownloadPath((int) $id, 1, $sid);
            if (($resolved['document']['status'] ?? '') !== 'active') {
                throw new HttpException(404, 'Document not found.');
            }
            $documents->assertCanAccessDocument($resolved['document'], $userId, false);
            return Response::download(
                $resolved['path'],
                (string) ($resolved['document']['title'] ?? $resolved['file_name']),
                (string) $resolved['mime_type']
            );
        } catch (HttpException) {
            return $this->redirect('/member/documents');
        }
    }

    /** @return array<string, mixed>|null */
    private function loadAccessible(int $stokvelId, int $documentId, int $userId): ?array
    {
        $doc = $this->db()->fetch(
            "SELECT d.*, dc.name AS category_name
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
             WHERE d.id = :id AND d.stokvel_id = :sid AND d.status = 'active'
             LIMIT 1",
            ['id' => $documentId, 'sid' => $stokvelId]
        );
        if (!$doc) {
            return null;
        }

        /** @var DocumentService $documents */
        $documents = app()->container()->get(DocumentService::class);
        try {
            $documents->assertCanAccessDocument($doc, $userId, false);
        } catch (HttpException) {
            return null;
        }

        return $doc;
    }

    /** @param array<string, mixed> $doc */
    private function memberOwnsFile(array $doc, int $userId): bool
    {
        return (string) ($doc['content_type'] ?? '') !== 'typed'
            && (int) ($doc['uploaded_by_user_id'] ?? 0) === $userId;
    }
}
