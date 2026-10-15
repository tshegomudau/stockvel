<section class="card card-auth">
    <h1 class="card-title">Reset password</h1>
    <form method="post" action="<?= e(url('/forgot-password')) ?>" class="form" data-disable-on-submit>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
    </form>
</section>
