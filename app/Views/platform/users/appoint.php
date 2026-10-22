<?= view('partials/page-header', [
    'title' => 'Appoint chairperson',
    'description' => 'This stokvel has no chairperson. Choose a member to appoint.',
], null) ?>

<div class="card mb-6">
    <h2><?= e($stokvel['name'] ?? 'Stokvel') ?></h2>
    <p class="text-muted">Status: <?= e($stokvel['status'] ?? '') ?></p>
</div>

<form method="get" class="toolbar mb-4">
    <input type="search" name="q" placeholder="Search members" value="<?= e($search ?? '') ?>">
    <button type="submit" class="btn">Search</button>
    <a class="btn btn-secondary" href="<?= e(url('/platform/users')) ?>">Cancel</a>
</form>

<?php if (($members ?? []) === []): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No members',
        'emptyMessage' => 'This stokvel has no active members to appoint.',
    ], null) ?>
<?php else: ?>
<table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Member #</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($members as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td><?= e($row['email'] ?? '') ?></td>
            <td><?= e($row['member_number'] ?? '') ?></td>
            <td><?= e($row['status'] ?? '') ?></td>
            <td>
                <form method="post" action="<?= e(url('/platform/users/appoint/' . (int) ($stokvel['id'] ?? 0))) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="membership_id" value="<?= (int) ($row['membership_id'] ?? 0) ?>">
                    <button type="submit" class="btn btn-sm btn-primary" data-confirm="Appoint this member as chairperson?">Appoint</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
