<?= view('partials/page-header', [
    'title' => ($filters['source'] ?? 'audit') === 'application' ? 'Application logs' : 'Audit log',
    'description' => ($filters['source'] ?? 'audit') === 'application'
        ? 'JSON application logs from storage/logs (redacted).'
        : 'Domain audit events. Switch source to view application logs.',
], null) ?>
<form method="get" class="filter-bar" action="<?= e(url('/platform/audit')) ?>">
    <div class="form-group">
        <label for="source">Source</label>
        <select class="form-control form-select" id="source" name="source">
            <option value="audit"<?= ($filters['source'] ?? '') === 'audit' ? ' selected' : '' ?>>Audit events</option>
            <?php if (!empty($canViewAppLogs)): ?>
            <option value="application"<?= ($filters['source'] ?? '') === 'application' ? ' selected' : '' ?>>Application logs</option>
            <?php endif; ?>
        </select>
    </div>
    <div class="form-group"><label for="q">Search</label><input class="form-control" id="q" type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>"></div>
    <?php if (($filters['source'] ?? '') === 'application'): ?>
        <div class="form-group"><label for="level">Severity</label>
            <select class="form-control form-select" id="level" name="level">
                <option value="">All</option>
                <?php foreach (['INFO','WARNING','ERROR','SECURITY'] as $lv): ?>
                    <option value="<?= $lv ?>"<?= ($filters['level'] ?? '') === $lv ? ' selected' : '' ?>><?= $lv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else: ?>
        <div class="form-group"><label for="action">Action</label><input class="form-control" type="text" id="action" name="action" value="<?= e((string) ($filters['action'] ?? '')) ?>"></div>
        <div class="form-group"><label for="user_id">User ID</label><input class="form-control" id="user_id" type="number" name="user_id" value="<?= e((string) ($filters['user_id'] ?? '')) ?>"></div>
        <div class="form-group"><label for="stokvel_id">Stokvel ID</label><input class="form-control" id="stokvel_id" type="number" name="stokvel_id" value="<?= e((string) ($filters['stokvel_id'] ?? '')) ?>"></div>
    <?php endif; ?>
    <div class="form-group"><label for="date_from">From</label><input class="form-control" id="date_from" type="date" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>"></div>
    <div class="form-group"><label for="date_to">To</label><input class="form-control" id="date_to" type="date" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>"></div>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

<?php if (($filters['source'] ?? '') === 'application'): ?>
<table class="table">
    <thead><tr><th>When</th><th>Severity</th><th>Message</th><th>File</th></tr></thead>
    <tbody>
    <?php foreach (($appLogItems ?? []) as $row): ?>
        <?php
            $level = strtoupper((string) ($row['level'] ?? 'INFO'));
            $sevClass = match ($level) {
                'ERROR', 'SECURITY' => 'text-danger',
                'WARNING' => 'text-warning',
                default => 'text-muted',
            };
        ?>
        <tr>
            <td><?= e(sa_datetime($row['ts'] ?? '')) ?></td>
            <td><span class="badge <?= e($sevClass) ?>"><?= e($level) ?></span></td>
            <td><?= e($row['message'] ?? '') ?></td>
            <td><?= e($row['source_file'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="text-muted">Showing page <?= (int) ($appLogPage ?? 1) ?> · <?= (int) ($appLogTotal ?? 0) ?> matches (capped scan).</p>
<?php else: ?>
<table class="table">
    <thead><tr><th>When</th><th>User</th><th>Stokvel</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach (($paginator->items ?? []) as $row): ?>
        <?php
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $userLabel = $name !== '' ? $name : (string) ($row['email'] ?? ('User #' . ($row['user_id'] ?? '')));
        ?>
        <tr>
            <td><?= e(sa_datetime($row['created_at'] ?? '')) ?></td>
            <td><strong><?= e($userLabel) ?></strong><?php if (!empty($row['email']) && $name !== ''): ?><div class="text-sm text-muted"><?= e($row['email']) ?></div><?php endif; ?></td>
            <td><?= e($row['stokvel_name'] ?? '—') ?></td>
            <td><?= e($row['action'] ?? '') ?></td>
            <td><?= e(($row['entity_type'] ?? '') . ' #' . ($row['entity_id'] ?? '')) ?></td>
            <td><?= e($row['ip_address'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= $paginator ? view('partials/pagination', ['paginator' => $paginator], null) : '' ?>
<?php endif; ?>
