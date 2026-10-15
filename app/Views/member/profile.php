<div class="card">
    <h1>My profile</h1>
    <form method="post" action="<?= e(url('/member/profile')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="first_name">First name</label>
            <input type="text" id="first_name" name="first_name" required value="<?= e($profile['first_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="last_name">Last name</label>
            <input type="text" id="last_name" name="last_name" required value="<?= e($profile['last_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="mobile">Mobile</label>
            <input type="tel" id="mobile" name="mobile" value="<?= e($profile['mobile'] ?? '') ?>">
        </div>
        <p class="text-muted">Email: <?= e($profile['email'] ?? '') ?> (contact admin to change)</p>
        <button type="submit" class="btn btn-primary">Save profile</button>
    </form>
</div>
