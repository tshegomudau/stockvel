<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\Documents\DocumentService;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $documents = $this->db()->fetchAll(
            "SELECT * FROM documents
             WHERE stokvel_id = :sid AND status = 'active'
             ORDER BY created_at DESC LIMIT 100",
            ['sid' => $sid]
        );

        return $this->render('member/documents', [
            'title' => 'Documents',
            'activeNav' => 'member-documents',
            'navContext' => 'member',
            'documents' => $documents,
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
