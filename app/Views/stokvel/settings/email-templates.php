<?= view('partials/page-header', [
    'title' => 'Email templates',
    'description' => 'Customise emails for this stokvel. Defaults include {{stokvel_name}}. Reset restores the sample message.',
], null) ?>
<p><a href="<?= e(url('/app/settings')) ?>">← Settings</a></p>
<table class="table">
    <thead><tr><th>Template</th><th>Customised</th><th></th></tr></thead>
    <tbody>
    <?php foreach (($templates ?? []) as $key => $tpl): ?>
        <tr>
            <td><?= e($tpl['label'] ?? $key) ?><div class="text-muted text-sm"><?= e($key) ?></div></td>
            <td><?= !empty($tpl['is_custom']) ? 'Yes' : 'Using default' ?></td>
            <td><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/settings/email-templates/' . rawurlencode($key) . '/edit')) ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
