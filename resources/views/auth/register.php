<section class="card card-auth">
    <h1 class="card-title">Create account</h1>
    <form method="post" action="<?= e(url('/register')) ?>" class="form" data-disable-on-submit>
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name" required value="<?= e((string) old('first_name')) ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name" required value="<?= e((string) old('last_name')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e((string) old('email')) ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
</section>
