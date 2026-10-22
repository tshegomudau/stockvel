<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class BurialClaimController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('burial.manage');
        $sid = $this->requireStokvel();
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM burial_claims WHERE stokvel_id = :sid',
            "SELECT bc.*, CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,'')) AS member_name
             FROM burial_claims bc
             LEFT JOIN memberships sm ON sm.id = bc.membership_id
             LEFT JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE bc.stokvel_id = :sid
             ORDER BY bc.created_at DESC",
            ['sid' => $sid],
            $this->page($request),
            $this->perPage($request),
            url('/app/burial-claims')
        );

        return $this->render('stokvel/burial/index', [
            'title' => 'Burial claims',
            'activeNav' => 'burial',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('burial.manage');
        $sid = $this->requireStokvel();
        $members = $this->db()->fetchAll(
            "SELECT sm.id, p.first_name, p.last_name FROM memberships sm
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.stokvel_id = :sid AND sm.status = 'active'
             ORDER BY p.last_name, p.first_name",
            ['sid' => $sid]
        );
        return $this->render('stokvel/burial/create', [
            'title' => 'New burial claim',
            'activeNav' => 'burial',
            'navContext' => 'app',
            'members' => $members,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('burial.manage');
        $sid = $this->requireStokvel();
        $data = [
            'membership_id' => (int) $request->input('membership_id', 0),
            'deceased_name' => trim((string) $request->input('deceased_name', '')),
            'relationship' => trim((string) $request->input('relationship', 'member')),
            'date_of_death' => (string) $request->input('date_of_death', ''),
            'claimed_amount' => (string) $request->input('claimed_amount', ''),
            'notes' => trim((string) $request->input('notes', '')),
        ];
        $validator = Validator::make($data, [
            'membership_id' => 'required|integer',
            'deceased_name' => 'required|min:2',
            'claimed_amount' => 'required|numeric',
        ]);
        if ($validator->fails() || $data['membership_id'] < 1) {
            Session::flash('error', 'Invalid burial claim details.');
            return $this->redirect('/app/burial-claims/create');
        }

        $now = now_utc();
        $ref = 'BCL-' . $sid . '-' . gmdate('YmdHis');
        $id = (int) $this->db()->insert(
            'INSERT INTO burial_claims
                (stokvel_id, membership_id, claim_ref, deceased_name, relationship, date_of_death,
                 claimed_amount, status, notes, submitted_by_user_id, created_at, updated_at)
             VALUES
                (:sid, :mid, :ref, :name, :rel, :dod, :amt, :status, :notes, :by, :ca, :ua)',
            [
                'sid' => $sid,
                'mid' => $data['membership_id'],
                'ref' => $ref,
                'name' => $data['deceased_name'],
                'rel' => $data['relationship'] !== '' ? $data['relationship'] : 'member',
                'dod' => $data['date_of_death'] !== '' ? $data['date_of_death'] : null,
                'amt' => $data['claimed_amount'],
                'status' => 'submitted',
                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                'by' => $this->userId(),
                'ca' => $now,
                'ua' => $now,
            ]
        );

        $this->db()->insert(
            'INSERT INTO approval_requests
                (stokvel_id, request_type, reference_type, reference_id, status,
                 requested_by_user_id, requested_at, created_at, updated_at)
             VALUES
                (:sid, :rtype, :reftype, :refid, :status, :by, :ra, :ca, :ua)',
            [
                'sid' => $sid,
                'rtype' => 'burial_claim',
                'reftype' => 'burial_claim',
                'refid' => $id,
                'status' => 'pending',
                'by' => $this->userId(),
                'ra' => $now,
                'ca' => $now,
                'ua' => $now,
            ]
        );

        Session::flash('success', 'Burial claim submitted for approval.');
        return $this->redirect('/app/burial-claims');
    }
}
