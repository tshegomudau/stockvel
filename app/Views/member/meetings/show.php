<?= view('partials/page-header', [
    'title' => $meeting['title'] ?? 'Meeting',
    'description' => sa_datetime($meeting['scheduled_at'] ?? '')
        . (!empty($meeting['venue']) ? ' · ' . $meeting['venue'] : '')
        . (!empty($meeting['status']) ? ' · ' . ucfirst((string) $meeting['status']) : ''),
], null) ?>

<p class="mb-4"><a href="<?= e(url('/member/meetings')) ?>">← All meetings</a></p>

<div class="grid grid-2 mb-6">
    <section class="card">
        <h2 class="card-title">Details</h2>
        <dl class="detail-list">
            <dt>Type</dt>
            <dd><?= e(strtoupper((string) ($meeting['meeting_type'] ?? 'regular')) === 'AGM' ? 'AGM' : ucfirst((string) ($meeting['meeting_type'] ?? 'regular'))) ?></dd>
            <dt>Scheduled</dt>
            <dd><?= e(sa_datetime($meeting['scheduled_at'] ?? '')) ?></dd>
            <dt>Venue</dt>
            <dd><?= e($meeting['venue'] ?? '—') ?></dd>
            <dt>Minutes acknowledged</dt>
            <dd><?= !empty($meeting['acknowledged_at']) ? e(sa_datetime($meeting['acknowledged_at'])) : 'Not yet' ?></dd>
        </dl>
        <?php if (empty($meeting['acknowledged_at']) && !empty($meeting['minutes_html'])): ?>
        <form method="post" action="<?= e(url('/member/meetings/' . (int) $meeting['id'] . '/acknowledge')) ?>" class="mt-4">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Acknowledge minutes</button>
        </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2 class="card-title">Agenda</h2>
        <?php if (!empty($meeting['agenda'])): ?>
            <div class="prose"><?= safe_html((string) $meeting['agenda']) ?></div>
        <?php else: ?>
            <p class="text-muted">No agenda published.</p>
        <?php endif; ?>
    </section>
</div>

<section class="card mb-6">
    <h2 class="card-title">Notes</h2>
    <?php if (!empty($meeting['notes'])): ?>
        <div class="prose"><?= safe_html((string) $meeting['notes']) ?></div>
    <?php else: ?>
        <p class="text-muted">No notes yet.</p>
    <?php endif; ?>
</section>

<section class="card mb-6">
    <h2 class="card-title">Minutes</h2>
    <?php if (!empty($meeting['minutes_html'])): ?>
        <div class="prose"><?= safe_html((string) $meeting['minutes_html']) ?></div>
    <?php else: ?>
        <p class="text-muted">Minutes have not been published yet.</p>
    <?php endif; ?>
</section>

<section class="card mb-6">
    <h2 class="card-title">Documents</h2>
    <?php if (($documents ?? []) === []): ?>
        <p class="text-muted">No documents linked to this meeting.</p>
        <p class="form-help"><a href="<?= e(url('/member/documents')) ?>">Browse all documents</a></p>
    <?php else: ?>
        <ul class="plain-list">
            <?php foreach ($documents as $doc): ?>
                <li class="mb-2">
                    <a href="<?= e(url('/member/documents/' . (int) $doc['id'])) ?>"><?= e($doc['title'] ?? 'Document') ?></a>
                    <?php if (!empty($doc['category_name'] ?? $doc['category'])): ?>
                        <span class="text-muted">· <?= e($doc['category_name'] ?? $doc['category']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
