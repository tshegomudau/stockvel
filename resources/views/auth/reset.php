<section class="card card-auth">
    <h1 class="card-title">Choose new password</h1>
    <form method="post" action="<?= e(url('/reset-password/' . urlencode($token))) ?>" class="form" data-disable-on-submit>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Update password</button>
    </form>
</section>
