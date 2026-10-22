<?= view('partials/page-header', [
    'title' => 'Roles & permissions',
    'description' => 'Role management has moved to the roles list.',
], null) ?>
<p><a class="btn btn-primary" href="<?= e(url('/app/settings/roles')) ?>">Open roles</a></p>
