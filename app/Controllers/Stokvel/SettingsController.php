<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        $stokvel = $this->db()->fetch('SELECT * FROM stokvels WHERE id = :id LIMIT 1', ['id' => $sid]);
        $settings = $this->db()->fetch('SELECT * FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1', ['sid' => $sid]) ?? [];

        return $this->render('stokvel/settings/edit', [
            'title' => 'Settings',
            'activeNav' => 'settings',
            'navContext' => 'app',
            'stokvel' => $stokvel,
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        $data = $request->all();
        unset($data['_token'], $data['_method'], $data['stokvel_id']);

        $stokvelFields = array_intersect_key($data, array_flip(['name', 'registration_number', 'description', 'primary_colour', 'logo_path']));
        if ($stokvelFields !== []) {
            $stokvelFields['updated_at'] = now_utc();
            $sets = [];
            foreach (array_keys($stokvelFields) as $col) {
                $sets[] = "{$col} = :{$col}";
            }
            $stokvelFields['id'] = $sid;
            $this->db()->exec('UPDATE stokvels SET ' . implode(', ', $sets) . ' WHERE id = :id', $stokvelFields);
        }

        $this->service(\App\Services\Stokvel\SettingsService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function upsert(int $stokvelId, array $settings): void
            {
                $settings['stokvel_id'] = $stokvelId;
                $settings['updated_at'] = now_utc();
                $existing = $this->db->fetch('SELECT id FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1', ['sid' => $stokvelId]);
                if ($existing) {
                    unset($settings['stokvel_id']);
                    $sets = [];
                    foreach (array_keys($settings) as $col) {
                        $sets[] = "{$col} = :{$col}";
                    }
                    $settings['sid'] = $stokvelId;
                    $this->db->exec('UPDATE stokvel_settings SET ' . implode(', ', $sets) . ' WHERE stokvel_id = :sid', $settings);
                } else {
                    $settings['created_at'] = now_utc();
                    $cols = array_keys($settings);
                    $ph = array_map(static fn ($c) => ':' . $c, $cols);
                    $this->db->insert('INSERT INTO stokvel_settings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')', $settings);
                }
            }
        })->upsert($sid, $data);

        Session::flash('success', 'Settings saved.');
        return $this->redirect('/app/settings');
    }
}
