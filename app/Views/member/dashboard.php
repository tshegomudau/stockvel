<?= view('partials/page-header', [
    'title' => 'My dashboard',
    'description' => 'Your membership status, balances, and upcoming activity.',
], null) ?>
<div class="grid grid-3">
<?= view('partials/stat-card', ['label' => 'Status', 'value' => ucfirst((string) ($membership['status'] ?? 'unknown'))], null) ?>
<?= view('partials/stat-card', ['label' => 'Balance due', 'value' => money($balanceDue ?? 0), 'isMoney' => true], null) ?>
<?= view('partials/stat-card', ['label' => 'Member number', 'value' => (string) ($membership['member_number'] ?? '—')], null) ?>
</div>
<div class="quick-actions mt-6">
<a class="btn btn-primary" href="<?= e(url('/member/statement')) ?>">View statement</a>
<a class="btn btn-secondary" href="<?= e(url('/member/meetings')) ?>">Meetings</a>
<a class="btn btn-secondary" href="<?= e(url('/member/documents')) ?>">Documents</a>
</div>
