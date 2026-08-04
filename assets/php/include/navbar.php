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
                <?php $currentManagementPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
                <?php if ($currentManagementPage !== 'index.php'): ?>
                    <a href="index.php" class="btn btn-sm btn-outline-light"><i class="fas fa-house me-1"></i>Beranda</a>
                <?php endif; ?>
                <?php if ($currentManagementPage !== 'pelanggan.php'): ?>
                    <a href="pelanggan.php" class="btn btn-sm btn-outline-light"><i class="fas fa-address-book me-1"></i>Pelanggan</a>
                <?php endif; ?>
                <?php if ($currentManagementPage !== 'files.php'): ?>
                    <a href="files.php" class="btn btn-sm btn-outline-light"><i class="fas fa-folder-open me-1"></i>Files</a>
                <?php endif; ?>
                <?php if (authIsAdmin()): ?>
                    <?php if (!in_array($currentManagementPage, ['managemen.php', 'setakun.php'], true)): ?>
                        <a href="managemen.php" class="btn btn-sm btn-outline-light"><i class="fas fa-users-gear me-1"></i>Manajemen</a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="customer/login.php" class="btn btn-sm btn-outline-light"><i class="fas fa-user me-1"></i>Customer area</a>
                <a href="logout.php" class="btn btn-sm btn-light"><i class="fas fa-right-from-bracket me-1"></i>Logout</a>
            </div>
        </div>
    </div>
</nav>
