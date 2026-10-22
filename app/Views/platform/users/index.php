<?= view('partials/page-header', [
    'title' => 'Stokvel chairpersons',
    'description' => 'Platform may update chairpersons and grant full permissions. Ordinary members are managed inside each stokvel.',
], null) ?>

<?php if (!empty($withoutChair)): ?>
<section class="card mb-6">
    <h2>Stokvels without a chairperson</h2>
    <p class="text-muted mb-4">Appoint a chairperson from the stokvel’s member list.</p>
    <table class="table">
        <thead><tr><th>Stokvel</th><th>Status</th><th>Members</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($withoutChair as $row): ?>
            <tr>
                <td><?= e($row['name'] ?? '') ?></td>
                <td><?= e($row['status'] ?? '') ?></td>
                <td><?= (int) ($row['member_count'] ?? 0) ?></td>
                <td>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('/platform/users/appoint/' . (int) ($row['id'] ?? 0))) ?>">Appoint chairperson</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<form method="get" class="toolbar">
    <input type="search" name="q" placeholder="Search chairpersons or stokvel" value="<?= e($search ?? '') ?>">
    <button type="submit" class="btn">Search</button>
</form>
<table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Stokvel</th><th>Verified</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td><?= e($row['email'] ?? '') ?></td>
            <td><?= e($row['stokvel_name'] ?? '—') ?></td>
            <td><?= !empty($row['email_verified_at']) ? 'Yes' : 'No' ?></td>
            <td>
                <form method="post" action="<?= e(url('/platform/users/' . (int) $row['id'] . '/grant-all')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="membership_id" value="<?= (int) ($row['membership_id'] ?? 0) ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" data-confirm="Grant all permissions to this chairperson?">Grant all permissions</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
