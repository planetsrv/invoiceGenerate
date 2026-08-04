<?php
require_once __DIR__.'/auth.php';

customerRequireLogin();

$db = authDb();
$prefix = customerAuthPrefix();
$customer = null;
$invoices = [];
$invoiceTotal = 0;
$totalHistory = 0.0;
$latestInvoice = null;
$invoicePerPage = 25;
$invoicePage = max(1, (int)($_GET['invoice_page'] ?? 1));
$invoiceTotalPages = 1;
$profileMessage = (string)($_SESSION['customer_profile_message'] ?? '');
$profileError = (string)($_SESSION['customer_profile_error'] ?? '');
$profileEditField = (string)($_SESSION['customer_profile_edit_field'] ?? '');
unset(
    $_SESSION['customer_profile_message'],
    $_SESSION['customer_profile_error'],
    $_SESSION['customer_profile_edit_field']
);

if ($prefix !== '') {
    $stmt = $db->prepare("SELECT c.prefix, c.nama_pelanggan, c.alamat, c.telepon, c.billing_id,
            COALESCE(b.nama, 'Belum ditentukan') AS billing_name
        FROM prefix_customers c
        LEFT JOIN billing_master b ON b.id = c.billing_id
        WHERE c.prefix = ? LIMIT 1");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Ringkasan dihitung dari seluruh riwayat, bukan hanya halaman yang aktif.
    $stmt = $db->prepare("SELECT COUNT(*) AS total_rows,
            COALESCE(SUM(i.total_harga), 0) AS total_history
        FROM invoices i
        WHERE i.prefix = ?");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $invoiceTotal = (int)($summary['total_rows'] ?? 0);
    $totalHistory = (float)($summary['total_history'] ?? 0);
    $invoiceTotalPages = max(1, (int)ceil($invoiceTotal / $invoicePerPage));
    $invoicePage = min($invoicePage, $invoiceTotalPages);
    $invoiceOffset = ($invoicePage - 1) * $invoicePerPage;

    $stmt = $db->prepare("SELECT f.id AS file_id, i.total_harga
        FROM invoices i
        INNER JOIN uploaded_files f ON f.id = i.file_id
        WHERE i.prefix = ?
        ORDER BY f.uploaded_at DESC, f.id DESC
        LIMIT 1");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $latestInvoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Ambil hanya 25 invoice pada halaman aktif. Rincian paket diambil setelahnya
    // berdasarkan ID tersebut agar riwayat lama tidak ikut dikelompokkan database.
    $stmt = $db->prepare("SELECT f.id,
            f.uploaded_at, f.periode, f.tanggal, i.total_harga
        FROM invoices i
        INNER JOIN uploaded_files f ON f.id = i.file_id
        WHERE i.prefix = ?
        ORDER BY f.uploaded_at DESC, f.id DESC
        LIMIT ?, ?");
    $stmt->bind_param('sii', $prefix, $invoiceOffset, $invoicePerPage);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['total_voucher'] = 0;
        $row['package_summary'] = '';
        $invoices[] = $row;
    }
    $stmt->close();

    if ($invoices) {
        $invoiceIndexes = [];
        foreach ($invoices as $index => $invoiceRow) {
            $invoiceIndexes[(int)$invoiceRow['id']] = $index;
        }
        $fileIds = implode(',', array_keys($invoiceIndexes));
        $stmt = $db->prepare("SELECT file_id, paket, jumlah FROM rekap
            WHERE prefix = ? AND file_id IN ({$fileIds}) ORDER BY file_id DESC, paket ASC");
        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $details = $stmt->get_result();
        $packageRows = [];
        while ($detail = $details->fetch_assoc()) {
            $fileId = (int)$detail['file_id'];
            if (!isset($invoiceIndexes[$fileId])) continue;
            $index = $invoiceIndexes[$fileId];
            $invoices[$index]['total_voucher'] += (int)$detail['jumlah'];
            $packageRows[$fileId][] = $detail['paket'].': '.$detail['jumlah'];
        }
        $stmt->close();
        foreach ($packageRows as $fileId => $rows) {
            $invoices[$invoiceIndexes[$fileId]]['package_summary'] = implode('||', $rows);
        }
    }
}
$db->close();

function customerCurrency(float $value): string {
    return 'Rp '.number_format($value, 0, ',', '.');
}

function customerPaginationItems(int $currentPage, int $totalPages): array {
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
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Area - PLANNET</title>
    <link rel="stylesheet" href="../assets/vendor/poppins/poppins.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main-style.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/main-style.css') ?>">
    <link rel="stylesheet" href="assets/customer-style.css?v=<?= (int) @filemtime(__DIR__ . '/assets/customer-style.css') ?>">
    <link rel="icon" type="image/ico" href="../assets/favicon.ico">
</head>
<body class="client-page customer-area-page">
<?php include __DIR__.'/includes/navbar.php'; ?>

<main class="container-fluid page-container app-shell client-page-container px-lg-4 pb-5">
    <section class="page-heading client-heading customer-heading">
        <div>
            <div class="eyebrow">Customer area</div>
            <h1 class="page-title">Selamat datang, <?= htmlspecialchars(customerAuthName()) ?></h1>
            <p class="page-description">
                Berikut ringkasan layanan dan riwayat tagihan Anda.
            </p>
        </div>
    </section>

    <?php if ($profileMessage !== ''): ?>
        <div class="alert alert-success client-alert customer-profile-alert">
            <i class="fas fa-circle-check"></i><?= htmlspecialchars($profileMessage) ?>
        </div>
    <?php endif; ?>
    <?php if ($profileError !== ''): ?>
        <div class="alert alert-danger client-alert customer-profile-alert">
            <i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($profileError) ?>
        </div>
    <?php endif; ?>

    <?php if (!$customer): ?>
        <div class="alert alert-warning client-alert">
            <i class="fas fa-triangle-exclamation"></i>
            Akun belum terhubung dengan data pelanggan. Silakan hubungi administrator.
        </div>
    <?php else: ?>
        <?php
        $missingProfileFields = [];
        if (trim((string)$customer['telepon']) === '') $missingProfileFields[] = 'nomor telepon';
        if (trim((string)$customer['alamat']) === '') $missingProfileFields[] = 'alamat';
        ?>
        <?php if ($missingProfileFields): ?>
            <div class="alert alert-warning client-alert customer-profile-alert">
                <i class="fas fa-triangle-exclamation"></i>
                <span>Profil belum lengkap: <?= htmlspecialchars(implode(' dan ', $missingProfileFields)) ?> belum diisi.</span>
                <span class="customer-alert-actions">
                    <?php if (trim((string)$customer['telepon']) === ''): ?>
                        <a href="#profil" class="alert-link" data-profile-edit-target="telepon">Isi telepon</a> dan
                    <?php endif; ?>
                    <?php if (trim((string)$customer['alamat']) === ''): ?>
                        <a href="#profil" class="alert-link" data-profile-edit-target="alamat">Isi alamat</a>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <section class="client-summary-grid" aria-label="Ringkasan akun pelanggan">
            <article class="client-summary-card client-summary-primary">
                <span class="client-summary-icon"><i class="fas fa-receipt"></i></span>
                <div>
                    <span>Tagihan terbaru</span>
                    <strong>
                        <?= $latestInvoice
                            ? customerCurrency((float)$latestInvoice['total_harga'])
                            : 'Belum tersedia' ?>
                    </strong>
                </div>
                <?php if ($latestInvoice): ?>
                    <a
                        href="actions/invoice_pdf.php?file_id=<?= (int)$latestInvoice['file_id'] ?>"
                        class="client-print-button"
                        target="_blank"
                        rel="noopener"
                        title="Buka PDF tagihan terbaru"
                    >
                        <i class="fas fa-print"></i><span>Cetak PDF</span>
                    </a>
                <?php endif; ?>
            </article>
            <article class="client-summary-card">
                <span class="client-summary-icon"><i class="fas fa-box-archive"></i></span>
                <div><span>Semua periode</span><strong><?= $invoiceTotal ?></strong></div>
            </article>
            <article class="client-summary-card">
                <span class="client-summary-icon"><i class="fas fa-wallet"></i></span>
                <div><span>Riwayat</span><strong><?= customerCurrency($totalHistory) ?></strong></div>
            </article>
        </section>

        <div class="client-layout">
            <section class="card panel client-profile-card" id="profil">
                <div class="card-body p-4">
                    <div class="client-section-title customer-profile-title">
                        <span><i class="fas fa-address-card"></i></span>
                        <div><h2>Profil</h2><p>Informasi akun dan layanan.</p></div>
                    </div>
                    <dl class="client-profile-list">
                        <div><dt>Nama</dt><dd><?= htmlspecialchars($customer['nama_pelanggan']) ?></dd></div>
                        <div><dt>Prefix</dt><dd><span class="client-code"><?= htmlspecialchars($customer['prefix']) ?></span></dd></div>
                        <div><dt>Billing</dt><dd><?= htmlspecialchars($customer['billing_name']) ?></dd></div>
                        <div class="customer-profile-row">
                            <dt>Nomor telepon</dt>
                            <dd>
                                <div class="customer-profile-display" data-profile-display="telepon">
                                    <span><?= htmlspecialchars(trim((string)$customer['telepon']) !== '' ? $customer['telepon'] : '-') ?></span>
                                    <a href="#profil" class="customer-profile-edit-link" data-profile-edit="telepon"><i class="fas fa-pen"></i>Edit</a>
                                </div>
                                <form method="post" action="actions/update_profile.php" class="customer-inline-edit d-none" data-profile-form="telepon">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">
                                    <input type="hidden" name="field" value="telepon">
                                    <input name="telepon" class="form-control" maxlength="20" value="<?= htmlspecialchars((string)$customer['telepon']) ?>" placeholder="Contoh: 081234567890 atau +6281234567890" required inputmode="tel" pattern="^(08\d{8,12}|\+62\d{8,12})$" title="Gunakan format 08... atau +62...">
                                    <div class="customer-inline-actions">
                                        <button type="button" class="btn btn-sm btn-light" data-profile-cancel="telepon">Batal</button>
                                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-floppy-disk"></i>Simpan</button>
                                    </div>
                                </form>
                            </dd>
                        </div>
                        <div class="customer-profile-row">
                            <dt>Alamat</dt>
                            <dd>
                                <div class="customer-profile-display" data-profile-display="alamat">
                                    <span><?= trim((string)$customer['alamat']) !== '' ? nl2br(htmlspecialchars($customer['alamat'])) : '-' ?></span>
                                    <a href="#profil" class="customer-profile-edit-link" data-profile-edit="alamat"><i class="fas fa-pen"></i>Edit</a>
                                </div>
                                <form method="post" action="actions/update_profile.php" class="customer-inline-edit d-none" data-profile-form="alamat">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken()) ?>">
                                    <input type="hidden" name="field" value="alamat">
                                    <textarea name="alamat" class="form-control" rows="2" maxlength="2000" placeholder="Masukkan alamat lengkap" required><?= htmlspecialchars((string)$customer['alamat']) ?></textarea>
                                    <div class="customer-inline-actions">
                                        <button type="button" class="btn btn-sm btn-light" data-profile-cancel="alamat">Batal</button>
                                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-floppy-disk"></i>Simpan</button>
                                    </div>
                                </form>
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="card panel client-history-card" id="riwayat">
                <div class="card-body p-0">
                    <div class="client-history-heading p-4 pb-3">
                        <div class="client-section-title">
                            <span><i class="fas fa-clock-rotate-left"></i></span>
                            <div><h2>Riwayat tagihan</h2><p>Rincian pemakaian berdasarkan data yang telah diproses.</p></div>
                        </div>
                    </div>

                    <?php if (!$invoices): ?>
                        <div class="empty-state client-empty">
                            <i class="fas fa-file-circle-xmark"></i>
                            <strong>Belum ada tagihan</strong>
                            <span>Riwayat akan tampil setelah data voucher diproses.</span>
                        </div>
                    <?php else: ?>
                        <div class="client-invoice-list">
                            <?php foreach ($invoices as $index => $invoice): ?>
                                <details class="client-invoice-item" <?= $index === 0 ? 'open' : '' ?>>
                                    <summary>
                                        <span class="client-invoice-date">
                                            <i class="fas fa-calendar-day"></i>
                                            <span>
                                                <strong><?= htmlspecialchars(date('d-m-Y', strtotime($invoice['tanggal'] ?: $invoice['uploaded_at']))) ?></strong>
                                                <small><?= htmlspecialchars($invoice['periode'] ?: 'Periode belum tersedia') ?></small>
                                            </span>
                                        </span>
                                        <span class="client-invoice-total">
                                            <?= customerCurrency((float)$invoice['total_harga']) ?>
                                            <i class="fas fa-chevron-down"></i>
                                        </span>
                                    </summary>
                                    <div class="client-invoice-detail">
                                        <div>
                                            <span>Total voucher</span>
                                            <strong><?= number_format((int)$invoice['total_voucher'], 0, ',', '.') ?></strong>
                                        </div>
                                        <div class="client-package-list">
                                            <span>Rincian paket</span>
                                            <ul>
                                                <?php foreach (array_filter(explode('||', (string)$invoice['package_summary'])) as $package): ?>
                                                    <li><i class="fas fa-ticket-alt" aria-hidden="true"></i><span><?= htmlspecialchars($package) ?></span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                        <nav class="date-pagination customer-invoice-pagination" aria-label="Halaman riwayat tagihan">
                            <div class="pagination-caption">
                                Halaman <?= $invoicePage ?> dari <?= $invoiceTotalPages ?>
                                <span>&middot; <?= $invoiceTotal ?> riwayat</span>
                            </div>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $invoicePage <= 1 ? 'disabled' : '' ?>">
                                    <?php if ($invoicePage > 1): ?>
                                        <a class="page-link" href="?invoice_page=<?= $invoicePage - 1 ?>#riwayat" aria-label="Sebelumnya">&lsaquo;</a>
                                    <?php else: ?>
                                        <span class="page-link">&lsaquo;</span>
                                    <?php endif; ?>
                                </li>
                                <?php foreach (customerPaginationItems($invoicePage, $invoiceTotalPages) as $pageItem): ?>
                                    <?php if ($pageItem === null): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php else: ?>
                                        <li class="page-item <?= $pageItem === $invoicePage ? 'active' : '' ?>">
                                            <a class="page-link" href="?invoice_page=<?= $pageItem ?>#riwayat" <?= $pageItem === $invoicePage ? 'aria-current="page"' : '' ?>><?= $pageItem ?></a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <li class="page-item <?= $invoicePage >= $invoiceTotalPages ? 'disabled' : '' ?>">
                                    <?php if ($invoicePage < $invoiceTotalPages): ?>
                                        <a class="page-link" href="?invoice_page=<?= $invoicePage + 1 ?>#riwayat" aria-label="Berikutnya">&rsaquo;</a>
                                    <?php else: ?>
                                        <span class="page-link">&rsaquo;</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>
</main>
<?php if ($customer): ?>
<script>
    function closeProfileEditors(resetForms = true) {
        document.querySelectorAll('[data-profile-form]').forEach(form => {
            if (resetForms) form.reset();
            form.classList.add('d-none');
        });
        document.querySelectorAll('[data-profile-display]').forEach(display => {
            display.classList.remove('d-none');
        });
    }

    function openProfileEditor(field) {
        closeProfileEditors();
        const display = document.querySelector(`[data-profile-display="${field}"]`);
        const form = document.querySelector(`[data-profile-form="${field}"]`);
        if (!display || !form) return;
        display.classList.add('d-none');
        form.classList.remove('d-none');
        form.querySelector('.form-control')?.focus();
    }

    document.querySelectorAll('[data-profile-form="telepon"]').forEach(form => {
        const phoneInput = form.querySelector('input[name="telepon"]');
        if (!phoneInput) return;

        const phonePattern = /^(08\d{8,12}|\+62\d{8,12})$/;
        const validatePhoneInput = () => {
            phoneInput.setCustomValidity('');
            const value = phoneInput.value.trim();
            if (value && !phonePattern.test(value)) {
                phoneInput.setCustomValidity('Nomor telepon harus dimulai dengan 08 atau +62.');
            }
        };

        phoneInput.addEventListener('input', validatePhoneInput);
        form.addEventListener('submit', event => {
            validatePhoneInput();
            if (!phoneInput.checkValidity()) {
                event.preventDefault();
                phoneInput.reportValidity();
            }
        });
    });

    document.querySelectorAll('[data-profile-edit], [data-profile-edit-target]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            openProfileEditor(link.dataset.profileEdit || link.dataset.profileEditTarget);
        });
    });

    document.querySelectorAll('[data-profile-cancel]').forEach(button => {
        button.addEventListener('click', () => closeProfileEditors());
    });

    <?php if (in_array($profileEditField, ['telepon', 'alamat'], true)): ?>
    openProfileEditor(<?= json_encode($profileEditField) ?>);
    <?php endif; ?>
</script>
<?php endif; ?>
<script src="../assets/js/mobile-keyboard.js"></script>
<script src="../assets/js/interaction-loading.js"></script>
</body>
</html>
