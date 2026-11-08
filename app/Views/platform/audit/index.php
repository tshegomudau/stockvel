<?= view('partials/page-header', [
    'title' => ($filters['source'] ?? 'audit') === 'application' ? 'Application logs' : 'Audit trail',
    'description' => ($filters['source'] ?? 'audit') === 'application'
        ? 'Live JSON logs from storage/logs (passwords and secrets redacted).'
        : 'Domain audit events across stokvels. Switch source to open application logs from storage/logs.',
], null) ?>
<form method="get" class="filter-bar" action="<?= e(url('/platform/audit')) ?>">
    <div class="form-group">
        <label class="form-label" for="source">Source</label>
        <select class="form-control form-select" id="source" name="source">
            <option value="audit"<?= ($filters['source'] ?? '') === 'audit' ? ' selected' : '' ?>>Audit events</option>
            <?php if (!empty($canViewAppLogs)): ?>
            <option value="application"<?= ($filters['source'] ?? '') === 'application' ? ' selected' : '' ?>>Application logs</option>
            <?php endif; ?>
        </select>
    </div>
    <div class="form-group"><label class="form-label" for="q">Search</label><input class="form-control" id="q" type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Message, action, email…"></div>
    <?php if (($filters['source'] ?? '') === 'application'): ?>
        <div class="form-group"><label class="form-label" for="level">Severity</label>
            <select class="form-control form-select" id="level" name="level">
                <option value="">All</option>
                <?php foreach (['INFO','WARNING','ERROR','SECURITY'] as $lv): ?>
                    <option value="<?= $lv ?>"<?= ($filters['level'] ?? '') === $lv ? ' selected' : '' ?>><?= $lv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else: ?>
        <div class="form-group"><label class="form-label" for="action">Action</label><input class="form-control" type="text" id="action" name="action" value="<?= e((string) ($filters['action'] ?? '')) ?>"></div>
        <div class="form-group"><label class="form-label" for="user_id">User ID</label><input class="form-control" id="user_id" type="number" name="user_id" value="<?= e((string) ($filters['user_id'] ?? '')) ?>"></div>
        <div class="form-group"><label class="form-label" for="stokvel_id">Stokvel ID</label><input class="form-control" id="stokvel_id" type="number" name="stokvel_id" value="<?= e((string) ($filters['stokvel_id'] ?? '')) ?>"></div>
    <?php endif; ?>
    <div class="form-group"><label class="form-label" for="date_from">From</label><input class="form-control" id="date_from" type="date" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>"></div>
    <div class="form-group"><label class="form-label" for="date_to">To</label><input class="form-control" id="date_to" type="date" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>"></div>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

<?php if (($filters['source'] ?? '') === 'application'): ?>
    <?php if (empty($appLogItems)): ?>
        <?= view('partials/empty', [
            'emptyTitle' => 'No application log lines match',
            'emptyMessage' => 'Try clearing filters, or confirm storage/logs is writable on this host.',
        ], null) ?>
    <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>Severity</th><th>Message</th><th>Request</th><th>File</th></tr></thead>
            <tbody>
            <?php foreach ($appLogItems as $row): ?>
                <?php
                    $level = strtoupper((string) ($row['level'] ?? 'INFO'));
                    $sevClass = match ($level) {
                        'ERROR', 'SECURITY' => 'badge-danger',
                        'WARNING' => 'badge-warning',
                        default => '',
                    };
                    $context = $row['context'] ?? [];
                    $contextJson = is_array($context) && $context !== []
                        ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        : '';
                ?>
                <tr>
                    <td><?= e(sa_datetime($row['ts'] ?? '')) ?></td>
                    <td><span class="badge <?= e($sevClass) ?>"><?= e($level) ?></span></td>
                    <td>
                        <div><?= e($row['message'] ?? '') ?></div>
                        <?php if ($contextJson !== ''): ?>
                            <details class="text-sm text-muted" style="margin-top:.35rem">
                                <summary>Context</summary>
                                <pre class="form-help" style="white-space:pre-wrap;margin:.35rem 0 0"><?= e((string) $contextJson) ?></pre>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted"><?= e($row['request_id'] ?? '—') ?></td>
                    <td class="text-sm"><?= e($row['source_file'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php
            $page = (int) ($appLogPage ?? 1);
            $totalPages = (int) ($appLogTotalPages ?? 1);
            $query = $appLogQuery ?? ['source' => 'application'];
        ?>
        <div class="page-actions" style="justify-content:space-between;align-items:center;margin-top:1rem;">
            <p class="text-muted" style="margin:0">Page <?= $page ?> of <?= $totalPages ?> · <?= (int) ($appLogTotal ?? 0) ?> matches (scan capped)</p>
            <div class="page-actions" style="gap:.5rem;margin:0">
                <?php if ($page > 1): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(url('/platform/audit?' . http_build_query(array_merge($query, ['page' => $page - 1])))) ?>">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(url('/platform/audit?' . http_build_query(array_merge($query, ['page' => $page + 1])))) ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if (empty($paginator->items ?? [])): ?>
        <?= view('partials/empty', [
            'emptyTitle' => 'No audit events match',
            'emptyMessage' => 'Try clearing filters. Application logs are available from the Source dropdown when permitted.',
        ], null) ?>
    <?php else: ?>
        <div class="table-wrap">
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
        </div>
        <?= $paginator ? view('partials/pagination', ['paginator' => $paginator], null) : '' ?>
    <?php endif; ?>
<?php endif; ?>
