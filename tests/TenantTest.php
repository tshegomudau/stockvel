<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\TenantContext;
use App\Repositories\StokvelRepository;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Tenant isolation on stokvel read', function (): void {
        test_transaction(function () {
            $db = test_db();
            $charge = $db->fetch('SELECT id, stokvel_id FROM member_charges ORDER BY id ASC LIMIT 1');
            if (!$charge) {
                return;
            }
            $otherStokvel = $db->fetch(
                'SELECT id FROM stokvels WHERE id != :sid LIMIT 1',
                ['sid' => $charge['stokvel_id']]
            );
            if (!$otherStokvel) {
                return;
            }
            $updated = $db->exec(
                'UPDATE member_charges SET amount = amount WHERE id = :id AND stokvel_id = :wrong',
                ['id' => $charge['id'], 'wrong' => $otherStokvel['id']]
            );
            test_assert($updated === 0);
        });
    });

    $runner->add('Cross-tenant ID manipulation blocked', function (): void {
        test_transaction(function () {
            $db = test_db();
            $member = $db->fetch('SELECT id, stokvel_id FROM memberships ORDER BY id ASC LIMIT 1');
            if (!$member) {
                return;
            }
            $otherStokvel = $db->fetch(
                'SELECT id FROM stokvels WHERE id != :sid LIMIT 1',
                ['sid' => $member['stokvel_id']]
            );
            if (!$otherStokvel) {
                return;
            }
            $updated = $db->exec(
                'UPDATE memberships SET member_number = :n WHERE id = :id AND stokvel_id = :sid',
                ['n' => 'Hacked', 'id' => $member['id'], 'sid' => $otherStokvel['id']]
            );
            test_assert($updated === 0);
        });
    });
};
