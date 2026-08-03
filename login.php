<?php
require_once __DIR__.'/auth.php';
ensureAuthSchema();
if (authUserId() > 0) {
    header('Location: index.php');
    exit;
}

$error = '';
$lockRemaining = loginThrottleState('management')['remaining'];
$next = trim($_GET['next'] ?? $_POST['next'] ?? 'index.php');
if ($next === '' || !preg_match('/^[A-Za-z0-9_.?=&%\-]+$/', $next)) $next = 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($lockRemaining > 0) {
        $error = 'Login dikunci sementara.';
    } elseif (!verifyCsrf((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Permintaan tidak valid. Muat ulang halaman dan coba kembali.';
    } else {
        $db = authDb();
        $stmt = $db->prepare("SELECT id, username, password, full_name, role, is_active
            FROM users WHERE username = ? AND role IN ('admin', 'user') LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $db->close();
        if ($user && (int)$user['is_active'] === 1 && hash_equals((string)$user['password'], $password)) {
            loginThrottleClear('management');
            session_regenerate_id(true);
            $_SESSION['auth_user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ];
            header('Location: '.$next);
            exit;
        }
        $throttle = loginThrottleFailure('management');
        $lockRemaining = $throttle['remaining'];
        $error = $lockRemaining > 0
            ? 'Terlalu banyak percobaan login yang gagal.'
            : 'Username atau password salah, atau akun tidak aktif.';
    }
}
$lockRemaining = loginThrottleState('management')['remaining'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - PLANETFlow</title>
    <link rel="stylesheet" href="assets/vendor/poppins/poppins.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main-style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <div class="login-card">
            <aside class="login-hero">
                <div class="login-brand">
                    <span class="login-brand-mark"><i class="fas fa-ticket-alt"></i></span>
                    <span>PLANETFlow</span>
                </div>
                <div class="login-hero-content">
                    <span class="login-hero-label">Invoice workspace</span>
                    <h1>Kelola voucher dan invoice dalam satu tempat.</h1>
                    <p>Impor data, susun rekap, dan hasilkan dokumen dengan alur kerja yang lebih ringkas.</p>
                </div>
                <div class="login-hero-note"><i class="fas fa-shield-halved"></i> Akses dilindungi autentikasi akun</div>
            </aside>

            <section class="login-form-panel">
                <div class="login-mobile-brand">
                    <span class="login-brand-mark"><i class="fas fa-ticket-alt"></i></span>
                    <span>PLANETFlow</span>
                </div>
                <div class="eyebrow">Area manajemen</div>
                <h2 class="login-title">Masuk sebagai staf</h2>
                <p class="login-description">Gunakan akun admin atau user manajemen untuk melanjutkan.</p>

                <?php if ($lockRemaining > 0): ?>
                    <div class="alert alert-warning login-alert" id="loginLockAlert" role="alert" data-login-lock="<?= $lockRemaining ?>">
                        <i class="fas fa-clock"></i>
                        <span>Tiga kali percobaan gagal. Coba lagi dalam <strong data-login-countdown><?= $lockRemaining ?></strong> detik.</span>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger login-alert" role="alert">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" class="login-form" data-login-lock-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

                    <div class="login-field">
                        <label for="loginUsername" class="form-label">Username</label>
                        <div class="login-input-wrap">
                            <i class="fas fa-user"></i>
                            <input id="loginUsername" class="form-control" name="username" required autofocus autocomplete="username" placeholder="Masukkan username" <?= $lockRemaining > 0 ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="loginPassword" class="form-label">Password</label>
                        <div class="login-input-wrap">
                            <i class="fas fa-lock"></i>
                            <input id="loginPassword" type="password" class="form-control" name="password" required autocomplete="current-password" placeholder="Masukkan password" <?= $lockRemaining > 0 ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <button class="btn btn-primary login-submit" type="submit" <?= $lockRemaining > 0 ? 'disabled' : '' ?>>
                        <span>Masuk</span><i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <div class="login-customer-link">
                    Anda pelanggan? <a href="customer/login.php">Masuk ke customer area</a>
                </div>
            </section>
        </div>
    </main>
    <script src="assets/js/login-lock.js"></script>
</body>
</html>
