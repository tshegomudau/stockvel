<?php

declare(strict_types=1);

namespace App\Services\Members;

use App\Core\Database;

/**
 * Checklist-based profile completeness from real member/user data.
 */
final class ProfileCompletenessService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{
     *   percent:int,
     *   complete:int,
     *   total:int,
     *   factors:list<array{key:string,label:string,done:bool,note:?string}>,
     *   missing:list<string>
     * }
     */
    public function forMembership(int $stokvelId, int $membershipId): array
    {
        $member = $this->db->fetch(
            'SELECT m.id, m.user_id, m.stokvel_id, u.email, u.mobile,
                    p.first_name, p.last_name, p.id_number_encrypted, p.id_number,
                    p.address_line1, p.city, p.province, p.postal_code,
                    p.emergency_contact_name, p.emergency_contact_phone
             FROM memberships m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE m.id = :mid AND m.stokvel_id = :sid LIMIT 1',
            ['mid' => $membershipId, 'sid' => $stokvelId]
        );

        if ($member === null) {
            return [
                'percent' => 0,
                'complete' => 0,
                'total' => 0,
                'factors' => [],
                'missing' => ['Membership not found'],
            ];
        }

        $userId = (int) $member['user_id'];

        $hasBeneficiary = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS c FROM beneficiaries
             WHERE stokvel_id = :sid AND membership_id = :mid',
            ['sid' => $stokvelId, 'mid' => $membershipId]
        )['c'] ?? 0) > 0;

        $idDoc = $this->idDocumentFactor($stokvelId, $userId);

        $factors = [
            [
                'key' => 'basic_details',
                'label' => 'Basic details (first & last name)',
                'done' => trim((string) ($member['first_name'] ?? '')) !== ''
                    && trim((string) ($member['last_name'] ?? '')) !== '',
                'note' => null,
            ],
            [
                'key' => 'contact',
                'label' => 'Contact (email and mobile)',
                'done' => trim((string) ($member['email'] ?? '')) !== ''
                    && trim((string) ($member['mobile'] ?? '')) !== '',
                'note' => null,
            ],
            [
                'key' => 'identity_number',
                'label' => 'SA ID number on profile',
                'done' => trim((string) ($member['id_number_encrypted'] ?? '')) !== ''
                    || trim((string) ($member['id_number'] ?? '')) !== '',
                'note' => null,
            ],
            [
                'key' => 'address',
                'label' => 'Residential address',
                'done' => trim((string) ($member['address_line1'] ?? '')) !== ''
                    && trim((string) ($member['city'] ?? '')) !== '',
                'note' => null,
            ],
            [
                'key' => 'emergency_contact',
                'label' => 'Emergency contact',
                'done' => trim((string) ($member['emergency_contact_name'] ?? '')) !== ''
                    && trim((string) ($member['emergency_contact_phone'] ?? '')) !== '',
                'note' => null,
            ],
            [
                'key' => 'beneficiary',
                'label' => 'At least one beneficiary',
                'done' => $hasBeneficiary,
                'note' => null,
            ],
            $idDoc,
        ];

        $total = count($factors);
        $complete = 0;
        $missing = [];
        foreach ($factors as $f) {
            if ($f['done']) {
                $complete++;
            } else {
                $missing[] = $f['label'] . ($f['note'] ? ' — ' . $f['note'] : '');
            }
        }

        $percent = $total > 0 ? (int) round(($complete / $total) * 100) : 0;

        return [
            'percent' => $percent,
            'complete' => $complete,
            'total' => $total,
            'factors' => $factors,
            'missing' => $missing,
        ];
    }

    /**
     * @return array{member_count:int,average_percent:float}
     */
    public function averageForStokvel(int $stokvelId): array
    {
        $members = $this->db->fetchAll(
            "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active'",
            ['sid' => $stokvelId]
        );
        if ($members === []) {
            return ['member_count' => 0, 'average_percent' => 0.0];
        }

        $sum = 0;
        foreach ($members as $m) {
            $sum += $this->forMembership($stokvelId, (int) $m['id'])['percent'];
        }

        return [
            'member_count' => count($members),
            'average_percent' => $sum / count($members),
        ];
    }

    /**
     * Documents are not membership-linked; use uploads by this user in the stokvel
     * whose category suggests identity/ID. If none exist, factor is incomplete with a note.
     *
     * @return array{key:string,label:string,done:bool,note:?string}
     */
    private function idDocumentFactor(int $stokvelId, int $userId): array
    {
        $hasCategoryTable = true;
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS c
                 FROM documents d
                 LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
                 WHERE d.stokvel_id = :sid
                   AND d.uploaded_by_user_id = :uid
                   AND d.status = 'active'
                   AND (
                        LOWER(COALESCE(d.category, '')) LIKE '%id%'
                     OR LOWER(COALESCE(d.title, '')) LIKE '%id %'
                     OR LOWER(COALESCE(d.title, '')) LIKE '%identity%'
                     OR LOWER(COALESCE(dc.code, '')) LIKE '%id%'
                     OR LOWER(COALESCE(dc.name, '')) LIKE '%id%'
                     OR LOWER(COALESCE(dc.code, '')) LIKE '%identity%'
                   )",
                ['sid' => $stokvelId, 'uid' => $userId]
            );
        } catch (\Throwable) {
            $hasCategoryTable = false;
            $row = null;
        }

        if (!$hasCategoryTable || $row === null) {
            return [
                'key' => 'id_document',
                'label' => 'ID document on file',
                'done' => false,
                'note' => 'Could not evaluate documents for this member.',
            ];
        }

        $done = (int) ($row['c'] ?? 0) > 0;
        return [
            'key' => 'id_document',
            'label' => 'ID document on file',
            'done' => $done,
            'note' => $done
                ? null
                : 'Checked documents uploaded by this member with an ID/identity category or title.',
        ];
    }
}
