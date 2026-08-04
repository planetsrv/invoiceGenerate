<?php
session_start();
require_once __DIR__ . '/assets/php/cont/cont.php';
require_once __DIR__ . '/auth.php';

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    die('Dependensi Composer tidak ditemukan. Jalankan "composer install" terlebih dahulu.');
}
require_once $autoloadPath;
require_once __DIR__ . '/assets/php/functions/main-function.php';

ensureDatabase();
ensureAuthSchema();
requireStaff();
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(GENERATED_DIR)) mkdir(GENERATED_DIR, 0755, true);
if (!is_dir(ZIP_DIR)) mkdir(ZIP_DIR, 0755, true);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');


require_once __DIR__ . '/assets/php/functions/logic.php';

// ===================== TAMPILAN HALAMAN =====================
$db = getDB();
$paketList = getPaketList($db);
$billingList = getBillingList($db);

// Data terbaru user hanya berasal dari file yang memuat billing izinnya.
$latestUploadAccessCondition = authIsAdmin()
    ? '1=1'
    : 'EXISTS (SELECT 1 FROM rekap access_rekap
        INNER JOIN prefix_customers access_customer ON access_customer.prefix = access_rekap.prefix
        WHERE access_rekap.file_id = f.id AND '.authBillingCondition('access_customer.billing_id', $db).')';

$latestFileId = null;
$latestUploadMetadata = ['periode' => '', 'tanggal' => ''];
$requestedUploadId = max(0, (int)($_GET['upload_id'] ?? 0));
if ($requestedUploadId > 0) {
    $stmt = $db->prepare("SELECT f.id, f.periode, f.tanggal FROM uploaded_files f
        WHERE f.id = ? AND {$latestUploadAccessCondition} LIMIT 1");
    $stmt->bind_param('i', $requestedUploadId);
    $stmt->execute();
    $selectedUpload = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $res = $db->query("SELECT f.id, f.periode, f.tanggal FROM uploaded_files f
        WHERE {$latestUploadAccessCondition} ORDER BY f.id DESC LIMIT 1");
    $selectedUpload = $res ? $res->fetch_assoc() : null;
}
if ($selectedUpload) {
    $row = $selectedUpload;
    $latestFileId = $row['id'];
    $latestUploadMetadata = [
        'periode' => (string)($row['periode'] ?? ''),
        'tanggal' => (string)($row['tanggal'] ?? ''),
    ];
}

$rekapData = []; $allPaket = $paketList; $customerNames = []; $prefixSubset = [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPages = 1;
$totalPrefix = 0;
$mainBillingCondition = authIsAdmin() ? '1=1' : authBillingCondition('c.billing_id', $db);

if ($latestFileId) {
    $totalPrefix = $db->query("SELECT COUNT(DISTINCT r.prefix) AS cnt
        FROM rekap r
        LEFT JOIN prefix_customers c ON c.prefix = r.prefix
        WHERE r.file_id = {$latestFileId} AND {$mainBillingCondition}")->fetch_assoc()['cnt'];
    $totalPages = max(1, (int)ceil($totalPrefix / PER_PAGE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * PER_PAGE;

    $res = $db->query("SELECT daftar.prefix,
            COALESCE(NULLIF(TRIM(c.nama_pelanggan), ''), 'N/A') AS nama
        FROM (SELECT DISTINCT prefix FROM rekap WHERE file_id = {$latestFileId}) daftar
        LEFT JOIN prefix_customers c ON c.prefix = daftar.prefix
        WHERE {$mainBillingCondition}
        ORDER BY nama ASC, daftar.prefix ASC
        LIMIT {$offset}, ".PER_PAGE);
    while ($r = $res->fetch_assoc()) {
        $prefixSubset[] = $r['prefix'];
        $customerNames[$r['prefix']] = $r['nama'];
    }

    if (!empty($prefixSubset)) {
        $in = "'" . implode("','", array_map([$db, 'real_escape_string'], $prefixSubset)) . "'";
        $res = $db->query("SELECT prefix, paket, jumlah FROM rekap WHERE file_id = {$latestFileId} AND prefix IN ({$in}) ORDER BY prefix, paket");
        while ($r = $res->fetch_assoc()) {
            $rekapData[$r['prefix']][$r['paket']] = (int)$r['jumlah'];
        }
    }
}

$totalPelanggan = 0;
$res = $db->query("SELECT COUNT(*) AS total FROM prefix_customers c WHERE ".(authIsAdmin() ? '1=1' : authBillingCondition('c.billing_id', $db)));
if ($res && $row = $res->fetch_assoc()) $totalPelanggan = (int)$row['total'];
$databaseBytes = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(data_length + index_length), 0) AS total_bytes
    FROM information_schema.tables WHERE table_schema = ?");
$databaseName = DB_NAME;
$stmt->bind_param('s', $databaseName);
$stmt->execute();
$databaseSizeRow = $stmt->get_result()->fetch_assoc();
if ($databaseSizeRow) $databaseBytes = (int)$databaseSizeRow['total_bytes'];
$stmt->close();
$databaseSize = $databaseBytes < 1048576 ? '0' : formatBytes($databaseBytes);
$db->close();
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Import & Invoice</title>
    <link rel="stylesheet" href="assets/vendor/poppins/poppins.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main-style.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/main-style.css') ?>">
    <link rel="icon" type="image/ico" href="assets/favicon.ico">
</head>
<body>
<?php include 'assets/php/include/navbar.php'; ?>
<div class="container-fluid page-container app-shell px-lg-4 pb-5">
    <div id="notificationArea"></div>

    <section class="page-heading">
        <div>
            <div class="eyebrow">Workspace Overview</div>
            <h1 class="page-title">Generator invoice</h1>
            <p class="page-description">Mengelola data voucher berdasatkan prefik reseller, dengan harga yang di distribusikan ke reseler</p>
        </div>
        <div class="stat-strip">
            <button type="button" class="mini-stat mini-stat-action" data-bs-toggle="modal" data-bs-target="#customerModal" title="Tambah pelanggan baru">
                <span>Client</span><strong><i class="fas fa-user-plus me-1"></i>Add</strong>
            </button>

           <a href="pelanggan.php" class="mini-stat mini-stat-action" title="Buka halaman pelanggan">
                <span>Pelanggan</span><strong><i class="fas fa-user me-1"></i><?= $totalPelanggan ?></strong>
            </a>
            <div class="mini-stat" title="Total data dan indeks seluruh tabel database">
                <span>Disk Database</span><strong><i class="fas fa-database me-1"></i><?= htmlspecialchars($databaseSize) ?></strong>
            </div>

            <button type="button" class="mini-stat mini-stat-action" data-bs-toggle="modal" data-bs-target="#packageModal" title="Kelola paket">
                <span>Paket</span><strong><i class="fas fa-ticket me-1"></i><span id="packageTotal" class="d-inline"><?= count($paketList) ?></span></strong>
            </button>
        </div>
    </section>

    <!-- Upload Form -->
    <div class="card premium-card mb-4">
        <div class="card-body">
            <div class="section-heading">
                <span class="section-icon"><i class="fas fa-cloud-arrow-up"></i></span>
                <div>
                    <h2>Import Data Voucher</h2>
                    <p>Unggah file Excel untuk memperbarui rekap dan invoice secara otomatis.</p>
                </div>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="uploadPeriode" class="form-label">Periode</label>
                        <input type="text" name="periode" id="uploadPeriode" class="form-control" maxlength="20" required placeholder="01 s/d 15 Agustus">
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="uploadTanggal" class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" id="uploadTanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">File voucher</label>
                        <div class="auto-upload-control">
                            <input class="visually-hidden" type="file" name="excelfile" id="ef" accept=".xlsx,.xls,.ods,.csv" required>
                            <label class="btn btn-primary auto-upload-button" for="ef" id="uploadPickerButton">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <span id="uploadPickerLabel">Pilih File &amp;</span>
                            </label>
                            <span class="visually-hidden" id="selectedFileName">Tidak ada file yang dipilih</span>
                        </div>
                    </div>
                </div>
                <button type="submit" class="visually-hidden" id="uploadBtn" tabindex="-1">Proses...</button>
                <div class="progress mt-3 d-none" id="progressWrap">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressFill" role="progressbar" aria-valuemin="0" aria-valuemax="100" ariavaluenow="0" style="width:0%">0%</div>
                </div>
                <div class="text-muted mt-1 d-none" id="progressStatus">Menyiapkan upload...</div>
            </form>
            <div id="uploadNotif" class="mt-2"></div>
        </div>
    </div>

    <?php if (!empty($rekapData)): ?>

    <section class="report-card latest-data-card mb-4">
    <div class="latest-data-heading">
        <div class="d-flex align-items-center latest-data-title">
            <i class="fas fa-table-list" aria-hidden="true"></i>
            <h4>Data terbaru</h4>
        </div>
    </div>
    <form method="get" id="metadataDownloadForm" class="latest-data-actions row g-3 align-items-end">
                <input type="hidden" name="action" id="metadataAction" value="export_rekap">
                <input type="hidden" name="view" id="metadataView" value="1" disabled>
                <input type="hidden" name="file_id" value="<?= $latestFileId ?>">
                <input type="hidden" name="periode" value="<?= htmlspecialchars($latestUploadMetadata['periode']) ?>">
                <input type="hidden" name="tanggal" value="<?= htmlspecialchars($latestUploadMetadata['tanggal']) ?>">


                <div class="col-12 report-action-toolbar">
                    <div class="report-billing-field">
                        <label for="reportBillingButton" class="form-label fw-semibold">Billing</label>
                        <input type="hidden" name="billing_id" id="reportBilling" value="">
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle report-billing-toggle"
                                id="reportBillingButton" data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                data-bs-offset="0,6" aria-expanded="false">
                                <i class="fas fa-building me-1"></i>
                                <span id="reportBillingLabel">Semua Billing</span>
                            </button>
                            <ul class="dropdown-menu report-billing-menu" aria-labelledby="reportBillingButton">
                                <li>
                                    <button type="button" class="dropdown-item report-billing-option active" data-billing-id="">
                                        Semua Billing
                                    </button>
                                </li>
                                <?php foreach ($billingList as $billing): ?>
                                    <li>
                                        <button type="button" class="dropdown-item report-billing-option" data-billing-id="<?= (int)$billing['id'] ?>">
                                            <?= htmlspecialchars($billing['nama']) ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="downloadBundleBtn">
                        <i class="fas fa-file-zipper me-1"></i>ZIP
                    </button>
                    <button type="submit" class="btn btn-outline-info" id="viewPdfBtn" formtarget="_blank">
                        <i class="fas fa-eye me-1"></i>PDF
                    </button>
                    <button type="button" class="btn btn-outline-success" id="viewExcelBtn">
                        <i class="fas fa-table me-1"></i>Excel
                    </button>
                </div>
            </form>

    <div class="tab-content data-panel">
        <div class="tab-pane fade show active" id="rekap">
            <div class="tab-search">
                <i class="fas fa-magnifying-glass tab-search-icon"></i>
                <input type="search" class="form-control" id="rekapSearch" placeholder="Cari nama pelanggan..." autocomplete="off" aria-label="Cari nama pelanggan pada rekap">
                <button type="button" class="tab-search-clear d-none" aria-label="Hapus pencarian"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Nama</th><th>Prefix</th><?php foreach ($allPaket as $p): ?><th><?= htmlspecialchars($p) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($prefixSubset as $prefix): $pakets = $rekapData[$prefix] ?? []; ?>
                        <tr data-search-name="<?= htmlspecialchars(mb_strtolower($customerNames[$prefix] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= htmlspecialchars($customerNames[$prefix] ?? 'N/A') ?></td>
                            <td class="prefix"><?= htmlspecialchars($prefix) ?></td>
                            <?php foreach ($allPaket as $p): $cnt = $pakets[$p] ?? 0; ?>
                                <td><?= $cnt ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-search-empty d-none" id="rekapSearchEmpty"><i class="fas fa-magnifying-glass"></i> Nama pelanggan tidak ditemukan.</div>
            <?php if ($totalPrefix > 0): ?>
            <nav class="date-pagination" id="rekapPagination" aria-label="Pagination rekap">
                <div class="pagination-caption">Halaman <?= $page ?> dari <?= $totalPages ?></div>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <?php if ($page > 1): ?><a class="page-link" href="?page=<?= $page - 1 ?>#rekap" aria-label="Sebelumnya">&lsaquo;</a><?php else: ?><span class="page-link">&lsaquo;</span><?php endif; ?>
                    </li>
                    <?php foreach (paginationPageItems($page, $totalPages) as $paginationPage): ?>
                        <?php if ($paginationPage === null): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php else: ?>
                            <li class="page-item <?= $paginationPage === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $paginationPage ?>#rekap" <?= $paginationPage === $page ? 'aria-current="page"' : '' ?>><?= $paginationPage ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <?php if ($page < $totalPages): ?><a class="page-link" href="?page=<?= $page + 1 ?>#rekap" aria-label="Berikutnya">&rsaquo;</a><?php else: ?><span class="page-link">&rsaquo;</span><?php endif; ?>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php if (false): // Tampilan arsip dan dokumen telah dipindahkan ke files.php. ?>
        <div class="tab-pane fade" id="arsip">
            <?php if (empty($pagedFilesByDate)): ?>
                <div class="text-center text-muted py-5">Belum ada rekap upload.</div>
            <?php else: ?>
                <div class="tab-search">
                    <i class="fas fa-calendar-days tab-search-icon"></i>
                    <input type="search" class="form-control" id="archiveSearch" placeholder="Cari tanggal arsip..." autocomplete="off" aria-label="Cari arsip berdasarkan tanggal">
                    <button type="button" class="tab-search-clear d-none" aria-label="Hapus pencarian"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="accordion date-accordion" id="archiveDateAccordion">
                <?php $archiveIndex = 0; foreach ($pagedFilesByDate as $dateKey => $dateFiles):
                    $archiveCollapseId = 'archive-date-'.$archiveIndex;
                    $archiveOpen = $archiveIndex === 0;
                ?>
                    <div class="accordion-item" data-search-date="<?= htmlspecialchars(mb_strtolower(formatDateGroup($dateKey).' '.$dateKey), ENT_QUOTES, 'UTF-8') ?>" data-initial-open="<?= $archiveOpen ? '1' : '0' ?>">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $archiveOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $archiveCollapseId ?>" aria-expanded="<?= $archiveOpen ? 'true' : 'false' ?>" aria-controls="<?= $archiveCollapseId ?>">
                                <span class="date-title"><i class="fas fa-calendar-day me-2"></i><?= htmlspecialchars(formatDateGroup($dateKey)) ?></span>
                                <span class="badge rounded-pill text-bg-primary ms-2"><?= count($dateFiles) ?> rekap</span>
                            </button>
                        </h2>
                        <div id="<?= $archiveCollapseId ?>" class="accordion-collapse collapse <?= $archiveOpen ? 'show' : '' ?>" data-bs-parent="#archiveDateAccordion">
                            <div class="accordion-body">
                                <div class="file-card-list">
                                <?php foreach ($dateFiles as $file): ?>
                                    <article class="file-entry">
                                        <div class="file-entry-main">
                                            <span class="file-entry-icon archive-icon"><i class="fas fa-file-arrow-up"></i></span>
                                            <div class="file-entry-info">
                                                <h3><?= htmlspecialchars($file['saved_name']) ?></h3>
                                                <div class="file-entry-meta">
                                                    <span><i class="fas fa-list-ol"></i><?= (int)$file['total_rows'] ?> baris</span>
                                                    <span><i class="fas fa-calendar"></i><?= htmlspecialchars($file['periode'] ?: 'Tanpa periode') ?></span>
                                                    <span><i class="fas fa-user"></i><?= htmlspecialchars($file['uploaded_by']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="file-entry-actions">
                                            <a href="?file_id=<?= (int)$file['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> Rekapan</a>
                                            <a href="?action=export_rekap&file_id=<?= (int)$file['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Excel</a>
                                            <a href="?action=download_all_invoices&file_id=<?= (int)$file['id'] ?>&view=1" class="btn btn-sm btn-outline-info" target="_blank"><i class="fas fa-eye"></i> Lihat PDF</a>
                                            <a href="?action=download_all_invoices&file_id=<?= (int)$file['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="fas fa-download"></i> PDF</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php $archiveIndex++; endforeach; ?>
                </div>
                <div class="tab-search-empty d-none" id="archiveSearchEmpty"><i class="fas fa-calendar-xmark"></i> Arsip pada tanggal tersebut tidak ditemukan.</div>
                <nav class="date-pagination" id="archivePagination" aria-label="Pagination rekap upload">
                    <div class="pagination-caption">Halaman <?= $archivePage ?> dari <?= $archiveTotalPages ?></div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $archivePage <= 1 ? 'disabled' : '' ?>">
                            <?php if ($archivePage > 1): ?><a class="page-link" href="?archive_page=<?= $archivePage - 1 ?>&amp;document_page=<?= $documentPage ?>#arsip" aria-label="Sebelumnya">&lsaquo;</a><?php else: ?><span class="page-link">&lsaquo;</span><?php endif; ?>
                        </li>
                        <?php foreach (paginationPageItems($archivePage, $archiveTotalPages) as $paginationPage): ?>
                            <?php if ($paginationPage === null): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php else: ?>
                                <li class="page-item <?= $paginationPage === $archivePage ? 'active' : '' ?>"><a class="page-link" href="?archive_page=<?= $paginationPage ?>&amp;document_page=<?= $documentPage ?>#arsip" <?= $paginationPage === $archivePage ? 'aria-current="page"' : '' ?>><?= $paginationPage ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <li class="page-item <?= $archivePage >= $archiveTotalPages ? 'disabled' : '' ?>">
                            <?php if ($archivePage < $archiveTotalPages): ?><a class="page-link" href="?archive_page=<?= $archivePage + 1 ?>&amp;document_page=<?= $documentPage ?>#arsip" aria-label="Berikutnya">&rsaquo;</a><?php else: ?><span class="page-link">&rsaquo;</span><?php endif; ?>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="dokumen">
            <?php if (empty($pagedDocumentsByDate)): ?>
                <div class="text-center text-muted py-5">Belum ada dokumen tersimpan.</div>
            <?php else: ?>
                <div class="tab-search">
                    <i class="fas fa-calendar-days tab-search-icon"></i>
                    <input type="search" class="form-control" id="documentSearch" placeholder="Cari tanggal dokumen..." autocomplete="off" aria-label="Cari dokumen berdasarkan tanggal">
                    <button type="button" class="tab-search-clear d-none" aria-label="Hapus pencarian"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="accordion date-accordion" id="documentDateAccordion">
                <?php $documentIndex = 0; foreach ($pagedDocumentsByDate as $dateKey => $dateDocuments):
                    $documentCollapseId = 'document-date-'.$documentIndex;
                    $documentOpen = $documentIndex === 0;
                ?>
                    <div class="accordion-item" data-search-date="<?= htmlspecialchars(mb_strtolower(formatDateGroup($dateKey).' '.$dateKey), ENT_QUOTES, 'UTF-8') ?>" data-initial-open="<?= $documentOpen ? '1' : '0' ?>">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $documentOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $documentCollapseId ?>" aria-expanded="<?= $documentOpen ? 'true' : 'false' ?>" aria-controls="<?= $documentCollapseId ?>">
                                <span class="date-title"><i class="fas fa-calendar-day me-2"></i><?= htmlspecialchars(formatDateGroup($dateKey)) ?></span>
                            </button>
                        </h2>
                        <div id="<?= $documentCollapseId ?>" class="accordion-collapse collapse <?= $documentOpen ? 'show' : '' ?>" data-bs-parent="#documentDateAccordion">
                            <div class="accordion-body">
                                <div class="file-card-list">
                                <?php foreach ($dateDocuments as $doc): $isPdf = $doc['document_type'] === 'PDF'; ?>
                                    <article class="file-entry">
                                        <div class="file-entry-main">
                                            <span class="file-entry-icon <?= $isPdf ? 'document-pdf-icon' : 'document-excel-icon' ?>"><i class="fas <?= $isPdf ? 'fa-file-pdf' : 'fa-file-excel' ?>"></i></span>
                                            <div class="file-entry-info">
                                                <h3><?= htmlspecialchars($doc['original_name']) ?></h3>
                                                <div class="file-entry-meta">
                                                    <span><i class="fas fa-building"></i><?= htmlspecialchars($doc['billing'] ?: 'Tanpa billing') ?></span>
                                                    <span><i class="fas fa-calendar"></i><?= htmlspecialchars($doc['periode'] ?: 'Tanpa periode') ?></span>
                                                    <span><i class="fas fa-calendar-check"></i><?= htmlspecialchars($doc['tanggal'] ?: 'Tanpa tanggal') ?></span>
                                                    <span><i class="fas fa-database"></i><?= number_format($doc['file_size'] / 1024, 1, ',', '.') ?> KB</span>
                                                    <span><i class="fas fa-user"></i><?= htmlspecialchars($doc['generated_by']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="file-entry-actions">
                                            <?php if ($isPdf): ?>
                                                <a href="?action=document_file&id=<?= (int)$doc['id'] ?>&view=1" class="btn btn-sm btn-outline-info" target="_blank"><i class="fas fa-eye"></i> Lihat</a>
                                            <?php else: ?>
                                                <a href="?action=view_excel&id=<?= (int)$doc['id'] ?>" class="btn btn-sm btn-outline-info" target="_blank"><i class="fas fa-eye"></i> Lihat</a>
                                            <?php endif; ?>
                                            <a href="?action=document_file&id=<?= (int)$doc['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-download"></i> Download</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php $documentIndex++; endforeach; ?>
                </div>
                <div class="tab-search-empty d-none" id="documentSearchEmpty"><i class="fas fa-calendar-xmark"></i> Dokumen pada tanggal tersebut tidak ditemukan.</div>
                <nav class="date-pagination" id="documentPagination" aria-label="Pagination dokumen">
                    <div class="pagination-caption">Halaman <?= $documentPage ?> dari <?= $documentTotalPages ?></div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $documentPage <= 1 ? 'disabled' : '' ?>">
                            <?php if ($documentPage > 1): ?><a class="page-link" href="?archive_page=<?= $archivePage ?>&amp;document_page=<?= $documentPage - 1 ?>#dokumen" aria-label="Sebelumnya">&lsaquo;</a><?php else: ?><span class="page-link">&lsaquo;</span><?php endif; ?>
                        </li>
                        <?php foreach (paginationPageItems($documentPage, $documentTotalPages) as $paginationPage): ?>
                            <?php if ($paginationPage === null): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php else: ?>
                                <li class="page-item <?= $paginationPage === $documentPage ? 'active' : '' ?>"><a class="page-link" href="?archive_page=<?= $archivePage ?>&amp;document_page=<?= $paginationPage ?>#dokumen" <?= $paginationPage === $documentPage ? 'aria-current="page"' : '' ?>><?= $paginationPage ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <li class="page-item <?= $documentPage >= $documentTotalPages ? 'disabled' : '' ?>">
                            <?php if ($documentPage < $documentTotalPages): ?><a class="page-link" href="?archive_page=<?= $archivePage ?>&amp;document_page=<?= $documentPage + 1 ?>#dokumen" aria-label="Berikutnya">&rsaquo;</a><?php else: ?><span class="page-link">&rsaquo;</span><?php endif; ?>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
        <?php endif; // Arsip dan dokumen tersedia di files.php. ?>
    </div>
    </section>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
            <h2 class="h5 fw-bold">Belum ada data voucher</h2>
            <p class="mb-0">Unggah file Excel di atas untuk mulai membuat rekap dan invoice.</p>
        </div>
    <?php endif; ?>

</div>

<!-- Modal Tambah/Edit Pelanggan -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable customer-form-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="section-heading mb-0">
                    <span class="section-icon"><i class="fas fa-user-pen"></i></span>
                    <div><h2 id="modalTitle">Tambah Pelanggan</h2><p>Lengkapi profil, billing, dan harga paket pelanggan.</p></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <input type="hidden" name="action" value="tambah_pelanggan">
                    <div class="row g-3 mb-3 customer-profile-fields">
                        <div class="col-md-4">
                            <label class="form-label">Prefix *</label>
                            <input type="text" name="prefix" class="form-control" maxlength="10" required id="custPrefix">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama *</label>
                            <input type="text" name="nama_pelanggan" class="form-control" required id="custNama">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2" id="custAlamat"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Telepon</label>
                            <input type="text" name="telepon" class="form-control" id="custTelepon">
                        </div>
                    </div>
                    <div class="mb-3 p-3 rounded-4 bg-light border customer-billing-section">
                        <label class="form-label d-block">Billing</label>
                        <div class="btn-group mb-3" role="group" aria-label="Pilihan billing">
                            <input type="radio" class="btn-check" name="billing_mode" id="billingModeExisting" value="existing" checked>
                            <label class="btn btn-outline-primary" for="billingModeExisting">
                                <i class="fas fa-list me-1"></i> Pilih Billing
                            </label>
                            <input type="radio" class="btn-check" name="billing_mode" id="billingModeNew" value="new" <?= authIsAdmin() ? '' : 'disabled' ?>>
                            <label class="btn btn-outline-primary <?= authIsAdmin() ? '' : 'd-none' ?>" for="billingModeNew">
                                <i class="fas fa-plus me-1"></i> Tambah Billing
                            </label>
                        </div>
                        <div id="existingBillingWrap">
                            <select name="billing_id" class="form-select" id="custBilling">
                                <?php if (authIsAdmin()): ?><option value="">-- Tanpa billing --</option><?php endif; ?>
                                <?php foreach ($billingList as $billing): ?>
                                    <option value="<?= (int)$billing['id'] ?>"><?= htmlspecialchars($billing['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="newBillingWrap" class="d-none">
                            <input type="text" name="billing_baru" class="form-control" id="custBillingNew" maxlength="100" placeholder="Masukkan nama billing baru">
                            <div class="form-text">Billing baru otomatis disimpan dan langsung dipilih untuk pelanggan ini.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga Paket (isi hanya untuk paket yang berbayar)</label>
                        <div class="input-group mb-3 customer-package-adder <?= authIsAdmin() ? '' : 'd-none' ?>">
                            <input type="text" class="form-control" id="newPaketName" maxlength="100" placeholder="Nama paket baru, contoh: VC 12 Jam">
                            <button type="button" class="btn btn-outline-primary" id="addPaketBtn">
                                <i class="fas fa-plus"></i> Tambah Paket Baru
                            </button>
                        </div>
                        <div class="row g-2" id="paketHargaContainer">
                            <?php foreach ($paketList as $pkt): ?>
                                <div class="col-sm-6 col-md-4 paket-harga-item">
                                    <div class="border rounded p-2 paket-harga-box">
                                        <label class="paket-harga-label"><?= htmlspecialchars($pkt) ?></label>
                                        <input type="number" name="harga[<?= htmlspecialchars($pkt) ?>]" class="form-control harga-input" min="0" step="100" value="0" data-paket="<?= htmlspecialchars($pkt) ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <?php if (authIsAdmin()): ?>
                <button type="button" class="btn btn-danger d-none" id="deleteCustomerBtn"><i class="fas fa-trash"></i> Hapus</button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="saveCustomerBtn"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pengelolaan paket master. -->
<div class="modal fade" id="packageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable package-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="section-heading mb-0">
                    <span class="section-icon"><i class="fas fa-ticket"></i></span>
                    <div><h2><?= authIsAdmin() ? 'Kelola Paket' : 'Daftar Paket' ?></h2><p><?= authIsAdmin() ? 'Tambahkan, ubah, atau hapus paket yang belum digunakan.' : 'Paket tersedia ditampilkan dalam mode hanya-baca.' ?></p></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <?php if (authIsAdmin()): ?>
                <div class="input-group package-create-form">
                    <input type="text" class="form-control" id="packageModalName" maxlength="100" placeholder="Nama paket baru">
                    <button type="button" class="btn btn-primary" id="packageModalAdd"><i class="fas fa-plus"></i> Tambah</button>
                </div>
                <?php endif; ?>
                <div class="package-management-list" id="packageManagementList"></div>
                <div class="empty-state d-none" id="packageManagementEmpty"><i class="fas fa-ticket-simple"></i> Belum ada paket.</div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="appToast" class="toast border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i id="toastIcon" class="fas fa-circle-info text-primary me-2"></i>
            <strong class="me-auto" id="toastTitle">Informasi</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Tutup"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(message, type = 'info', title = '') {
    const toastEl = document.getElementById('appToast');
    const icon = document.getElementById('toastIcon');
    const styles = {
        success: { title: 'Berhasil', icon: 'fa-circle-check', color: 'text-success' },
        danger: { title: 'Terjadi Kesalahan', icon: 'fa-circle-exclamation', color: 'text-danger' },
        warning: { title: 'Perhatian', icon: 'fa-triangle-exclamation', color: 'text-warning' },
        info: { title: 'Informasi', icon: 'fa-circle-info', color: 'text-primary' }
    };
    const style = styles[type] || styles.info;
    document.getElementById('toastTitle').textContent = title || style.title;
    document.getElementById('toastMessage').textContent = message;
    icon.className = `fas ${style.icon} ${style.color} me-2`;
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4500 }).show();
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

function toggleBillingMode() {
    const isNew = document.getElementById('billingModeNew').checked;
    document.getElementById('existingBillingWrap').classList.toggle('d-none', isNew);
    document.getElementById('newBillingWrap').classList.toggle('d-none', !isNew);
    document.getElementById('custBillingNew').required = isNew;
}                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 

document.querySelectorAll('input[name="billing_mode"]').forEach(input => {
    input.addEventListener('change', toggleBillingMode);
});

document.getElementById('metadataDownloadForm')?.addEventListener('submit', event => {
    const actionInput = document.getElementById('metadataAction');
    const viewInput = document.getElementById('metadataView');
    const buttonId = event.submitter?.id || 'downloadExcelBtn';

    if (buttonId === 'viewPdfBtn') {
        actionInput.value = 'download_all_invoices';
    } else {
        actionInput.value = 'export_rekap';
    }
    viewInput.disabled = buttonId !== 'viewPdfBtn';
});

document.querySelectorAll('.report-billing-option').forEach(option => {
    option.addEventListener('click', function() {
        document.getElementById('reportBilling').value = this.dataset.billingId || '';
        document.getElementById('reportBillingLabel').textContent = this.textContent.trim();
        document.querySelectorAll('.report-billing-option').forEach(item => item.classList.remove('active'));
        this.classList.add('active');
    });
});

function getDownloadFilename(response, fallback) {
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename="?([^";]+)"?/i);
    return match ? match[1] : fallback;
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
}

document.getElementById('downloadBundleBtn')?.addEventListener('click', async function() {
    const button = this;
    const form = document.getElementById('metadataDownloadForm');
    const baseParams = new URLSearchParams(new FormData(form));
    baseParams.delete('action');
    baseParams.delete('view');

    const excelParams = new URLSearchParams(baseParams);
    excelParams.set('action', 'export_rekap');
    excelParams.set('save_document', '1');
    excelParams.set('save_only', '1');
    const pdfParams = new URLSearchParams(baseParams);
    pdfParams.set('action', 'download_all_invoices');
    pdfParams.set('save_document', '1');
    pdfParams.set('save_only', '1');

    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyiapkan...';
    showToast('Excel dan PDF sedang dibuat serta disimpan ke File Explorer.', 'info');

    try {
        const [excelResponse, pdfResponse] = await Promise.all([
            fetch(`index.php?${excelParams.toString()}`),
            fetch(`index.php?${pdfParams.toString()}`)
        ]);
        if (!excelResponse.ok || !pdfResponse.ok) {
            throw new Error('Salah satu dokumen gagal dibuat.');
        }

        const [excelData, pdfData] = await Promise.all([excelResponse.json(), pdfResponse.json()]);
        if (!excelData.success || !pdfData.success) throw new Error('Dokumen gagal disimpan.');

        const bundleParams = new URLSearchParams({
            action: 'download_bundle',
            excel_id: excelData.document.id,
            pdf_id: pdfData.document.id
        });
        const bundleResponse = await fetch(`index.php?${bundleParams.toString()}`);
        if (!bundleResponse.ok) throw new Error('File ZIP gagal dibuat.');
        const zipBlob = await bundleResponse.blob();
        downloadBlob(zipBlob, getDownloadFilename(bundleResponse, 'Dokumen_Excel_PDF.zip'));

        showToast('ZIP berisi Excel dan PDF berhasil diunduh dan disimpan di halaman Files.', 'success');
        window.setTimeout(() => location.reload(), 1800);
    } catch (error) {
        showToast(error.message || 'Gagal membuat dokumen.', 'danger');
        button.disabled = false;
        button.innerHTML = originalContent;
    }
});

document.getElementById('viewExcelBtn')?.addEventListener('click', async function() {
    const button = this;
    const form = document.getElementById('metadataDownloadForm');
    const params = new URLSearchParams(new FormData(form));
    params.set('action', 'export_rekap');
    params.set('save_document', '1');
    params.set('save_only', '1');
    params.delete('view');

    // Buka tab terlebih dahulu agar tidak diblokir browser sebagai pop-up asinkron.
    const previewWindow = window.open('about:blank', '_blank');
    if (!previewWindow) {
        showToast('Izinkan pop-up browser untuk melihat Excel.', 'warning');
        return;
    }

    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyiapkan...';

    try {
        const response = await fetch(`index.php?${params.toString()}`);
        if (!response.ok) throw new Error('File Excel gagal dibuat.');

        const data = await response.json();
        const documentId = Number(data?.document?.id || 0);
        if (!data.success || documentId < 1) throw new Error(data.message || 'File Excel gagal disimpan.');

        previewWindow.location.href = `index.php?action=view_excel&id=${encodeURIComponent(documentId)}`;
        showToast('Excel untuk data unggahan ini berhasil dibuka.', 'success');
    } catch (error) {
        previewWindow.close();
        showToast(error.message || 'Gagal membuka Excel.', 'danger');
    } finally {
        button.disabled = false;
        button.innerHTML = originalContent;
    }
});

function setUploadProgress(value, status) {
    const progressFill = document.getElementById('progressFill');
    const pct = Math.max(0, Math.min(100, Math.round(value)));
    progressFill.style.width = `${pct}%`;
    progressFill.textContent = `${pct}%`;
    progressFill.setAttribute('aria-valuenow', pct);
    document.getElementById('progressStatus').textContent = status;
}

const uploadForm = document.getElementById('uploadForm');
const uploadFileInput = document.getElementById('ef');
const uploadPickerButton = document.getElementById('uploadPickerButton');
const uploadPickerLabel = document.getElementById('uploadPickerLabel');

function setUploadBusy(isBusy) {
    document.getElementById('uploadBtn').disabled = isBusy;
    uploadFileInput.disabled = isBusy;
    uploadPickerButton.classList.toggle('disabled', isBusy);
    uploadPickerButton.setAttribute('aria-disabled', isBusy ? 'true' : 'false');
    uploadPickerLabel.textContent = isBusy ? 'Mengupload...' : 'Pilih File & Upload';
}

uploadPickerButton.addEventListener('click', event => {
    const metadataFields = [
        document.getElementById('uploadPeriode'),
        document.getElementById('uploadTanggal')
    ];
    const invalidField = metadataFields.find(field => !field.checkValidity());
    if (invalidField) {
        event.preventDefault();
        invalidField.reportValidity();
        invalidField.focus();
        showToast('Isi periode dan tanggal sebelum memilih file.', 'warning');
    }
});

uploadFileInput.addEventListener('change', function() {
    document.getElementById('selectedFileName').textContent = this.files.length
        ? this.files[0].name
        : 'Tidak ada file yang dipilih';
    if (this.files.length) uploadForm.requestSubmit(document.getElementById('uploadBtn'));
});

uploadForm.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!this.reportValidity()) return;
    const fileInput = document.getElementById('ef');
    if (!fileInput.files.length) { showToast('Silakan pilih file terlebih dahulu.', 'warning'); return; }
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    const progressWrap = document.getElementById('progressWrap');
    const progressStatus = document.getElementById('progressStatus');
    let processingTimer = null;
    let processingProgress = 70;
    progressWrap.classList.remove('d-none');
    progressStatus.classList.remove('d-none');
    setUploadBusy(true);
    setUploadProgress(0, 'Menyiapkan upload...');
    xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
            const uploadPercent = Math.round(e.loaded / e.total * 100);
            setUploadProgress(uploadPercent * .7, `Mengunggah file: ${uploadPercent}%`);
        }
    });
    xhr.upload.addEventListener('load', () => {
        setUploadProgress(70, 'Upload selesai. Memproses data dan menyimpan ke database...');
        processingTimer = window.setInterval(() => {
            if (processingProgress < 94) {
                processingProgress += 1;
                setUploadProgress(processingProgress, 'Memproses data dan menyimpan ke database...');
            }
        }, 350);
    });
    xhr.addEventListener('load', () => {
        window.clearInterval(processingTimer);
        setUploadBusy(false);
        try {
            const resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                setUploadProgress(100, 'Selesai. Data berhasil diproses.');
                showToast(resp.message, 'success');
                const uploadId = Number(resp.file_id || 0);
                const target = uploadId > 0
                    ? `index.php?upload_id=${encodeURIComponent(uploadId)}#quickActions`
                    : 'index.php#quickActions';
                setTimeout(() => { window.location.href = target; }, 700);
            } else {
                progressWrap.classList.add('d-none');
                progressStatus.classList.add('d-none');
                showToast(resp.message, 'danger');
            }
        } catch(e) {
            progressWrap.classList.add('d-none');
            progressStatus.classList.add('d-none');
            showToast('Respons server tidak valid.', 'danger');
        }
    });
    xhr.addEventListener('error', () => {
        window.clearInterval(processingTimer);
        setUploadBusy(false);
        progressWrap.classList.add('d-none');
        progressStatus.classList.add('d-none');
        showToast('Gagal mengirim file.', 'danger');
    });
    xhr.open('POST', 'index.php');
    xhr.send(formData);
});

document.getElementById('saveCustomerBtn').addEventListener('click', () => {
    const form = document.getElementById('customerForm');
    if (!form.reportValidity()) return;
    const formData = new FormData(form);
    fetch('index.php', { method:'POST', body:formData })
    .then(r => r.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) {
            const returnToCustomers = new URLSearchParams(window.location.search).has('edit_customer')
                || new URLSearchParams(window.location.search).has('add_customer');
            setTimeout(() => {
                if (returnToCustomers) window.location.href = 'pelanggan.php';
                else window.location.reload();
            }, 900);
        }
    })
    .catch(() => showToast('Gagal menyimpan pelanggan.', 'danger'));
});

function appendPaketHargaInput(paket, focusInput = true) {
    const existing = Array.from(document.querySelectorAll('.harga-input'))
        .find(input => input.dataset.paket === paket);
    if (existing) {
        if (focusInput) existing.focus();
        return;
    }

    const col = document.createElement('div');
    col.className = 'col-sm-6 col-md-4 paket-harga-item';
    const box = document.createElement('div');
    box.className = 'border rounded p-2 paket-harga-box';
    const label = document.createElement('label');
    label.className = 'paket-harga-label';
    label.textContent = paket;
    const input = document.createElement('input');
    input.type = 'number';
    input.name = `harga[${paket}]`;
    input.className = 'form-control harga-input';
    input.min = '0';
    input.step = '100';
    input.value = '0';
    input.dataset.paket = paket;
    box.append(label, input);
    col.appendChild(box);
    document.getElementById('paketHargaContainer').appendChild(col);
    if (focusInput) input.focus();
}

document.getElementById('addPaketBtn').addEventListener('click', () => {
    const nameInput = document.getElementById('newPaketName');
    const namaPaket = nameInput.value.trim();
    if (!namaPaket) {
        showToast('Masukkan nama paket baru.', 'warning');
        nameInput.focus();
        return;
    }

    const formData = new FormData();
    formData.append('action', 'tambah_paket');
    formData.append('nama_paket', namaPaket);
    fetch('index.php', { method: 'POST', body: formData })
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Gagal menambah paket.');
            return data;
        })
        .then(data => {
            appendPaketHargaInput(data.paket);
            nameInput.value = '';
            showToast(`Paket ${data.paket} siap diberi harga.`, 'success');
        })
        .catch(error => showToast(error.message, 'danger'));
});

const packageModal = document.getElementById('packageModal');
const packageList = document.getElementById('packageManagementList');
const packageEmpty = document.getElementById('packageManagementEmpty');
const canManagePackages = <?= authIsAdmin() ? 'true' : 'false' ?>;

function sendPackageAction(action, values = {}) {
    const formData = new FormData();
    formData.append('action', action);
    Object.entries(values).forEach(([key, value]) => formData.append(key, value));
    return fetch('index.php', { method: 'POST', body: formData }).then(async response => {
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Permintaan paket gagal.');
        return data;
    });
}

function createPackageRow(packageData) {
    const row = document.createElement('article');
    row.className = 'package-management-row';

    const information = document.createElement('div');
    information.className = 'package-management-info';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control package-name-input';
    input.maxLength = 100;
    input.value = packageData.nama;
    input.readOnly = true;
    const meta = document.createElement('small');
    meta.textContent = `${packageData.customer_count} pelanggan · ${packageData.usage_count} data rekap`;
    information.append(input, meta);

    row.append(information);
    if (canManagePackages) {
        const actions = document.createElement('div');
        actions.className = 'package-management-actions';
        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'btn btn-outline-primary';
        editButton.innerHTML = '<i class="fas fa-pen"></i><span>Edit</span>';
        editButton.addEventListener('click', () => {
            if (input.readOnly) {
                input.readOnly = false;
                input.focus();
                input.select();
                editButton.innerHTML = '<i class="fas fa-check"></i><span>Simpan</span>';
                return;
            }

            const newName = input.value.trim();
            if (!newName) {
                showToast('Nama paket wajib diisi.', 'warning');
                input.focus();
                return;
            }
            sendPackageAction('update_package', { package_id: packageData.id, nama_paket: newName })
                .then(data => {
                    showToast(data.message, 'success');
                    window.setTimeout(() => window.location.reload(), 650);
                })
                .catch(error => showToast(error.message, 'danger'));
        });

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'btn btn-outline-danger';
        deleteButton.innerHTML = '<i class="fas fa-trash"></i><span>Hapus</span>';
        deleteButton.title = packageData.usage_count > 0
            ? 'Paket yang sudah digunakan tidak dapat dihapus'
            : 'Hapus paket';
        deleteButton.addEventListener('click', () => {
            if (!window.confirm(`Hapus paket "${packageData.nama}"? Harga paket pelanggan juga akan dihapus.`)) return;
            sendPackageAction('delete_package', { package_id: packageData.id })
                .then(data => {
                    showToast(data.message, 'success');
                    loadPackages();
                })
                .catch(error => showToast(error.message, 'danger'));
        });

        actions.append(editButton, deleteButton);
        row.append(actions);
    }
    return row;
}

function loadPackages() {
    packageList.innerHTML = '<div class="text-center text-muted py-3">Memuat paket...</div>';
    fetch('index.php?action=list_packages')
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Daftar paket gagal dimuat.');
            return data;
        })
        .then(data => {
            packageList.replaceChildren(...data.packages.map(createPackageRow));
            packageEmpty.classList.toggle('d-none', data.packages.length > 0);
            document.getElementById('packageTotal').textContent = data.packages.length;
        })
        .catch(error => {
            packageList.innerHTML = '';
            showToast(error.message, 'danger');
        });
}

packageModal.addEventListener('show.bs.modal', loadPackages);
document.getElementById('packageModalAdd')?.addEventListener('click', () => {
    const input = document.getElementById('packageModalName');
    const name = input.value.trim();
    if (!name) {
        showToast('Masukkan nama paket baru.', 'warning');
        input.focus();
        return;
    }
    sendPackageAction('tambah_paket', { nama_paket: name })
        .then(data => {
            input.value = '';
            appendPaketHargaInput(data.paket, false);
            showToast(`Paket ${data.paket} berhasil ditambahkan.`, 'success');
            loadPackages();
        })
        .catch(error => showToast(error.message, 'danger'));
});

document.getElementById('packageModalName')?.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
        event.preventDefault();
        document.getElementById('packageModalAdd').click();
    }
});

document.getElementById('customerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('customerForm').reset();
    document.getElementById('modalTitle').textContent = 'Tambah Pelanggan';
    document.getElementById('custPrefix').readOnly = false;
    this.classList.remove('is-editing');
    document.getElementById('deleteCustomerBtn')?.classList.add('d-none');
    document.getElementById('billingModeExisting').checked = true;
    toggleBillingMode();
    document.querySelectorAll('.harga-input').forEach(inp => inp.value = '0');
});

const listModal = document.getElementById('listCustomerModal');
const canDeleteCustomers = <?= authIsAdmin() ? 'true' : 'false' ?>;

function loadCustomerList(page = 1) {
    fetch(`index.php?action=list_customers&customer_page=${encodeURIComponent(page)}`)
    .then(async response => {
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Daftar pelanggan gagal dimuat.');
        return data;
    })
    .then(result => {
        customerListPage = result.page;
        customerListTotalPages = result.total_pages;
        const tbody = document.querySelector('#listCustomerTable tbody');
        tbody.innerHTML = '';
        result.customers.forEach(c => {
            const deleteAction = canDeleteCustomers
                ? `<button class="btn btn-sm btn-outline-danger delete-cust customer-delete-button" type="button" data-prefix="${escapeHtml(c.prefix)}" data-name="${escapeHtml(c.nama_pelanggan)}" aria-label="Hapus pelanggan ${escapeHtml(c.nama_pelanggan)}"><i class="fas fa-trash"></i><span>Hapus</span></button>`
                : '';
            tbody.innerHTML += `<tr>
                <td>${escapeHtml(c.prefix)}</td>
                <td>${escapeHtml(c.nama_pelanggan)}</td>
                <td class="customer-address">${escapeHtml(c.alamat || 'N/A')}</td>
                <td>${escapeHtml(c.telepon || 'N/A')}</td>
                <td>${escapeHtml(c.billing || 'N/A')}</td>
                <td class="customer-table-action"><div class="customer-row-actions"><button class="btn btn-sm btn-warning edit-cust customer-edit-button" type="button" data-prefix="${escapeHtml(c.prefix)}" aria-label="Edit pelanggan ${escapeHtml(c.nama_pelanggan)}"><i class="fas fa-edit"></i><span>Edit</span></button>${deleteAction}</div></td>
            </tr>`;
        });
        document.querySelectorAll('.edit-cust').forEach(btn => {
            btn.addEventListener('click', function() {
                const prefix = this.dataset.prefix;
                fetch(`index.php?action=get_customer&prefix=${encodeURIComponent(prefix)}`)
                .then(r => r.json())
                .then(cust => {
                    if (cust) {
                        document.getElementById('modalTitle').textContent = 'Edit Pelanggan';
                        const customerModalElement = document.getElementById('customerModal');
                        customerModalElement.classList.add('is-editing');
                        document.getElementById('deleteCustomerBtn')?.classList.remove('d-none');
                        document.getElementById('custPrefix').value = cust.prefix;
                        document.getElementById('custPrefix').readOnly = true;
                        document.getElementById('custNama').value = cust.nama_pelanggan;
                        document.getElementById('custAlamat').value = cust.alamat||'';
                        document.getElementById('custTelepon').value = cust.telepon||'';
                        document.getElementById('billingModeExisting').checked = true;
                        document.getElementById('custBilling').value = cust.billing_id || '';
                        document.getElementById('custBillingNew').value = '';
                        toggleBillingMode();
                        document.querySelectorAll('.harga-input').forEach(inp => inp.value = '0');
                        if (cust.harga) {
                            for (const [pkt, hrg] of Object.entries(cust.harga)) {
                                const inp = Array.from(document.querySelectorAll('.harga-input'))
                                    .find(input => input.dataset.paket === pkt);
                                if (inp) inp.value = hrg;
                            }
                        }
                        bootstrap.Modal.getInstance(listModal).hide();
                        new bootstrap.Modal(document.getElementById('customerModal')).show();
                    }
                });
            });
        });
        document.querySelectorAll('.delete-cust').forEach(button => {
            button.addEventListener('click', () => {
                deleteCustomer(button.dataset.prefix || '', button.dataset.name || '');
            });
        });
        document.getElementById('customerListCaption').textContent = `Halaman ${result.page} dari ${result.total_pages} · ${result.total} pelanggan`;
        document.getElementById('customerListPrevious').disabled = result.page <= 1;
        document.getElementById('customerListNext').disabled = result.page >= result.total_pages;
    })
    .catch(error => showToast(error.message, 'danger'));
}

listModal?.addEventListener('show.bs.modal', () => loadCustomerList(1));
document.getElementById('customerListPrevious')?.addEventListener('click', () => {
    if (customerListPage > 1) loadCustomerList(customerListPage - 1);
});
document.getElementById('customerListNext')?.addEventListener('click', () => {
    if (customerListPage < customerListTotalPages) loadCustomerList(customerListPage + 1);
});

// Halaman pelanggan menggunakan tautan edit menuju editor yang sudah tersedia.
function openCustomerEditor(prefix) {
    fetch(`index.php?action=get_customer&prefix=${encodeURIComponent(prefix)}`)
        .then(async response => {
            const customer = await response.json();
            if (!response.ok || !customer) throw new Error('Pelanggan tidak ditemukan atau tidak dapat diakses.');
            return customer;
        })
        .then(cust => {
            document.getElementById('modalTitle').textContent = 'Edit Pelanggan';
            const customerModalElement = document.getElementById('customerModal');
            customerModalElement.classList.add('is-editing');
            document.getElementById('deleteCustomerBtn')?.classList.remove('d-none');
            document.getElementById('custPrefix').value = cust.prefix;
            document.getElementById('custPrefix').readOnly = true;
            document.getElementById('custNama').value = cust.nama_pelanggan;
            document.getElementById('custAlamat').value = cust.alamat || '';
            document.getElementById('custTelepon').value = cust.telepon || '';
            document.getElementById('billingModeExisting').checked = true;
            document.getElementById('custBilling').value = cust.billing_id || '';
            document.getElementById('custBillingNew').value = '';
            toggleBillingMode();
            document.querySelectorAll('.harga-input').forEach(input => input.value = '0');
            Object.entries(cust.harga || {}).forEach(([packageName, price]) => {
                const input = Array.from(document.querySelectorAll('.harga-input'))
                    .find(item => item.dataset.paket === packageName);
                if (input) input.value = price;
            });
            bootstrap.Modal.getOrCreateInstance(customerModalElement).show();
        })
        .catch(error => showToast(error.message, 'danger'));
}

const customerRoute = new URLSearchParams(window.location.search);
if (customerRoute.has('add_customer')) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('customerModal')).show();
} else if (customerRoute.get('edit_customer')) {
    openCustomerEditor(customerRoute.get('edit_customer'));
}

function deleteCustomer(prefix, customerName) {
    if (!prefix || !window.confirm(`Hapus pelanggan "${customerName}" (${prefix})? Akun customer, harga paket, dan invoice pelanggan akan dihapus.`)) return;

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
            bootstrap.Modal.getInstance(document.getElementById('customerModal'))?.hide();
            showToast(data.message, 'success');
            window.setTimeout(() => {
                if (new URLSearchParams(window.location.search).has('edit_customer')) {
                    window.location.href = 'pelanggan.php';
                } else {
                    window.location.reload();
                }
            }, 700);
        })
        .catch(error => showToast(error.message, 'danger'));
}

document.getElementById('deleteCustomerBtn')?.addEventListener('click', () => {
    deleteCustomer(
        document.getElementById('custPrefix').value.trim(),
        document.getElementById('custNama').value.trim()
    );
});

function setupRealtimeSearch(inputId, clearSelector, filterCallback) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const clearButton = input.closest('.tab-search').querySelector(clearSelector);
    const runFilter = () => {
        const query = input.value.trim().toLocaleLowerCase('id-ID');
        clearButton.classList.toggle('d-none', query === '');
        filterCallback(query);
    };
    input.addEventListener('input', runFilter);
    clearButton.addEventListener('click', () => {
        input.value = '';
        runFilter();
        input.focus();
    });
}

setupRealtimeSearch('rekapSearch', '.tab-search-clear', query => {
    const rows = Array.from(document.querySelectorAll('#rekap tbody tr[data-search-name]'));
    let visible = 0;
    rows.forEach(row => {
        const matches = !query || row.dataset.searchName.includes(query);
        row.classList.toggle('d-none', !matches);
        if (matches) visible++;
    });
    document.getElementById('rekapSearchEmpty')?.classList.toggle('d-none', visible > 0);
    document.getElementById('rekapPagination')?.classList.toggle('d-none', query !== '');
});

</script>
<script src="assets/js/mobile-keyboard.js"></script>
<script src="assets/js/interaction-loading.js"></script>
</body>
</html>
