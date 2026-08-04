<?php
require_once dirname(__DIR__).'/auth.php';

ensureAuthSchema();

function customerAuthAccount(): ?array {
    return isset($_SESSION['customer_auth']) && is_array($_SESSION['customer_auth'])
        ? $_SESSION['customer_auth']
        : null;
}

function customerAuthId(): int {
    return (int)(customerAuthAccount()['id'] ?? 0);
}

function customerAuthName(): string {
    $account = customerAuthAccount();
    return trim((string)($account['full_name'] ?? ''))
        ?: (string)($account['username'] ?? 'Pelanggan');
}

function customerAuthPrefix(): string {
    return (string)(customerAuthAccount()['customer_prefix'] ?? '');
}

function customerRequireLogin(string $loginPath = 'login.php'): void {
    $accountId = customerAuthId();
    if ($accountId > 0) {
        $db = authDb();
        $stmt = $db->prepare("SELECT id, customer_prefix, username, full_name, is_active
            FROM customer_accounts WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $db->close();
        if ($account && (int)$account['is_active'] === 1) {
            $_SESSION['customer_auth'] = [
                'id' => (int)$account['id'],
                'customer_prefix' => $account['customer_prefix'],
                'username' => $account['username'],
                'full_name' => $account['full_name'],
            ];
            return;
        }
        unset($_SESSION['customer_auth']);
    }
    header('Location: '.$loginPath);
    exit;
}
function customerCsrfToken(): string {
    if (empty($_SESSION['customer_csrf_token'])) {
        $_SESSION['customer_csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['customer_csrf_token'];
}

function verifyCustomerCsrf(string $token): bool {
    return isset($_SESSION['customer_csrf_token'])
        && hash_equals($_SESSION['customer_csrf_token'], $token);
}
