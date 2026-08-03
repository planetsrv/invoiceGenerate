<?php
require_once __DIR__.'/auth.php';

if (customerAuthId() > 0) {
    header('Location: index.php');
    exit;
}

$error = '';
$lockRemaining = loginThrottleState('customer')['remaining'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernamePart = ltrim(strtolower(trim((string)($_POST['username'] ?? ''))), '@');
    $usernameWithPrefix = '@'.$usernamePart;
    $password = (string)($_POST['password'] ?? '');

    if ($lockRemaining > 0) {
        $error = 'Login dikunci sementara.';
    } elseif (!verifyCustomerCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Permintaan tidak valid. Muat ulang halaman dan coba kembali.';
    } else {
        $db = authDb();
        $stmt = $db->prepare("SELECT id, customer_awalan, username, password, full_name, is_active
            FROM customer_accounts WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $usernameWithPrefix);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $db->close();

        if ($account && (int)$account['is_active'] === 1
            && hash_equals((string)$account['password'], $password)) {
            loginThrottleClear('customer');
            session_regenerate_id(true);
            $_SESSION['customer_auth'] = [
                'id' => (int)$account['id'],
                'customer_awalan' => $account['customer_awalan'],
                'username' => $account['username'],
                'full_name' => $account['full_name'],
            ];
            header('Location: index.php');
            exit;
        }

        $throttle = loginThrottleFailure('customer');
        $lockRemaining = $throttle['remaining'];
        $error = $lockRemaining > 0
            ? 'Terlalu banyak percobaan login yang gagal.'
            : 'Username atau password pelanggan salah, atau akun tidak aktif.';
    }
}
$lockRemaining = loginThrottleState('customer')['remaining'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Pelanggan - PLANETFlow</title>
    <link rel="stylesheet" href="../assets/vendor/poppins/poppins.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main-style.css">
    <link rel="stylesheet" href="assets/customer-style.css">
    <link rel="icon" type="image/ico" href="../assets/favicon.ico">
</head>
<body class="customer-login-page">
    <main class="customer-login-shell">
        <section class="customer-login-welcome">
            <div class="customer-login-brand">
                <span><i class="fas fa-user"></i></span>
                <strong>PLANETFlow <small>Customer Area</small></strong>
            </div>
            <div class="customer-welcome-copy">
                <span class="customer-welcome-label">Selamat datang</span>
                <h1>Pantau layanan dan tagihan Anda dengan mudah.</h1>
                <p>Masuk menggunakan akun pelanggan yang diberikan saat data pelanggan dibuat.</p>
            </div>
            <div class="customer-welcome-note">
                <i class="fas fa-shield-heart"></i>
                Area ini khusus untuk pelanggan PLANETFlow.
            </div>
        </section>

        <section class="customer-login-form-panel">
            <div class="customer-login-mobile-brand">
                <span><i class="fas fa-user"></i></span>
                <strong>PLANETFlow <small>Customer Area</small></strong>
            </div>
            <span class="customer-form-eyebrow">Portal pelanggan</span>
            <h2>Masuk ke customer area</h2>
            <p class="customer-login-description">Gunakan username dan password pelanggan Anda.</p>

            <?php if ($lockRemaining > 0): ?>
                <div class="alert alert-warning customer-login-alert" id="loginLockAlert" role="alert" data-login-lock="<?= $lockRemaining ?>">
                    <i class="fas fa-clock"></i>
                    <span>Tiga kali percobaan gagal. Coba lagi dalam <strong data-login-countdown><?= $lockRemaining ?></strong> detik.</span>
                </div>
            <?php elseif ($error !== ''): ?>
                <div class="alert alert-danger customer-login-alert" role="alert">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="customer-login-form" data-login-lock-form>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">

                <div>
                    <label for="customerUsername" class="form-label">Username pelanggan</label>
                    <div class="customer-login-input customer-login-username">
                        <span class="customer-username-prefix" aria-hidden="true">@</span>
                        <input
                            id="customerUsername"
                            name="username"
                            class="form-control"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="username pelanggan"
                            <?= $lockRemaining > 0 ? 'disabled' : '' ?>
                        >
                    </div>
                </div>

                <div>
                    <label for="customerPassword" class="form-label">Password</label>
                    <div class="customer-login-input">
                        <i class="fas fa-lock"></i>
                        <input
                            id="customerPassword"
                            type="password"
                            name="password"
                            class="form-control"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan password"
                            <?= $lockRemaining > 0 ? 'disabled' : '' ?>
                        >
                    </div>
                </div>

                <button type="submit" class="btn customer-login-submit" <?= $lockRemaining > 0 ? 'disabled' : '' ?>>
                    <span>Masuk sebagai pelanggan</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="customer-staff-login">
                Bukan pelanggan? <a href="../login.php">Masuk ke area manajemen</a>
            </div>
        </section>
    </main>
    <script src="../assets/js/login-lock.js"></script>
</body>
</html>
