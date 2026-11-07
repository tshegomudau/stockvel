<?php $tpl = $template ?? []; $key = (string) ($templateKey ?? ''); ?>
<?= view('partials/page-header', [
    'title' => 'Edit: ' . ($tpl['label'] ?? $key),
    'description' => 'Available variables: ' . implode(', ', array_map(static fn ($v) => '{{' . $v . '}}', $tpl['variables'] ?? ['stokvel_name'])),
], null) ?>
<form method="post" action="<?= e(url('/app/settings/email-templates/' . rawurlencode($key))) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="subject">Subject</label>
        <input class="form-control" type="text" id="subject" name="subject" required value="<?= e((string) old('subject', $tpl['subject'] ?? '')) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="body">Body</label>
        <textarea class="form-control form-textarea" id="body" name="body" rows="12" required><?= e((string) old('body', $tpl['body'] ?? '')) ?></textarea>
        <p class="form-help">Plain text with {{variables}}. Scripts and unsafe markup are rejected.</p>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save template</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/settings/email-templates')) ?>">Back</a>
    </div>
</form>
<form method="post" action="<?= e(url('/app/settings/email-templates/' . rawurlencode($key) . '/reset')) ?>" class="mt-4">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-ghost" data-confirm="Reset to default sample?">Reset to default</button>
</form>
