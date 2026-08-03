<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'voucher_db');

function authDb(): mysqli {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($db->connect_error) {
        throw new RuntimeException('Koneksi database autentikasi gagal.');
    }
    $db->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->select_db(DB_NAME);
    $db->set_charset('utf8mb4');
    return $db;
}

function ensureAuthSchema(): void {
    static $done = false;
    if ($done) return;

    $db = authDb();
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL DEFAULT '',
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS user_billing_access (
        user_id INT NOT NULL,
        billing_id INT NOT NULL,
        PRIMARY KEY (user_id, billing_id),
        INDEX idx_access_billing (billing_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS customer_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_awalan VARCHAR(10) NOT NULL UNIQUE,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS company_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        company_name VARCHAR(150) NOT NULL DEFAULT 'PLANETFlow',
        contact_name VARCHAR(100) NOT NULL DEFAULT '',
        phone VARCHAR(50) NOT NULL DEFAULT '',
        email VARCHAR(150) NOT NULL DEFAULT '',
        website VARCHAR(150) NOT NULL DEFAULT '',
        address TEXT NULL,
        payment_info TEXT NULL,
        invoice_note TEXT NULL,
        logo_path VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("INSERT IGNORE INTO company_settings (id, company_name) VALUES (1, 'PLANETFlow')");

    // Password sengaja disimpan tanpa hashing sesuai kebutuhan aplikasi lokal.
    $stmt = $db->prepare("INSERT IGNORE INTO users (username, password, full_name, role, is_active)
        VALUES ('admin', 'admin', 'Administrator', 'admin', 1)");
    $stmt->execute();
    $stmt->close();
    $db->close();
    $done = true;
}

function authUser(): ?array {
    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
        ? $_SESSION['auth_user']
        : null;
}

function authUserId(): int {
    return (int)(authUser()['id'] ?? 0);
}

function authUsername(): string {
    return (string)(authUser()['username'] ?? '');
}

function authDisplayName(): string {
    $user = authUser();
    return trim((string)($user['full_name'] ?? '')) ?: (string)($user['username'] ?? '');
}

function authIsAdmin(): bool {
    return (authUser()['role'] ?? '') === 'admin';
}

/** Mengambil identitas perusahaan yang digunakan pada invoice pelanggan. */
function getCompanySettings(?mysqli $db = null): array {
    $ownsDb = $db === null;
    $db ??= authDb();
    $result = $db->query("SELECT company_name, contact_name, phone, email, website,
        address, payment_info, invoice_note, logo_path
        FROM company_settings WHERE id = 1 LIMIT 1");
    $settings = $result ? $result->fetch_assoc() : null;
    if ($ownsDb) $db->close();

    return array_merge([
        'company_name' => 'PLANETFlow',
        'contact_name' => '',
        'phone' => '',
        'email' => '',
        'website' => '',
        'address' => '',
        'payment_info' => '',
        'invoice_note' => '',
        'logo_path' => '',
    ], is_array($settings) ? $settings : []);
}

function authRequestWantsJson(): bool {
    return isset($_GET['action']) || isset($_POST['action']) || isset($_FILES['excelfile']);
}

function requireLogin(?bool $json = null): void {
    if (authUserId() > 0) {
        $db = authDb();
        $userId = authUserId();
        $stmt = $db->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $db->close();
        if ($user && (int)$user['is_active'] === 1) {
            $_SESSION['auth_user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ];
            return;
        }
        unset($_SESSION['auth_user']);
    }
    $json ??= authRequestWantsJson();
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sesi login berakhir. Silakan login kembali.']);
        exit;
    }
    $target = basename(parse_url($_SERVER['REQUEST_URI'] ?? 'index.php', PHP_URL_PATH));
    if ($target === '') $target = 'index.php';
    header('Location: login.php?next='.rawurlencode($target));
    exit;
}

function requireAdmin(): void {
    requireLogin(false);
    if (authIsAdmin()) return;
    http_response_code(403);
    exit('Akses hanya tersedia untuk administrator.');
}

function authAllowedBillingIds(?mysqli $db = null): array {
    if (authIsAdmin()) return [];
    $ownsDb = $db === null;
    $db ??= authDb();
    $userId = authUserId();
    $stmt = $db->prepare("SELECT billing_id FROM user_billing_access WHERE user_id = ? ORDER BY billing_id");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) $ids[] = (int)$row['billing_id'];
    $stmt->close();
    if ($ownsDb) $db->close();
    return $ids;
}

function authCanAccessBilling(int $billingId, ?mysqli $db = null): bool {
    if (authIsAdmin()) return true;
    if ($billingId <= 0) return false;
    return in_array($billingId, authAllowedBillingIds($db), true);
}

function authBillingCondition(string $column, ?mysqli $db = null): string {
    if (authIsAdmin()) return '1=1';
    $ids = authAllowedBillingIds($db);
    if (!$ids) return '1=0';
    return $column.' IN ('.implode(',', array_map('intval', $ids)).')';
}

function authRequireBilling(int $billingId, ?mysqli $db = null): void {
    if (authIsAdmin() || authCanAccessBilling($billingId, $db)) return;
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses ke billing tersebut.']);
    exit;
}

function authCanAccessDocument(array $document, ?mysqli $db = null): bool {
    if (authIsAdmin()) return true;
    $billingId = (int)($document['billing_id'] ?? 0);
    if ($billingId > 0) return authCanAccessBilling($billingId, $db);
    return (int)($document['generated_by_user_id'] ?? 0) === authUserId();
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Status pembatas percobaan login berbasis sesi. */
function loginThrottleState(string $scope): array {
    $key = 'login_throttle_'.$scope;
    $state = $_SESSION[$key] ?? ['attempts' => 0, 'locked_until' => 0];
    $lockedUntil = (int)($state['locked_until'] ?? 0);
    if ($lockedUntil > 0 && $lockedUntil <= time()) {
        $state = ['attempts' => 0, 'locked_until' => 0];
        $_SESSION[$key] = $state;
    }
    return [
        'attempts' => max(0, (int)($state['attempts'] ?? 0)),
        'locked_until' => max(0, (int)($state['locked_until'] ?? 0)),
        'remaining' => max(0, $lockedUntil - time()),
    ];
}

/** Mencatat kegagalan dan mengunci login selama 60 detik setelah kegagalan ketiga. */
function loginThrottleFailure(string $scope): array {
    $key = 'login_throttle_'.$scope;
    $state = loginThrottleState($scope);
    $attempts = $state['attempts'] + 1;
    $lockedUntil = 0;
    if ($attempts >= 3) {
        $lockedUntil = time() + 60;
    }
    $_SESSION[$key] = ['attempts' => $attempts, 'locked_until' => $lockedUntil];
    return loginThrottleState($scope);
}

function loginThrottleClear(string $scope): void {
    unset($_SESSION['login_throttle_'.$scope]);
}

/** Membuat satu akun pada tabel khusus pelanggan. */
function createCustomerAccount(mysqli $db, string $awalan, string $name): array {
    $awalan = strtoupper(trim($awalan));
    $name = trim($name);

    $stmt = $db->prepare("SELECT id, username, password FROM customer_accounts WHERE customer_awalan = ? LIMIT 1");
    $stmt->bind_param('s', $awalan);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) return $existing + ['created' => false];

    $plainName = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name;
    if ($plainName === false) $plainName = $name;
    $namePart = preg_replace('/[^a-zA-Z0-9]+/', '', $plainName);
    $prefixPart = preg_replace('/[^a-zA-Z0-9]+/', '', $awalan);
    $usernamePart = strtolower($prefixPart.$namePart);
    if (strlen($usernamePart) < 3) $usernamePart = strtolower($prefixPart).'client';
    $base = '@'.substr($usernamePart, 0, 49);
    $username = $base;
    $suffix = 1;

    $check = $db->prepare("SELECT id FROM customer_accounts WHERE username = ? LIMIT 1");
    while (true) {
        $check->bind_param('s', $username);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) break;
        $suffix++;
        $suffixText = (string)$suffix;
        $username = substr($base, 0, 50 - strlen($suffixText)).$suffixText;
    }
    $check->close();

    $password = $name;
    $active = 1;
    $stmt = $db->prepare("INSERT INTO customer_accounts (customer_awalan, username, password, full_name, is_active) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssi', $awalan, $username, $password, $name, $active);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    return ['id' => $id, 'username' => $username, 'password' => $password, 'created' => true];
}

function requireStaff(?bool $json = null): void {
    requireLogin($json);
}
