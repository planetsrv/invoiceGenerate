<nav class="customer-navbar">
    <div class="customer-navbar-inner">
        <a href="index.php" class="customer-brand" aria-label="Beranda pelanggan PLANETFlow">
            <span class="customer-brand-mark"><i class="fas fa-user"></i></span>
            <span>
                PLANETFlow
                <small>Customer Area</small>
            </span>
        </a>

        <div class="customer-nav-actions">
            <span class="customer-nav-user">
                <i class="fas fa-circle-user"></i>
                <span><?= htmlspecialchars(customerAuthName()) ?></span>
            </span>
            <a href="logout.php" class="btn btn-sm customer-logout-button">
                <i class="fas fa-arrow-right-from-bracket"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>
</nav>
