<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $documents = $this->db()->fetchAll(
            "SELECT * FROM documents WHERE stokvel_id = :sid AND deleted_at IS NULL AND visibility IN ('members','public') ORDER BY created_at DESC LIMIT 100",
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
        $doc = $this->db()->fetch(
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND visibility IN ('members','public') AND deleted_at IS NULL LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            return $this->redirect('/member/documents');
        }

        $path = app()->basePath('storage/documents/' . ($doc['stored_path'] ?? ''));
        return \App\Core\Response::download($path, (string) ($doc['title'] ?? 'document'), (string) ($doc['mime_type'] ?? 'application/octet-stream'));
    }
}
