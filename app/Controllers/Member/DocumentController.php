<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\Documents\DocumentService;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $documents = $this->db()->fetchAll(
            "SELECT d.*, dc.name AS category_name
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
             WHERE d.stokvel_id = :sid AND d.status = 'active'
             ORDER BY d.created_at DESC LIMIT 100",
            ['sid' => $sid]
        );

        return $this->render('member/documents/index', [
            'title' => 'Documents',
            'activeNav' => 'member-documents',
            'navContext' => 'member',
            'documents' => $documents,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $doc = $this->db()->fetch(
            "SELECT d.*, dc.name AS category_name
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
             WHERE d.id = :id AND d.stokvel_id = :sid AND d.status = 'active'
             LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            Session::flash('error', 'Document not found.');
            return $this->redirect('/member/documents');
        }

        return $this->render('member/documents/show', [
            'title' => (string) ($doc['title'] ?? 'Document'),
            'activeNav' => 'member-documents',
            'navContext' => 'member',
            'document' => $doc,
        ]);
    }

    public function download(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $resolved = $documents->resolveDownloadPath((int) $id, 1, $sid);
            if (($resolved['document']['status'] ?? '') !== 'active') {
                throw new HttpException(404, 'Document not found.');
            }
            return Response::download(
                $resolved['path'],
                (string) ($resolved['document']['title'] ?? $resolved['file_name']),
                (string) $resolved['mime_type']
            );
        } catch (HttpException) {
            return $this->redirect('/member/documents');
        }
    }
}
