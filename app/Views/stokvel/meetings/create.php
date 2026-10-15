<div class="card">
    <h1>New Meeting</h1>
    <form method="post" action="<?= e(url('/app/meetings')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" value="<?= e((string) old('title', (string) ($item['title'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="scheduled_at">Date & time</label><input type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?= e((string) old('scheduled_at', (string) ($item['scheduled_at'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="location">Location</label><input type="text" id="location" name="location" value="<?= e((string) old('location', (string) ($item['location'] ?? ''))) ?>" required></div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
