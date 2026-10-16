<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

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
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();
        $documents = $this->db()->fetchAll(
            "SELECT * FROM documents WHERE stokvel_id = :sid AND status <> 'deleted' ORDER BY created_at DESC LIMIT 100",
            ['sid' => $sid]
        );

        return $this->render('stokvel/documents/index', [
            'title' => 'Documents',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'documents' => $documents,
        ]);
    }

    public function uploadForm(Request $request): Response
    {
        $this->requirePermission('documents.upload');
        return $this->render('stokvel/documents/upload', [
            'title' => 'Upload document',
            'activeNav' => 'documents',
            'navContext' => 'app',
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->requirePermission('documents.upload');
        $this->requireStokvel();
        $file = $request->file('document');
        if ($file === null) {
            Session::flash('error', 'Upload failed.');
            return $this->redirect('/app/documents/upload');
        }

        try {
            /** @var DocumentService $documents */
            $documents = app()->container()->get(DocumentService::class);
            $documents->upload(
                $file,
                trim((string) $request->input('title', $file['name'] ?? 'document')),
                null,
                (int) $this->userId(),
                (string) $request->input('category', 'general')
            );
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/documents/upload');
        }

        Session::flash('success', 'Document uploaded.');
        return $this->redirect('/app/documents');
    }

    public function download(Request $request, string $id): Response
    {
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();

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
            return $this->redirect('/app/documents');
        }
    }
}
