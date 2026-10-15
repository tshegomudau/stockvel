<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM meetings WHERE stokvel_id = :sid',
            'SELECT * FROM meetings WHERE stokvel_id = :sid ORDER BY created_at DESC',
            ['sid' => $sid],
            $page,
            $perPage,
            url('/app/meetings')
        );
        return $this->render('stokvel/meetings/index', [
            'title' => 'Meetings',
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('meetings.manage');
        return $this->render('stokvel/meetings/create', [
            'title' => 'New Meeting',
            'activeNav' => 'meetings',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $data = $request->all();
        unset($data['_token'], $data['_method']);
        $data['stokvel_id'] = $sid;
        $data['created_at'] = now_utc();
        $data['updated_at'] = now_utc();
        $this->service(\App\Services\Stokvel\MeetingService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function create(array $data): int
            {
                $cols = array_keys($data);
                $ph = array_map(static fn ($c) => ':' . $c, $cols);
                $sql = 'INSERT INTO meetings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
                return (int) $this->db->insert($sql, $data);
            }
        })->create($data);
        Session::flash('success', 'Meeting saved.');
        return $this->redirect('/app/meetings');
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM meetings WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/meetings');
        }
        return $this->render('stokvel/meetings/show', [
            'title' => 'Meeting #' . $id,
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM meetings WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/meetings');
        }
        return $this->render('stokvel/meetings/edit', [
            'title' => 'Edit Meeting',
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $data = $request->all();
        unset($data['_token'], $data['_method'], $data['stokvel_id']);
        $data['updated_at'] = now_utc();
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $data['id'] = (int) $id;
        $data['sid'] = $sid;
        $this->db()->exec('UPDATE meetings SET ' . implode(', ', $sets) . ' WHERE id = :id AND stokvel_id = :sid', $data);
        Session::flash('success', 'Meeting updated.');
        return $this->redirect('/app/meetings/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec('UPDATE meetings SET deleted_at = :da, updated_at = :ua WHERE id = :id AND stokvel_id = :sid', [
            'da' => now_utc(),
            'ua' => now_utc(),
            'id' => (int) $id,
            'sid' => $sid,
        ]);
        Session::flash('success', 'Meeting removed.');
        return $this->redirect('/app/meetings');
    }
}
