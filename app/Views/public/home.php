<section class="auth-shell auth-split" style="padding:2rem 0 1rem;">
    <div class="auth-hero">
        <p class="text-sm text-muted fw-600">stockvel · Stokvel management</p>
        <h1>Run your stokvel with confidence</h1>
        <p>Contributions, loans, payouts, meetings, and compliance-ready records — built for South African groups that need clarity without complexity.</p>
        <div class="quick-actions" style="margin-top:1.25rem;">
            <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>">Create account</a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('/login')) ?>">Sign in</a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/start-stokvel')) ?>">Register a stokvel</a>
        </div>
    </div>
    <div class="card">
        <h2 class="card-title">Built for real committee work</h2>
        <ul class="auth-points">
            <li>Isolated tenants with audited financial actions</li>
            <li>Maker-checker approvals and immutable journal posts</li>
            <li>Member statements, invitations, and document vault</li>
            <li>Works on desktop and mobile without framework lock-in</li>
        </ul>
    </div>
</section>

<section class="grid grid-3" style="margin:2rem 0 1rem;">
    <article class="card">
        <h2 class="card-title">Multi-tenant security</h2>
        <p class="text-muted">Each stokvel is isolated with role-based access, CSRF protection, and an append-only audit trail.</p>
    </article>
    <article class="card">
        <h2 class="card-title">Financial clarity</h2>
        <p class="text-muted">Track contributions, arrears, expenses, and member statements in South African Rand formatting.</p>
    </article>
    <article class="card">
        <h2 class="card-title">Member self-service</h2>
        <p class="text-muted">Members view balances, acknowledge minutes, and download authorised documents securely.</p>
    </article>
</section>
