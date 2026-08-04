<?php
require_once __DIR__.'/auth.php';

ensureAuthSchema();
requireStaff();

$db = authDb();
$customerAccess = authIsAdmin() ? '1=1' : authBillingCondition('c.billing_id', $db);
$totalCustomers = (int)($db->query("SELECT COUNT(*) AS total FROM prefix_customers c
    WHERE {$customerAccess}")->fetch_assoc()['total'] ?? 0);
$db->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pelanggan - PLANETFlow</title>
    <link rel="stylesheet" href="assets/vendor/poppins/poppins.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main-style.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/main-style.css') ?>">
    <link rel="icon" type="image/ico" href="assets/favicon.ico">
</head>
<body class="customers-page">
<?php include __DIR__.'/assets/php/include/navbar.php'; ?>

<main class="container-fluid page-container app-shell customers-page-container px-lg-4 pb-5">
    <section class="page-heading customers-heading">
        <div>
            <div class="eyebrow">Direktori</div>
            <h1 class="page-title">Daftar pelanggan</h1>
            <p class="page-description">Pelanggan dikelompokkan berdasarkan billing dan disembunyikan sampai kategorinya dibuka.</p>
        </div>
        <a href="index.php?add_customer=1" class="btn btn-primary customers-add-button">
            <i class="fas fa-user-plus"></i><span>Tambah pelanggan</span>
        </a>
    </section>

    <section class="customers-toolbar" aria-label="Pencarian pelanggan">
        <label class="customers-search" for="customerSearchInput">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input id="customerSearchInput" type="search" inputmode="search" autocomplete="off"
                placeholder="Cari berdasarkan nama pelanggan" aria-label="Cari berdasarkan nama pelanggan">
            <button type="button" id="customerSearchClear" class="customers-search-clear d-none" aria-label="Hapus pencarian">
                <i class="fas fa-xmark"></i>
            </button>
        </label>
        <span class="customers-result-count" id="customerResultCount"><?= number_format($totalCustomers, 0, ',', '.') ?> pelanggan</span>
    </section>

    <div id="customerPageNotice" class="alert d-none" role="alert"></div>
    <section id="customerBillingList" class="customer-billing-list" aria-live="polite">
        <div class="customer-loading-card"><span class="spinner-border spinner-border-sm"></span><span>Memuat..</span></div>
    </section>
    <section id="customerPageEmpty" class="customer-page-empty d-none">
        <span><i class="fas fa-user-slash"></i></span>
        <h2>Data pelanggan tidak ditemukan</h2>
        <p>Coba gunakan nama lain atau tambahkan pelanggan baru.</p>
    </section>
</main>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
const billingListElement = document.getElementById('customerBillingList');
const searchInput = document.getElementById('customerSearchInput');
const searchClear = document.getElementById('customerSearchClear');
let searchTimer = null;
let billingRequest = null;

function escapeCustomerHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');
    return element.innerHTML;
}

function customerDate(value) {
    if (!value) return '-';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
        ? String(value)
        : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(parsed);
}

function showCustomerNotice(message, type = 'danger') {
    const notice = document.getElementById('customerPageNotice');
    notice.className = `alert alert-${type}`;
    notice.textContent = message;
}

function customerHeader(customer, billingId) {
    const accountAvailable = Boolean(customer.account_username);
    const accountActive = accountAvailable && Number(customer.account_is_active) === 1;
    const detailId = `customer-detail-${billingId}-${String(customer.prefix).replace(/[^a-z0-9_-]/gi, '-')}`;
    return `<article class="customer-accordion-item">
        <header class="customer-accordion-header">
            <button type="button" class="customer-accordion-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#${detailId}" aria-expanded="false">
                <span class="customer-card-avatar"><i class="fas fa-user"></i></span>
                <span class="customer-card-identity">
                    <strong>${escapeCustomerHtml(customer.nama_pelanggan)}</strong>
                    <span class="customer-prefix">${escapeCustomerHtml(customer.prefix)}</span>
                </span>
                <span class="customer-account-status ${accountActive ? 'is-active' : 'is-inactive'}">
                    ${accountAvailable ? (accountActive ? 'Aktif' : 'Nonaktif') : 'Tanpa akun'}
                </span>
                <i class="fas fa-chevron-down customer-detail-chevron"></i>
            </button>
            <div class="dropdown customer-card-menu">
                <button type="button" class="customer-menu-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Aksi ${escapeCustomerHtml(customer.nama_pelanggan)}"><i class="fas fa-ellipsis-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="index.php?edit_customer=${encodeURIComponent(customer.prefix)}"><i class="fas fa-pen"></i>Edit pelanggan</a></li>
                    <li><button type="button" class="dropdown-item text-danger customer-delete-action" data-prefix="${escapeCustomerHtml(customer.prefix)}" data-name="${escapeCustomerHtml(customer.nama_pelanggan)}" data-billing-id="${billingId}"><i class="fas fa-trash"></i>Hapus pelanggan</button></li>
                </ul>
            </div>
        </header>
        <div id="${detailId}" class="collapse customer-detail-collapse">
            <div class="customer-detail-body">
                <div class="customer-contact-grid">
                    <div><span>Telepon</span><strong>${escapeCustomerHtml(customer.telepon || '-')}</strong></div>
                    <div><span>Alamat</span><strong>${escapeCustomerHtml(customer.alamat || '-')}</strong></div>
                    <div><span>Paket aktif</span><strong>${Number(customer.package_count) || 0} paket</strong></div>
                </div>
                <section class="customer-account-detail ${accountAvailable ? '' : 'is-missing'}">
                    <div class="customer-account-title"><span><i class="fas fa-circle-user"></i>Akun customer</span></div>
                    <div class="customer-account-grid">
                        <div><span>Username</span><strong>${escapeCustomerHtml(customer.account_username || '-')}</strong></div>
                        <div><span>Password</span><strong>${escapeCustomerHtml(customer.account_password || '-')}</strong></div>
                        <div><span>Dibuat : ${escapeCustomerHtml(customerDate(customer.account_created_at))}</span></div>
                    </div>
                </section>
            </div>
        </div>
    </article>`;
}

function bindCustomerActions(container) {
    container.querySelectorAll('.customer-delete-action').forEach(button => {
        button.addEventListener('click', () => deleteCustomerFromPage(
            button.dataset.prefix || '',
            button.dataset.name || '',
            Number(button.dataset.billingId) || 0
        ));
    });
}

function billingCategory(billing) {
    const billingId = Number(billing.id) || 0;
    const collapseId = `billing-customers-${billingId}`;
    return `<article class="customer-billing-group" data-billing-id="${billingId}">
        <h2 class="customer-billing-heading">
            <button type="button" class="customer-billing-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false">
                <span class="customer-billing-icon"><i class="fas fa-building"></i></span>
                <span class="customer-billing-name"><strong>${escapeCustomerHtml(billing.name)}</strong><small>Kategori billing</small></span>
                <span class="customer-billing-count">${Number(billing.customer_count) || 0} pelanggan</span>
                <i class="fas fa-chevron-down customer-billing-chevron"></i>
            </button>
        </h2>
        <div id="${collapseId}" class="collapse customer-billing-collapse">
            <div class="customer-billing-body" data-loaded="false">
                <div class="customer-category-placeholder">Klik kategori untuk memuat pelanggan.</div>
            </div>
        </div>
    </article>`;
}

function loadBillingCustomers(group, page = 1, minimumDelay = false) {
    const body = group.querySelector('.customer-billing-body');
    const billingId = Number(group.dataset.billingId) || 0;
    body.innerHTML = '<div class="customer-category-loading"><span class="spinner-border spinner-border-sm"></span><span>Memuat pelanggan...</span></div>';
    const startedAt = Date.now();
    const parameters = new URLSearchParams({
        action: 'list_customers',
        billing_id: String(billingId),
        customer_page: String(page),
        q: searchInput.value.trim(),
    });

    fetch(`index.php?${parameters.toString()}`)
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Pelanggan gagal dimuat.');
            const remainingDelay = minimumDelay ? Math.max(0, 1000 - (Date.now() - startedAt)) : 0;
            if (remainingDelay) await new Promise(resolve => window.setTimeout(resolve, remainingDelay));
            return data;
        })
        .then(result => {
            const customerRows = result.customers.map(customer => customerHeader(customer, billingId)).join('');
            const pagination = result.total_pages > 1
                ? `<nav class="customer-category-pagination"><span>Halaman ${result.page} dari ${result.total_pages}</span><div><button type="button" class="btn btn-outline-secondary category-page-button" data-page="${result.page - 1}" ${result.page <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-primary category-page-button" data-page="${result.page + 1}" ${result.page >= result.total_pages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button></div></nav>`
                : '';
            body.innerHTML = customerRows || '<div class="customer-category-placeholder">Pelanggan tidak ditemukan.</div>';
            body.insertAdjacentHTML('beforeend', pagination);
            body.dataset.loaded = 'true';
            bindCustomerActions(body);
            body.querySelectorAll('.category-page-button').forEach(button => {
                button.addEventListener('click', () => loadBillingCustomers(group, Number(button.dataset.page), false));
            });
        })
        .catch(error => {
            body.innerHTML = `<div class="customer-category-error">${escapeCustomerHtml(error.message)}</div>`;
        });
}

function bindBillingGroups() {
    billingListElement.querySelectorAll('.customer-billing-group').forEach(group => {
        const collapse = group.querySelector('.customer-billing-collapse');
        collapse.addEventListener('show.bs.collapse', event => {
            if (event.target !== collapse) return;
            const body = group.querySelector('.customer-billing-body');
            if (body.dataset.loaded !== 'true') loadBillingCustomers(group, 1, true);
        });
    });
}

function loadBillingCategories(openSearchResults = false) {
    billingRequest?.abort();
    billingRequest = new AbortController();
    const parameters = new URLSearchParams({ action: 'list_customer_billings', q: searchInput.value.trim() });
    billingListElement.innerHTML = '<div class="customer-loading-card"><span class="spinner-border spinner-border-sm"></span><span>Memuat kategori billing...</span></div>';
    document.getElementById('customerPageEmpty').classList.add('d-none');

    fetch(`index.php?${parameters.toString()}`, { signal: billingRequest.signal })
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Kategori billing gagal dimuat.');
            return data;
        })
        .then(result => {
            billingListElement.innerHTML = result.billings.map(billingCategory).join('');
            document.getElementById('customerResultCount').textContent = `${result.total} pelanggan`;
            document.getElementById('customerPageEmpty').classList.toggle('d-none', result.billings.length > 0);
            bindBillingGroups();
            if (openSearchResults && searchInput.value.trim() !== '') {
                billingListElement.querySelectorAll('.customer-billing-collapse').forEach(collapse => {
                    bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
                });
            }
        })
        .catch(error => {
            if (error.name === 'AbortError') return;
            billingListElement.replaceChildren();
            showCustomerNotice(error.message);
        });
}

function deleteCustomerFromPage(prefix, customerName, billingId) {
    if (!prefix || !window.confirm(`Hapus pelanggan "${customerName}" (${prefix}) beserta akun dan harga paketnya?`)) return;
    const formData = new FormData();
    formData.append('action', 'delete_customer');
    formData.append('prefix', prefix);
    fetch('index.php', { method: 'POST', body: formData })
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Pelanggan gagal dihapus.');
            return data;
        })
        .then(data => {
            showCustomerNotice(data.message, 'success');
            loadBillingCategories(false);
        })
        .catch(error => showCustomerNotice(error.message));
}

searchInput.addEventListener('input', () => {
    searchClear.classList.toggle('d-none', searchInput.value === '');
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => loadBillingCategories(true), 250);
});
searchClear.addEventListener('click', () => {
    searchInput.value = '';
    searchClear.classList.add('d-none');
    loadBillingCategories(false);
    searchInput.focus();
});

loadBillingCategories();
</script>
<script src="assets/js/mobile-keyboard.js"></script>
<script src="assets/js/interaction-loading.js"></script>
</body>
</html>
