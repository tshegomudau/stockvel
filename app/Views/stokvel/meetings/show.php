<?= view('partials/page-header', [
    'title' => $item['title'] ?? 'Meeting',
    'description' => sa_datetime($item['scheduled_at'] ?? '') . (!empty($item['venue']) ? ' · ' . $item['venue'] : ''),
    'actionUrl' => url('/app/meetings/' . (int) $item['id'] . '/edit'),
    'actionLabel' => 'Edit',
], null) ?>
<?php
$canManageMinutes = !empty($canManageMinutes);
$canManageMeetings = !empty($canManageMeetings);
$resolutions = $resolutions ?? [];
$actionItems = $actionItems ?? [];
$myVotes = $myVotes ?? [];
$currentMembershipId = $currentMembershipId ?? null;
$attendanceSummary = $attendanceSummary ?? ['present' => 0, 'absent' => 0, 'apology' => 0, 'late' => 0, 'unmarked' => 0];
$meetingId = (int) $item['id'];
?>

<div class="grid grid-2">
    <section class="card">
        <h2 class="card-title">Meeting notes</h2>
        <form method="post" action="<?= e(url('/app/meetings/' . $meetingId . '/notes')) ?>" class="form-stack">
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
        <form method="post" action="<?= e(url('/app/meetings/' . $meetingId . '/minutes')) ?>" class="form-stack">
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

<?php
$attendanceSummary = $attendanceSummary ?? ['present' => 0, 'absent' => 0, 'apology' => 0, 'late' => 0, 'unmarked' => 0];
$resolutions = $resolutions ?? [];
$actionItems = $actionItems ?? [];
$myVotes = $myVotes ?? [];
$canManageMeetings = !empty($canManageMeetings);
$currentMembershipId = $currentMembershipId ?? null;
$meetingId = (int) ($item['id'] ?? 0);
?>

<section class="card mt-6">
    <h2 class="card-title">Attendance</h2>
    <p class="text-muted mb-4">
        Present <?= (int) $attendanceSummary['present'] ?>
        · Absent <?= (int) $attendanceSummary['absent'] ?>
        · Apology <?= (int) $attendanceSummary['apology'] ?>
        · Late <?= (int) $attendanceSummary['late'] ?>
        · Unmarked <?= (int) $attendanceSummary['unmarked'] ?>
    </p>
    <?php if (empty($members)): ?>
        <?= view('partials/empty', ['emptyTitle' => 'No active members', 'emptyMessage' => 'Invite members before recording attendance.'], null) ?>
    <?php else: ?>
        <form method="post" action="<?= e(url('/app/meetings/' . $meetingId . '/attendance')) ?>">
            <?= csrf_field() ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Member</th><th>Number</th><th>Status</th><th>Notes / apology</th></tr>
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
                            <td>
                                <input type="text" name="attendance_notes[<?= $mid ?>]" value="<?= e($m['attendance_notes'] ?? '') ?>" maxlength="512" placeholder="Optional">
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

<section class="card mt-6">
    <h2 class="card-title">Resolutions</h2>
    <?php if ($resolutions === []): ?>
        <p class="text-muted">No resolutions yet.</p>
    <?php else: ?>
        <div class="table-wrap mb-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Yes</th>
                        <th>No</th>
                        <th>Abstain</th>
                        <th>Your vote</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resolutions as $res): ?>
                    <?php
                    $rid = (int) $res['id'];
                    $status = strtolower((string) ($res['status'] ?? ''));
                    $isOpen = in_array($status, ['open', 'proposed'], true);
                    $myVote = $myVotes[$rid] ?? null;
                    ?>
                    <tr>
                        <td><?= e($res['resolution_number'] ?? '') ?></td>
                        <td>
                            <strong><?= e($res['title'] ?? '') ?></strong>
                            <?php if (!empty($res['description'])): ?>
                                <div class="text-muted"><?= e(mb_strimwidth((string) $res['description'], 0, 160, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge"><?= e(ucfirst($status)) ?></span></td>
                        <td><?= (int) ($res['votes_for'] ?? 0) ?></td>
                        <td><?= (int) ($res['votes_against'] ?? 0) ?></td>
                        <td><?= (int) ($res['votes_abstain'] ?? 0) ?></td>
                        <td>
                            <?php if ($currentMembershipId && $isOpen): ?>
                                <form method="post" action="<?= e(url('/app/meetings/' . $meetingId . '/resolutions/' . $rid . '/vote')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <select name="vote" aria-label="Vote on <?= e($res['title'] ?? 'resolution') ?>">
                                        <option value="yes"<?= $myVote === 'yes' ? ' selected' : '' ?>>Yes</option>
                                        <option value="no"<?= $myVote === 'no' ? ' selected' : '' ?>>No</option>
                                        <option value="abstain"<?= $myVote === 'abstain' ? ' selected' : '' ?>>Abstain</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-secondary"><?= $myVote ? 'Update' : 'Vote' ?></button>
                                </form>
                            <?php elseif ($myVote): ?>
                                <?= e(ucfirst($myVote)) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canManageMeetings): ?>
        <form method="post" action="<?= e(url('/app/meetings/' . $meetingId . '/resolutions')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <h3 class="card-title">Add resolution</h3>
            <div class="form-group">
                <label for="resolution_title">Title</label>
                <input type="text" id="resolution_title" name="title" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="resolution_body">Description</label>
                <textarea id="resolution_body" name="body" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add resolution</button>
        </form>
    <?php endif; ?>
</section>

<section class="card mt-6">
    <h2 class="card-title">Action items</h2>
    <?php if ($actionItems === []): ?>
        <p class="text-muted">No action items yet.</p>
    <?php else: ?>
        <div class="table-wrap mb-4">
            <table class="table">
                <thead>
                    <tr><th>Title</th><th>Assignee</th><th>Due</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($actionItems as $ai): ?>
                    <tr>
                        <td>
                            <strong><?= e($ai['title'] ?? '') ?></strong>
                            <?php if (!empty($ai['description'])): ?>
                                <div class="text-muted"><?= e(mb_strimwidth((string) $ai['description'], 0, 120, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e(trim((string) ($ai['assignee_name'] ?? '')) !== '' ? $ai['assignee_name'] : '—') ?></td>
                        <td><?= !empty($ai['due_date']) ? e(sa_date($ai['due_date'])) : '—' ?></td>
                        <td><span class="badge"><?= e(ucfirst((string) ($ai['status'] ?? ''))) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canManageMeetings): ?>
        <form method="post" action="<?= e(url('/app/meetings/' . $meetingId . '/action-items')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <h3 class="card-title">Add action item</h3>
            <div class="form-group">
                <label for="action_title">Title</label>
                <input type="text" id="action_title" name="title" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="action_description">Description</label>
                <textarea id="action_description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="action_assignee">Assign to</label>
                <select id="action_assignee" name="assigned_membership_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int) $m['membership_id'] ?>">
                            <?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) . (!empty($m['member_number']) ? ' (' . $m['member_number'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="action_due">Due date</label>
                <input type="date" id="action_due" name="due_date">
            </div>
            <button type="submit" class="btn btn-primary">Add action item</button>
        </form>
    <?php endif; ?>
</section>
