<?php
$types = ['success', 'error', 'info', 'warning'];
foreach ($types as $type):
    $message = flash($type);
    if ($message): ?>
        <div class="alert alert-<?= e($type === 'error' ? 'danger' : $type) ?>" role="alert"><?= e((string) $message) ?></div>
    <?php endif;
endforeach;
$errors = flash('errors');
if (is_array($errors) && $errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="error-list">
            <?php foreach ($errors as $field => $messages): ?>
                <?php foreach ((array) $messages as $msg): ?>
                    <li><?= e((string) $msg) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
