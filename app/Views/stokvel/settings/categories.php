<?= view('partials/page-header', [
    'title' => 'Category catalogues',
    'description' => 'Manage expense, payout, document, fee, meeting, and rule categories for this stokvel.',
], null) ?>
<p class="text-muted mb-4"><a href="<?= e(url('/app/settings')) ?>">&larr; Back to settings</a></p>

<?php if (empty($links)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No category managers available',
        'emptyMessage' => 'You need module access or the matching manage permission to open category screens.',
    ], null) ?>
<?php else: ?>
<div class="grid grid-2">
    <?php foreach ($links as $link): ?>
        <a class="card" href="<?= e($link['url'] ?? '#') ?>" style="text-decoration:none;color:inherit;">
            <h2><?= e($link['title'] ?? '') ?></h2>
            <p class="text-muted"><?= e($link['description'] ?? '') ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
