<?= view('partials/page-header', [
    'title' => 'My dashboard',
    'description' => 'Your membership status, balances, and upcoming activity.',
], null) ?>
<div class="grid grid-3">
<?= view('partials/stat-card', ['label' => 'Status', 'value' => ucfirst((string) ($membership['status'] ?? 'unknown'))], null) ?>
<?= view('partials/stat-card', ['label' => 'Balance due', 'value' => money($balanceDue ?? 0), 'isMoney' => true], null) ?>
<?= view('partials/stat-card', ['label' => 'Member number', 'value' => (string) ($membership['member_number'] ?? '—')], null) ?>
</div>
<?php if (!empty($pendingChangeCount)): ?>
<div class="alert alert-warning mt-6">
    You have <?= (int) $pendingChangeCount ?> profile/beneficiary change<?= (int) $pendingChangeCount === 1 ? '' : 's' ?> waiting for your approval.
    <a href="<?= e(url('/member/change-requests')) ?>">Review now</a>
</div>
<?php endif; ?>
<div class="quick-actions mt-6">
<a class="btn btn-primary" href="<?= e(url('/member/statement')) ?>">View statement</a>
<a class="btn btn-secondary" href="<?= e(url('/member/meetings')) ?>">Meetings</a>
<a class="btn btn-secondary" href="<?= e(url('/member/documents')) ?>">Documents</a>
<?php if (!empty($pendingChangeCount)): ?>
<a class="btn btn-secondary" href="<?= e(url('/member/change-requests')) ?>">Change requests (<?= (int) $pendingChangeCount ?>)</a>
<?php endif; ?>
</div>
