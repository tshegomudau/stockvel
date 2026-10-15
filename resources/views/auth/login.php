<section class="card card-auth">
    <h1 class="card-title">Sign in</h1>
    <form method="post" action="<?= e(url('/login')) ?>" class="form" data-disable-on-submit>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="username" value="<?= e((string) old('email')) ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>
    <p class="form-footer"><a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a> · <a href="<?= e(url('/register')) ?>">Create account</a></p>
</section>
