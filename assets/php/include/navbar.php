<nav class="navbar navbar-expand-md navbar-dark app-navbar mb-4 shadow-sm">
    <div class="container-fluid app-shell px-3 px-lg-4 py-2">
        <div class="brand-wrap">
            <span class="brand-mark"><i class="fas fa-ticket-alt"></i></span>
            <span class="navbar-brand mb-0">
                PLANETFlow
                <span class="brand-subtitle">Invoice Generator</span>
            </span>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Buka menu navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <div class="navbar-actions ms-auto">
                <span class="navbar-status"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars(authDisplayName()) ?> (<?= authIsAdmin() ? 'admin' : 'user' ?>)</span>
                <?php if (authIsAdmin()): ?>
                    <?php if (in_array(basename($_SERVER['PHP_SELF'] ?? ''), ['managemen.php', 'setakun.php'], true)): ?>
                        <a href="index.php" class="btn btn-sm btn-outline-light"><i class="fas fa-house me-1"></i>Beranda</a>
                    <?php else: ?>
                        <a href="managemen.php" class="btn btn-sm btn-outline-light"><i class="fas fa-users-gear me-1"></i>Manajemen</a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-sm btn-light"><i class="fas fa-right-from-bracket me-1"></i>Logout</a>
            </div>
        </div>
    </div>
</nav>
