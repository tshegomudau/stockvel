<?= view('partials/page-header', [
    'title' => 'Profile changes',
    'description' => 'Review updates submitted by your stokvel administrators before they apply to your account.',
], null) ?>

<?php if (($requests ?? []) === []): ?>
<div class="card">
    <p class="text-muted mb-0">You have no pending changes to review.</p>
</div>
<?php else: ?>
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Requested by</th>
                <th>Submitted</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $req): ?>
            <tr>
                <td><?= e(ucwords(str_replace('_', ' ', (string) ($req['change_type'] ?? '')))) ?></td>
                <td><?= e($req['requester_email'] ?? '') ?></td>
                <td><?= e(sa_datetime($req['created_at'] ?? '')) ?></td>
                <td><a class="btn btn-sm" href="<?= e(url('/member/change-requests/' . ($req['id'] ?? ''))) ?>">Review</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
