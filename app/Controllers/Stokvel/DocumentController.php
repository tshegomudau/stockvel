<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();
        $documents = $this->db()->fetchAll(
            'SELECT * FROM documents WHERE stokvel_id = :sid AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 100',
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
        $sid = $this->requireStokvel();
        $file = $request->file('document');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Upload failed.');
            return $this->redirect('/app/documents/upload');
        }

        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
        if (!in_array($mime, $allowed, true)) {
            Session::flash('error', 'File type not allowed.');
            return $this->redirect('/app/documents/upload');
        }

        $storedName = uuid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $file['name']);
        $destDir = app()->basePath('storage/documents/' . $sid);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }
        $dest = $destDir . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Session::flash('error', 'Could not store file.');
            return $this->redirect('/app/documents/upload');
        }

        $this->db()->insert(
            'INSERT INTO documents (stokvel_id, title, category, stored_path, mime_type, uploaded_by, created_at, updated_at) VALUES (:sid, :title, :cat, :path, :mime, :by, :ca, :ua)',
            [
                'sid' => $sid,
                'title' => trim((string) $request->input('title', $file['name'])),
                'cat' => (string) $request->input('category', 'general'),
                'path' => $sid . '/' . $storedName,
                'mime' => $mime,
                'by' => $this->userId(),
                'ca' => now_utc(),
                'ua' => now_utc(),
            ]
        );

        Session::flash('success', 'Document uploaded.');
        return $this->redirect('/app/documents');
    }

    public function download(Request $request, string $id): Response
    {
        $this->requirePermission('documents.view');
        $sid = $this->requireStokvel();
        $doc = $this->db()->fetch(
            'SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND deleted_at IS NULL LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$doc) {
            return $this->redirect('/app/documents');
        }

        $path = app()->basePath('storage/documents/' . ($doc['stored_path'] ?? ''));
        return Response::download($path, (string) ($doc['title'] ?? 'document'), (string) ($doc['mime_type'] ?? 'application/octet-stream'));
    }
}
