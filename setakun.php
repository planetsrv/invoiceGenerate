<?php
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'setakun.php') {
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: managemen.php'.($query !== '' ? '?'.$query : ''));
    exit;
}

require_once __DIR__.'/auth.php';

ensureAuthSchema();
requireAdmin();

$db = authDb();
$message = (string)($_SESSION['account_flash_message'] ?? '');
$error = (string)($_SESSION['account_flash_error'] ?? '');
unset($_SESSION['account_flash_message'], $_SESSION['account_flash_error']);

// Daftar billing dipakai oleh formulir tambah dan edit akun.
$billingRows = [];
$result = $db->query("SELECT id, nama FROM billing_master ORDER BY nama");
while ($result && $row = $result->fetch_assoc()) {
    $billingRows[(int)$row['id']] = $row['nama'];
}
$companySettings = getCompanySettings($db);

// Post/Redirect/Get mencegah formulir terkirim ulang ketika halaman di-refresh.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCsrf((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Permintaan tidak valid. Muat ulang halaman.');
        }

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_company') {
            $companyName = trim((string)($_POST['company_name'] ?? ''));
            $contactName = trim((string)($_POST['contact_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $website = trim((string)($_POST['website'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $paymentInfo = trim((string)($_POST['payment_info'] ?? ''));
            $invoiceNote = trim((string)($_POST['invoice_note'] ?? ''));
            $logoPath = (string)($companySettings['logo_path'] ?? '');

            if ($companyName === '') {
                throw new RuntimeException('Nama perusahaan wajib diisi.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Format email perusahaan tidak valid.');
            }
            if (strlen($companyName) > 150 || strlen($contactName) > 100
                || strlen($phone) > 50 || strlen($email) > 150 || strlen($website) > 150) {
                throw new RuntimeException('Salah satu informasi perusahaan melebihi batas karakter.');
            }

            $logo = $_FILES['company_logo'] ?? null;
            if (is_array($logo) && (int)($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int)$logo['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Logo gagal diunggah.');
                }
                if ((int)$logo['size'] > 2 * 1024 * 1024) {
                    throw new RuntimeException('Ukuran logo maksimal 2 MB.');
                }

                $imageInfo = @getimagesize((string)$logo['tmp_name']);
                $allowedLogoTypes = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                ];
                $mime = (string)($imageInfo['mime'] ?? '');
                if (!isset($allowedLogoTypes[$mime])) {
                    throw new RuntimeException('Logo harus berupa PNG, JPG, atau WEBP.');
                }

                $logoDirectory = __DIR__.'/assets/uploads/company';
                if (!is_dir($logoDirectory) && !mkdir($logoDirectory, 0755, true) && !is_dir($logoDirectory)) {
                    throw new RuntimeException('Folder penyimpanan logo tidak dapat dibuat.');
                }
                $logoFilename = 'logo_'.bin2hex(random_bytes(8)).'.'.$allowedLogoTypes[$mime];
                if (!move_uploaded_file((string)$logo['tmp_name'], $logoDirectory.'/'.$logoFilename)) {
                    throw new RuntimeException('Logo tidak dapat disimpan ke server.');
                }
                $logoPath = 'assets/uploads/company/'.$logoFilename;
            }

            $stmt = $db->prepare("INSERT INTO company_settings
                (id, company_name, contact_name, phone, email, website, address, payment_info, invoice_note, logo_path)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE company_name = VALUES(company_name), contact_name = VALUES(contact_name),
                    phone = VALUES(phone), email = VALUES(email), website = VALUES(website), address = VALUES(address),
                    payment_info = VALUES(payment_info), invoice_note = VALUES(invoice_note), logo_path = VALUES(logo_path)");
            $stmt->bind_param(
                'sssssssss',
                $companyName,
                $contactName,
                $phone,
                $email,
                $website,
                $address,
                $paymentInfo,
                $invoiceNote,
                $logoPath
            );
            $stmt->execute();
            $stmt->close();
            $_SESSION['account_flash_message'] = 'Informasi perusahaan untuk invoice berhasil disimpan.';
            $db->close();
            header('Location: managemen.php#company-profile');
            exit;
        }

        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;
        $billingIds = array_values(array_intersect(
            array_keys($billingRows),
            array_unique(array_map('intval', $_POST['billing_ids'] ?? []))
        ));

        $db->begin_transaction();

        if ($action === 'create') {
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $username)) {
                throw new RuntimeException(
                    'Username harus 3-50 karakter: huruf kecil, angka, titik, garis bawah, atau minus.'
                );
            }
            if ($password === '') {
                throw new RuntimeException('Password wajib diisi untuk akun baru.');
            }

            $stmt = $db->prepare(
                "INSERT INTO users (username, password, full_name, role, is_active)
                 VALUES (?, ?, ?, 'user', ?)"
            );
            $stmt->bind_param('sssi', $username, $password, $fullName, $active);
            $stmt->execute();
            $userId = $stmt->insert_id;
            $stmt->close();
            $successMessage = 'User '.$username.' berhasil dibuat.';
        } elseif ($action === 'update') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $stmt = $db->prepare(
                "SELECT username FROM users
                 WHERE id = ? AND role = 'user'
                 LIMIT 1"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$target) {
                throw new RuntimeException('User tidak ditemukan atau tidak dapat diedit.');
            }

            if ($password !== '') {
                $stmt = $db->prepare(
                    "UPDATE users
                     SET full_name = ?, password = ?, is_active = ?
                     WHERE id = ? AND role = 'user'"
                );
                $stmt->bind_param('ssii', $fullName, $password, $active, $userId);
            } else {
                $stmt = $db->prepare(
                    "UPDATE users
                     SET full_name = ?, is_active = ?
                     WHERE id = ? AND role = 'user'"
                );
                $stmt->bind_param('sii', $fullName, $active, $userId);
            }
            $stmt->execute();
            $stmt->close();
            $successMessage = 'Akun '.$target['username'].' berhasil diperbarui.';
        } else {
            throw new RuntimeException('Aksi akun tidak dikenal.');
        }

        $stmt = $db->prepare("DELETE FROM user_billing_access WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        if ($billingIds) {
            $stmt = $db->prepare(
                "INSERT INTO user_billing_access (user_id, billing_id) VALUES (?, ?)"
            );
            foreach ($billingIds as $billingId) {
                $stmt->bind_param('ii', $userId, $billingId);
                $stmt->execute();
            }
            $stmt->close();
        }

        $db->commit();
        $_SESSION['account_flash_message'] = $successMessage;
    } catch (Throwable $exception) {
        if ($db->errno === 1062 || (int)$exception->getCode() === 1062) {
            $errorMessage = 'Username sudah digunakan. Pilih username lain.';
        } else {
            $errorMessage = $exception->getMessage();
        }

        try {
            $db->rollback();
        } catch (Throwable) {
            // Transaksi mungkin belum dimulai ketika validasi awal gagal.
        }
        $_SESSION['account_flash_error'] = $errorMessage;
    }

    $db->close();
    header('Location: managemen.php');
    exit;
}

// Akun manajemen dan pelanggan dipisahkan, masing-masing 25 data per halaman.
const ACCOUNT_PAGE_SIZE = 25;
$managementPage = max(1, (int)($_GET['management_page'] ?? 1));
$customerPage = max(1, (int)($_GET['customer_page'] ?? 1));
$managementTotal = (int)($db->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'] ?? 0);
$customerTotal = (int)($db->query("SELECT COUNT(*) AS total FROM customer_accounts")->fetch_assoc()['total'] ?? 0);
$managementTotalPages = max(1, (int)ceil($managementTotal / ACCOUNT_PAGE_SIZE));
$customerTotalPages = max(1, (int)ceil($customerTotal / ACCOUNT_PAGE_SIZE));
$managementPage = min($managementPage, $managementTotalPages);
$customerPage = min($customerPage, $customerTotalPages);
$managementOffset = ($managementPage - 1) * ACCOUNT_PAGE_SIZE;
$customerOffset = ($customerPage - 1) * ACCOUNT_PAGE_SIZE;

$managementUsers = [];
$stmt = $db->prepare("SELECT id, username, password, full_name, role, is_active, created_at
    FROM users ORDER BY role = 'admin' DESC, username LIMIT ?, ?");
$pageSize = ACCOUNT_PAGE_SIZE;
$stmt->bind_param('ii', $managementOffset, $pageSize);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['billing_ids'] = [];
    $row['customer_awalan'] = null;
    $managementUsers['staff-'.(int)$row['id']] = $row;
}
$stmt->close();

$customerUsers = [];
$stmt = $db->prepare("SELECT id, username, password, full_name, customer_awalan, is_active, created_at
    FROM customer_accounts ORDER BY username LIMIT ?, ?");
$stmt->bind_param('ii', $customerOffset, $pageSize);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['role'] = 'customer';
    $row['billing_ids'] = [];
    $customerUsers['customer-'.(int)$row['id']] = $row;
}
$stmt->close();

$result = $db->query(
    "SELECT user_id, billing_id
     FROM user_billing_access
     ORDER BY billing_id"
);
while ($result && $row = $result->fetch_assoc()) {
    $userId = (int)$row['user_id'];
    $staffKey = 'staff-'.$userId;
    if (isset($managementUsers[$staffKey])) {
        $managementUsers[$staffKey]['billing_ids'][] = (int)$row['billing_id'];
    }
}

function accountPaginationItems(int $currentPage, int $totalPages): array {
    if ($totalPages <= 7) return range(1, $totalPages);
    $pages = [1, $totalPages];
    for ($page = $currentPage - 2; $page <= $currentPage + 2; $page++) {
        if ($page > 1 && $page < $totalPages) $pages[] = $page;
    }
    sort($pages);
    $items = [];
    $previous = 0;
    foreach (array_unique($pages) as $page) {
        if ($previous > 0 && $page > $previous + 1) $items[] = null;
        $items[] = $page;
        $previous = $page;
    }
    return $items;
}

$accountSections = [
    [
        'id' => 'management-accounts',
        'title' => 'Akun manajemen',
        'description' => 'Administrator dan user staf yang mengelola data serta akses billing.',
        'users' => $managementUsers,
        'page_key' => 'management_page',
        'page' => $managementPage,
        'total_pages' => $managementTotalPages,
        'total' => $managementTotal,
        'other_key' => 'customer_page',
        'other_page' => $customerPage,
    ],
    [
        'id' => 'customer-accounts',
        'title' => 'Akun pelanggan',
        'description' => 'Akun customer area yang dibuat otomatis ketika pelanggan ditambahkan.',
        'users' => $customerUsers,
        'page_key' => 'customer_page',
        'page' => $customerPage,
        'total_pages' => $customerTotalPages,
        'total' => $customerTotal,
        'other_key' => 'management_page',
        'other_page' => $managementPage,
    ],
];
$db->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen - PLANETFlow</title>
    <link rel="stylesheet" href="assets/vendor/poppins/poppins.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main-style.css">
    <link rel="icon" type="image/ico" href="assets/favicon.ico">
</head>
<body class="account-page">
<?php include __DIR__.'/assets/php/include/navbar.php'; ?>

<main class="container-fluid page-container app-shell account-page-container px-lg-4 pb-5">
    <section class="page-heading account-heading">
        <div>
            <div class="eyebrow">Administrasi</div>
            <h1 class="page-title">Manajemen</h1>
            <p class="page-description">
                Kelola identitas invoice, akun staf, akun pelanggan, dan akses billing.
            </p>
        </div>
        <button
            type="button"
            class="btn btn-primary account-add-button"
            data-bs-toggle="modal"
            data-bs-target="#createUserModal"
        >
            <i class="fas fa-user-plus me-1"></i>Tambah user
        </button>
    </section>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card panel company-settings-card" id="company-profile">
        <div class="card-body p-4">
            <div class="management-section-heading">
                <div>
                    <h2>Identitas perusahaan</h2>
                    <p>Informasi ini akan ditampilkan pada invoice PDF pelanggan.</p>
                </div>
                <?php
                $savedLogoPath = ltrim(str_replace('\\', '/', (string)$companySettings['logo_path']), '/');
                $hasCompanyLogo = $savedLogoPath !== '' && is_file(__DIR__.'/'.$savedLogoPath);
                ?>
                <div class="company-logo-preview <?= $hasCompanyLogo ? '' : 'is-empty' ?>">
                    <?php if ($hasCompanyLogo): ?>
                        <img src="<?= htmlspecialchars($savedLogoPath) ?>" alt="Logo <?= htmlspecialchars($companySettings['company_name']) ?>">
                    <?php else: ?>
                        <i class="fas fa-image"></i><span>Belum ada logo</span>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data" class="company-settings-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="action" value="save_company">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="companyName" class="form-label">Nama perusahaan</label>
                        <input id="companyName" name="company_name" class="form-control" maxlength="150" required value="<?= htmlspecialchars($companySettings['company_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="companyContact" class="form-label">Nama kontak</label>
                        <input id="companyContact" name="contact_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($companySettings['contact_name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="companyPhone" class="form-label">Nomor telepon</label>
                        <input id="companyPhone" name="phone" class="form-control" maxlength="50" value="<?= htmlspecialchars($companySettings['phone']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="companyEmail" class="form-label">Email</label>
                        <input id="companyEmail" type="email" name="email" class="form-control" maxlength="150" value="<?= htmlspecialchars($companySettings['email']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="companyWebsite" class="form-label">Website</label>
                        <input id="companyWebsite" name="website" class="form-control" maxlength="150" placeholder="https://..." value="<?= htmlspecialchars($companySettings['website']) ?>">
                    </div>
                    <div class="col-md-8">
                        <label for="companyAddress" class="form-label">Alamat perusahaan</label>
                        <textarea id="companyAddress" name="address" class="form-control" rows="3" maxlength="2000"><?= htmlspecialchars($companySettings['address']) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="companyLogo" class="form-label">Logo perusahaan</label>
                        <input id="companyLogo" type="file" name="company_logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">PNG, JPG, atau WEBP. Maksimal 2 MB.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="paymentInfo" class="form-label">Informasi pembayaran</label>
                        <textarea id="paymentInfo" name="payment_info" class="form-control" rows="3" maxlength="2000" placeholder="Contoh: Bank, nomor rekening, dan nama pemilik"><?= htmlspecialchars($companySettings['payment_info']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="invoiceNote" class="form-label">Catatan invoice</label>
                        <textarea id="invoiceNote" name="invoice_note" class="form-control" rows="3" maxlength="2000" placeholder="Contoh: Terima kasih atas kepercayaan Anda"><?= htmlspecialchars($companySettings['invoice_note']) ?></textarea>
                    </div>
                </div>
                <div class="company-settings-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Simpan informasi</button>
                </div>
            </form>
        </div>
    </section>

    <?php foreach ($accountSections as $section): ?>
        <section class="card panel account-section" id="<?= $section['id'] ?>">
            <div class="card-body p-0">
                <div class="management-account-heading p-4 pb-2">
                    <div>
                        <h2 class="h5 mb-1"><?= htmlspecialchars($section['title']) ?></h2>
                        <p class="small text-muted mb-0"><?= htmlspecialchars($section['description']) ?></p>
                    </div>
                    <span class="account-section-count"><?= number_format($section['total'], 0, ',', '.') ?> akun</span>
                </div>

                <div class="file-card-list account-card-list p-3 pt-2">
                    <?php if (!$section['users']): ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-users-slash"></i>
                            <strong>Belum ada akun</strong>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($section['users'] as $user): ?>
                        <?php
                        $assignedBillingNames = [];
                        foreach ($user['billing_ids'] as $billingId) {
                            if (isset($billingRows[$billingId])) {
                                $assignedBillingNames[] = $billingRows[$billingId];
                            }
                        }

                        $isEditable = $user['role'] === 'user';
                        $modalData = [
                            'id' => (int)$user['id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'],
                            'is_active' => (bool)$user['is_active'],
                            'billing_ids' => array_values($user['billing_ids']),
                        ];
                        $rowAttributes = $isEditable
                            ? 'data-user="'.htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8').'" tabindex="0" role="button"'
                            : '';
                        ?>
                        <article class="file-entry account-entry <?= $isEditable ? 'user-list-row' : '' ?> <?= $user['role'] === 'customer' ? 'customer-account-entry' : 'management-account-entry' ?>" <?= $rowAttributes ?>>
                            <div class="file-entry-main">
                                <span class="file-entry-icon account-icon">
                                    <i class="fas <?= $user['role'] === 'admin' ? 'fa-user-shield' : ($user['role'] === 'customer' ? 'fa-user-tag' : 'fa-user-gear') ?>"></i>
                                </span>

                                <div class="file-entry-info">
                                    <h3>
                                        <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="badge text-bg-primary ms-1">Administrator</span>
                                        <?php elseif ($user['role'] === 'customer'): ?>
                                            <span class="badge text-bg-success ms-1">Pelanggan</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary ms-1">Manajemen</span>
                                        <?php endif; ?>
                                    </h3>

                                    <div class="file-entry-subtitle"><?= htmlspecialchars($user['full_name'] ?: 'Nama belum diisi') ?></div>
                                    <div class="file-entry-meta account-entry-meta">
                                        <span>
                                            <i class="fas fa-building"></i><strong>Akses:</strong>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                Semua billing
                                            <?php elseif ($user['role'] === 'customer'): ?>
                                                Customer area &middot; <?= htmlspecialchars($user['customer_awalan'] ?: 'Tanpa pelanggan') ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($assignedBillingNames ? implode(', ', $assignedBillingNames) : 'Belum ada akses') ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($user['role'] === 'customer'): ?>
                                            <span><i class="fas fa-key"></i><strong>Password awal:</strong> <?= htmlspecialchars($user['password']) ?></span>
                                        <?php endif; ?>
                                        <span>
                                            <i class="fas <?= $user['is_active'] ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?>"></i>
                                            <strong>Status:</strong> <?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                        <span><i class="fas fa-clock"></i><strong>Dibuat:</strong> <?= htmlspecialchars($user['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($isEditable): ?>
                                <div class="file-entry-actions account-entry-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary account-edit-button" aria-label="Edit akun <?= htmlspecialchars($user['username']) ?>">
                                        <i class="fas fa-pen"></i><span>Edit</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($section['total_pages'] > 1): ?>
                    <nav class="date-pagination account-pagination" aria-label="Halaman <?= htmlspecialchars($section['title']) ?>">
                        <div class="pagination-caption">Halaman <?= $section['page'] ?> dari <?= $section['total_pages'] ?><span>&middot; <?= $section['total'] ?> akun</span></div>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $section['page'] <= 1 ? 'disabled' : '' ?>">
                                <?php if ($section['page'] > 1): ?>
                                    <a class="page-link" href="?<?= $section['page_key'] ?>=<?= $section['page'] - 1 ?>&amp;<?= $section['other_key'] ?>=<?= $section['other_page'] ?>#<?= $section['id'] ?>">&lsaquo;</a>
                                <?php else: ?><span class="page-link">&lsaquo;</span><?php endif; ?>
                            </li>
                            <?php foreach (accountPaginationItems($section['page'], $section['total_pages']) as $pageItem): ?>
                                <?php if ($pageItem === null): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php else: ?>
                                    <li class="page-item <?= $pageItem === $section['page'] ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= $section['page_key'] ?>=<?= $pageItem ?>&amp;<?= $section['other_key'] ?>=<?= $section['other_page'] ?>#<?= $section['id'] ?>"><?= $pageItem ?></a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <li class="page-item <?= $section['page'] >= $section['total_pages'] ? 'disabled' : '' ?>">
                                <?php if ($section['page'] < $section['total_pages']): ?>
                                    <a class="page-link" href="?<?= $section['page_key'] ?>=<?= $section['page'] + 1 ?>&amp;<?= $section['other_key'] ?>=<?= $section['other_page'] ?>#<?= $section['id'] ?>">&rsaquo;</a>
                                <?php else: ?><span class="page-link">&rsaquo;</span><?php endif; ?>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</main>

<!-- Modal tambah user hanya dibuka melalui tombol Tambah user. -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5">Tambah user</h2>
                        <div class="small text-muted">Buat akun staf dan tentukan akses billing.</div>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup"
                    ></button>
                </div>

                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="createUsername" class="form-label">Username</label>
                            <input
                                id="createUsername"
                                name="username"
                                class="form-control"
                                required
                                pattern="[a-z0-9_.-]{3,50}"
                                autocomplete="off"
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="createFullName" class="form-label">Nama lengkap</label>
                            <input
                                id="createFullName"
                                name="full_name"
                                class="form-control"
                                autocomplete="off"
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="createPassword" class="form-label">Password</label>
                            <input
                                id="createPassword"
                                type="password"
                                name="password"
                                class="form-control"
                                required
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Akses billing</label>
                            <div class="billing-box border rounded p-3 row g-2">
                                <?php if (!$billingRows): ?>
                                    <span class="text-muted">
                                        Belum ada billing. Buat billing melalui halaman utama terlebih dahulu.
                                    </span>
                                <?php endif; ?>
                                <?php foreach ($billingRows as $id => $name): ?>
                                    <label class="col-md-4">
                                        <input
                                            type="checkbox"
                                            class="form-check-input me-1"
                                            name="billing_ids[]"
                                            value="<?= $id ?>"
                                        >
                                        <?= htmlspecialchars($name) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <label>
                                <input
                                    type="checkbox"
                                    class="form-check-input me-1"
                                    name="is_active"
                                    checked
                                >
                                Akun aktif
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary account-submit">Buat user</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pengaturan akun user. -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editUserForm" autocomplete="off">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5">Edit user</h2>
                        <div class="small text-muted">Atur akun dan billing yang dapat diakses.</div>
                    </div>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup"
                    ></button>
                </div>

                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="editUserId">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="editUsername" class="form-label">Username</label>
                            <input
                                id="editUsername"
                                class="form-control username bg-light"
                                readonly
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="editFullName" class="form-label">Nama lengkap</label>
                            <input
                                id="editFullName"
                                name="full_name"
                                class="form-control"
                                autocomplete="off"
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="editPassword" class="form-label">Password baru</label>
                            <input
                                id="editPassword"
                                type="password"
                                name="password"
                                class="form-control"
                                placeholder="Kosongkan jika tetap"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Akses billing</label>
                            <div class="billing-box border rounded p-3 row g-2">
                                <?php if (!$billingRows): ?>
                                    <span class="text-muted">Belum ada billing.</span>
                                <?php endif; ?>
                                <?php foreach ($billingRows as $id => $name): ?>
                                    <label class="col-md-4">
                                        <input
                                            type="checkbox"
                                            class="form-check-input me-1 edit-billing"
                                            name="billing_ids[]"
                                            value="<?= $id ?>"
                                        >
                                        <?= htmlspecialchars($name) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <label>
                                <input
                                    type="checkbox"
                                    class="form-check-input me-1"
                                    name="is_active"
                                    id="editIsActive"
                                >
                                Akun aktif
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    const editModalElement = document.getElementById('editUserModal');
    const editModal = bootstrap.Modal.getOrCreateInstance(editModalElement);
    const editForm = document.getElementById('editUserForm');

    function openUserModal(row) {
        const user = JSON.parse(row.dataset.user);
        const allowedBillingIds = (user.billing_ids || []).map(Number);

        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editFullName').value = user.full_name || '';
        document.getElementById('editIsActive').checked = Boolean(user.is_active);
        document.getElementById('editPassword').value = '';

        document.querySelectorAll('.edit-billing').forEach(input => {
            input.checked = allowedBillingIds.includes(Number(input.value));
        });

        editModal.show();
    }

    document.querySelectorAll('.user-list-row').forEach(row => {
        row.addEventListener('click', () => openUserModal(row));
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openUserModal(row);
            }
        });
    });

    // Pastikan data sensitif pada modal tidak tertinggal setelah ditutup.
    document.getElementById('createUserModal').addEventListener('hidden.bs.modal', event => {
        event.currentTarget.querySelector('form').reset();
    });
    editModalElement.addEventListener('hidden.bs.modal', () => {
        editForm.reset();
    });
</script>
</body>
</html>
