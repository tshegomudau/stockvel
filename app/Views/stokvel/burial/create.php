<?= view('partials/page-header', [
    'title' => 'New burial claim',
    'description' => 'Capture a funeral benefit claim. It will enter the approvals queue.',
], null) ?>
<form method="post" action="<?= e(url('/app/burial-claims')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="membership_id">Member</label>
        <select id="membership_id" name="membership_id" required>
            <option value="">Select member</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= (int) $m['id'] ?>"><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="deceased_name">Deceased full name</label>
        <input id="deceased_name" name="deceased_name" required>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="relationship">Relationship to member</label>
            <input id="relationship" name="relationship" value="member" placeholder="member / spouse / child / parent">
        </div>
        <div class="form-group">
            <label class="form-label" for="date_of_death">Date of death</label>
            <input type="date" id="date_of_death" name="date_of_death">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="claimed_amount">Claim amount (ZAR)</label>
        <input type="number" step="0.01" min="0" id="claimed_amount" name="claimed_amount" required>
    </div>
    <div class="form-group">
        <label class="form-label" for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="3"></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Submit for approval</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/burial-claims')) ?>">Cancel</a>
    </div>
</form>
