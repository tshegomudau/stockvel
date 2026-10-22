<?= view('partials/page-header', [
    'title' => $item['title'] ?? 'Meeting',
    'description' => sa_datetime($item['scheduled_at'] ?? '') . (!empty($item['venue']) ? ' · ' . $item['venue'] : ''),
    'actionUrl' => url('/app/meetings/' . (int) $item['id'] . '/edit'),
    'actionLabel' => 'Edit',
], null) ?>
<?php $canManageMinutes = !empty($canManageMinutes); ?>

<div class="grid grid-2">
    <section class="card">
        <h2 class="card-title">Meeting notes</h2>
        <form method="post" action="<?= e(url('/app/meetings/' . (int) $item['id'] . '/notes')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <textarea name="notes" rows="8" data-wysiwyg class="wysiwyg" data-wysiwyg-size="tall" aria-label="Meeting notes"><?= e($item['notes'] ?? '') ?></textarea>
            <p class="form-help">Rich text only — no images or file attachments.</p>
            <button type="submit" class="btn btn-primary">Save notes</button>
        </form>
    </section>

    <section class="card">
        <h2 class="card-title">Details</h2>
        <dl class="detail-list">
            <dt>Reference</dt><dd><?= e($item['meeting_ref'] ?? '—') ?></dd>
            <dt>Status</dt><dd><span class="badge"><?= e(ucfirst((string) ($item['status'] ?? ''))) ?></span></dd>
            <dt>Type</dt><dd><?= e(strtoupper((string) ($item['meeting_type'] ?? 'regular')) === 'AGM' ? 'AGM' : ucfirst((string) ($item['meeting_type'] ?? 'regular'))) ?></dd>
            <dt>Scheduled</dt><dd><?= e(sa_datetime($item['scheduled_at'] ?? '')) ?></dd>
            <dt>Venue</dt><dd><?= e($item['venue'] ?? '—') ?></dd>
        </dl>
    </section>
</div>

<?php if (!empty($item['agenda'])): ?>
<section class="card mt-6">
    <h2 class="card-title">Agenda</h2>
    <div class="prose"><?= safe_html((string) $item['agenda']) ?></div>
</section>
<?php endif; ?>

<section class="card mt-6">
    <h2 class="card-title">Minutes</h2>
    <?php if ($canManageMinutes): ?>
        <form method="post" action="<?= e(url('/app/meetings/' . (int) $item['id'] . '/minutes')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <textarea name="minutes_html" rows="12" data-wysiwyg class="wysiwyg wysiwyg--tall" data-wysiwyg data-wysiwyg-size="tall" aria-label="Meeting minutes"><?= e($item['minutes_html'] ?? '') ?></textarea>
            <p class="form-help">Full minutes editor (headings, lists, links). No image or file attachments.</p>
            <button type="submit" class="btn btn-primary">Save minutes</button>
        </form>
    <?php elseif (!empty($item['minutes_html'])): ?>
        <div class="prose"><?= safe_html((string) $item['minutes_html']) ?></div>
    <?php else: ?>
        <p class="text-muted">No minutes recorded yet.</p>
    <?php endif; ?>
</section>

<section class="card mt-6">
    <h2 class="card-title">Linked documents</h2>
    <?php if (($linkedDocuments ?? []) === []): ?>
        <p class="text-muted">No documents linked. Attach one when creating/editing a document.</p>
    <?php else: ?>
        <ul class="plain-list">
            <?php foreach ($linkedDocuments as $doc): ?>
                <li><a href="<?= e(url('/app/documents/' . (int) $doc['id'])) ?>"><?= e($doc['title'] ?? 'Document') ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card mt-6">
    <h2 class="card-title">Attendance</h2>
    <?php if (empty($members)): ?>
        <?= view('partials/empty', ['emptyTitle' => 'No active members', 'emptyMessage' => 'Invite members before recording attendance.'], null) ?>
    <?php else: ?>
        <form method="post" action="<?= e(url('/app/meetings/' . (int) $item['id'] . '/attendance')) ?>">
            <?= csrf_field() ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Member</th><th>Number</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <?php $mid = (int) $m['membership_id']; ?>
                        <tr>
                            <td><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></td>
                            <td><?= e($m['member_number'] ?? '') ?></td>
                            <td>
                                <select name="attendance[<?= $mid ?>]">
                                    <?php foreach (['present' => 'Present', 'absent' => 'Absent', 'apology' => 'Apology', 'late' => 'Late'] as $val => $label): ?>
                                        <option value="<?= e($val) ?>"<?= (($m['attendance_status'] ?? '') === $val) ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary">Save attendance</button>
            </div>
        </form>
    <?php endif; ?>
</section>
