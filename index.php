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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');


require_once __DIR__ . '/assets/php/functions/logic.php';

// ===================== TAMPILAN HALAMAN =====================
$db = getDB();
$paketList = getPaketList($db);
$billingList = getBillingList($db);

$latestFileId = null;
$latestUploadMetadata = ['periode' => '', 'tanggal' => ''];
$requestedUploadId = max(0, (int)($_GET['upload_id'] ?? 0));
if ($requestedUploadId > 0) {
    $stmt = $db->prepare("SELECT id, periode, tanggal FROM uploaded_files WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $requestedUploadId);
    $stmt->execute();
    $selectedUpload = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $res = $db->query("SELECT id, periode, tanggal FROM uploaded_files ORDER BY id DESC LIMIT 1");
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

$rekapData = []; $allPaket = $paketList; $customerNames = []; $awalanSubset = [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPages = 1;
$totalAwalan = 0;
$mainBillingCondition = authIsAdmin() ? '1=1' : authBillingCondition('c.billing_id', $db);

if ($latestFileId) {
    $totalAwalan = $db->query("SELECT COUNT(DISTINCT r.awalan) AS cnt
        FROM rekap r
        LEFT JOIN prefix_customers c ON c.awalan = r.awalan
        WHERE r.file_id = {$latestFileId} AND {$mainBillingCondition}")->fetch_assoc()['cnt'];
    $totalPages = max(1, (int)ceil($totalAwalan / PER_PAGE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * PER_PAGE;

    $res = $db->query("SELECT daftar.awalan,
            COALESCE(NULLIF(TRIM(c.nama_pelanggan), ''), 'N/A') AS nama
        FROM (SELECT DISTINCT awalan FROM rekap WHERE file_id = {$latestFileId}) daftar
        LEFT JOIN prefix_customers c ON c.awalan = daftar.awalan
        WHERE {$mainBillingCondition}
        ORDER BY nama ASC, daftar.awalan ASC
        LIMIT {$offset}, ".PER_PAGE);
    while ($r = $res->fetch_assoc()) {
        $awalanSubset[] = $r['awalan'];
        $customerNames[$r['awalan']] = $r['nama'];
    }

    if (!empty($awalanSubset)) {
        $in = "'" . implode("','", array_map([$db, 'real_escape_string'], $awalanSubset)) . "'";
        $res = $db->query("SELECT awalan, paket, jumlah FROM rekap WHERE file_id = {$latestFileId} AND awalan IN ({$in}) ORDER BY awalan, paket");
        while ($r = $res->fetch_assoc()) {
            $rekapData[$r['awalan']][$r['paket']] = (int)$r['jumlah'];
        }
    }
}

$allFiles = [];
$res = $db->query("SELECT f.id, f.saved_name, f.total_rows, f.periode, f.tanggal, f.uploaded_at,
        COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Tidak diketahui') AS uploaded_by
    FROM uploaded_files f
    LEFT JOIN users u ON u.id = f.uploaded_by_user_id
    ORDER BY f.uploaded_at DESC, f.id DESC");
while ($f = $res->fetch_assoc()) $allFiles[] = $f;
$filesByDate = groupRowsByDate($allFiles, 'tanggal');
[$pagedFilesByDate, $archivePage, $archiveTotalPages] = paginateDateGroups($filesByDate, (int)($_GET['archive_page'] ?? 1));
$totalPelanggan = 0;
$res = $db->query("SELECT COUNT(*) AS total FROM prefix_customers c WHERE ".(authIsAdmin() ? '1=1' : authBillingCondition('c.billing_id', $db)));
if ($res && $row = $res->fetch_assoc()) $totalPelanggan = (int)$row['total'];
$generatedDocs = [];
$documentWhere = authIsAdmin()
    ? '1=1'
    : '('.authBillingCondition('d.billing_id', $db).' OR (d.billing_id IS NULL AND d.generated_by_user_id = '.authUserId().'))';
$res = $db->query("SELECT d.id, d.document_type, d.original_name, d.file_size, d.billing_id,
        f.periode, f.tanggal, d.created_at,
        COALESCE(b.nama, 'Semua billing') AS billing,
        COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Tidak diketahui') AS generated_by
    FROM generated_documents d
    INNER JOIN uploaded_files f ON f.id = d.file_id
    LEFT JOIN billing_master b ON b.id = d.billing_id
    LEFT JOIN users u ON u.id = d.generated_by_user_id
    WHERE {$documentWhere}
    ORDER BY d.created_at DESC, d.id DESC");
while ($res && $row = $res->fetch_assoc()) $generatedDocs[] = $row;
$documentsByDate = groupRowsByDate($generatedDocs, 'created_at');
[$pagedDocumentsByDate, $documentPage, $documentTotalPages] = paginateDateGroups($documentsByDate, (int)($_GET['document_page'] ?? 1));
$totalDocuments = 0;
$res = $db->query("SELECT COUNT(*) AS total FROM generated_documents d WHERE {$documentWhere}");
if ($res && $row = $res->fetch_assoc()) $totalDocuments = (int)$row['total'];
$databaseBytes = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(data_length + index_length), 0) AS total_bytes
    FROM information_schema.tables WHERE table_schema = ?");
$databaseName = DB_NAME;
$stmt->bind_param('s', $databaseName);
$stmt->execute();
$databaseSizeRow = $stmt->get_result()->fetch_assoc();
if ($databaseSizeRow) $databaseBytes = (int)$databaseSizeRow['total_bytes'];
$stmt->close();
$databaseSize = formatBytes($databaseBytes);
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
    <link rel="stylesheet" href="assets/css/main-style.css">
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
            <div class="mini-stat" title="Total data dan indeks seluruh tabel database">
                <span>Disk Database</span><strong><?= htmlspecialchars($databaseSize) ?></strong>
            </div>
            <div class="mini-stat"><span>Paket</span><strong><?= count($paketList) ?></strong></div>
            <button type="button" class="mini-stat" data-bs-toggle="modal" data-bs-target="#listCustomerModal" title="Buka direktori pelanggan">
                <span>Pelanggan</span><strong><?= $totalPelanggan ?></strong>
            </button>
            <div class="mini-stat">
                <span>Dokumen</span><strong id="documentTotal"><?= $totalDocuments ?></strong>
            </div>
            <div class="mini-stat"><span>Arsip</span><strong><?= count($allFiles) ?></strong></div>
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
                        <input type="text" name="periode" id="uploadPeriode" class="form-control" maxlength="100" required placeholder="Contoh: Agustus 2026">
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="uploadTanggal" class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" id="uploadTanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">File voucher</label>
                        <div class="file-picker">
                            <input class="visually-hidden" type="file" name="excelfile" id="ef" accept=".xlsx,.xls,.ods,.csv" required>
                            <label class="file-picker-button" for="ef"><i class="fas fa-folder-open"></i>Pilih File</label>
                            <span class="file-picker-name" id="selectedFileName">Tidak ada file yang dipilih</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-primary w-100" id="uploadBtn"><i class="fas fa-upload"></i> Upload & Proses</button>
                    </div>
                </div>
                <div class="progress mt-3 d-none" id="progressWrap">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressFill" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%">0%</div>
                </div>
                <div class="text-muted mt-1 d-none" id="progressStatus">Menyiapkan upload...</div>
            </form>
            <div id="uploadNotif" class="mt-2"></div>
        </div>
    </div>

    <?php if (!empty($rekapData)): ?>
    <div class="card premium-card mb-4 report-card" id="quickActions">
        <div class="card-body">
            <div class="section-heading">
                <span class="section-icon"><i class="fas fa-file-export"></i></span>
                <div>
                    <h2>Aksi Data Terbaru</h2>
                    <p>Download atau lihat dokumen dari data yang baru diunggah.</p>
                </div>
            </div>
            <div class="metadata-note p-3 mb-3">
                <i class="fas fa-lightbulb me-1"></i>
                Periode dan tanggal mengikuti metadata yang ditentukan saat file diunggah agar semua dokumen konsisten.
            </div>
            <form method="get" id="metadataDownloadForm" class="row g-3 align-items-end">
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
                                id="reportBillingButton" data-bs-toggle="dropdown" aria-expanded="false">
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
        </div>
    </div>

    <ul class="nav nav-pills app-tabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rekap"><i class="fas fa-table-list me-1"></i> Rekap</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#arsip"><i class="fas fa-box-archive me-1"></i> Arsip</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dokumen"><i class="fas fa-folder-open me-1"></i> Dokumen <span class="badge rounded-pill text-bg-light ms-1"><?= $totalDocuments ?></span></button></li>
    </ul>

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
                    <?php foreach ($awalanSubset as $awalan): $pakets = $rekapData[$awalan] ?? []; ?>
                        <tr data-search-name="<?= htmlspecialchars(mb_strtolower($customerNames[$awalan] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= htmlspecialchars($customerNames[$awalan] ?? 'N/A') ?></td>
                            <td class="awalan"><?= htmlspecialchars($awalan) ?></td>
                            <?php foreach ($allPaket as $p): $cnt = $pakets[$p] ?? 0; ?>
                                <td><?= $cnt ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tab-search-empty d-none" id="rekapSearchEmpty"><i class="fas fa-magnifying-glass"></i> Nama pelanggan tidak ditemukan.</div>
            <?php if ($totalAwalan > 0): ?>
            <nav class="date-pagination" id="rekapPagination" aria-label="Pagination rekap">
                <div class="pagination-caption">Halaman <?= $page ?> dari <?= $totalPages ?></div>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <?php if ($page > 1): ?><a class="page-link" href="?page=<?= $page - 1 ?>&amp;archive_page=<?= $archivePage ?>&amp;document_page=<?= $documentPage ?>#rekap" aria-label="Sebelumnya">&lsaquo;</a><?php else: ?><span class="page-link">&lsaquo;</span><?php endif; ?>
                    </li>
                    <?php foreach (paginationPageItems($page, $totalPages) as $paginationPage): ?>
                        <?php if ($paginationPage === null): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php else: ?>
                            <li class="page-item <?= $paginationPage === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $paginationPage ?>&amp;archive_page=<?= $archivePage ?>&amp;document_page=<?= $documentPage ?>#rekap" <?= $paginationPage === $page ? 'aria-current="page"' : '' ?>><?= $paginationPage ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <?php if ($page < $totalPages): ?><a class="page-link" href="?page=<?= $page + 1 ?>&amp;archive_page=<?= $archivePage ?>&amp;document_page=<?= $documentPage ?>#rekap" aria-label="Berikutnya">&rsaquo;</a><?php else: ?><span class="page-link">&rsaquo;</span><?php endif; ?>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <div class="tab-pane fade" id="arsip">
            <?php if (empty($filesByDate)): ?>
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
                                                    <span><i class="fas fa-clock"></i><?= htmlspecialchars(date('H:i', strtotime($file['uploaded_at']))) ?></span>
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
            <?php if (empty($documentsByDate)): ?>
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
                                <span class="badge rounded-pill text-bg-primary ms-2"><?= count($dateDocuments) ?> dokumen</span>
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
                                                    <span><i class="fas fa-clock"></i><?= htmlspecialchars(date('H:i', strtotime($doc['created_at']))) ?></span>
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
    </div>
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
                            <label class="form-label">Awalan *</label>
                            <input type="text" name="awalan" class="form-control" maxlength="10" required id="custAwalan">
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="saveCustomerBtn"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Daftar Pelanggan -->
<div class="modal fade" id="listCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable customer-list-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="section-heading mb-0">
                    <span class="section-icon"><i class="fas fa-address-book"></i></span>
                    <div><h2>Direktori Pelanggan</h2><p>Detail alamat, telepon, billing, dan pengaturan pelanggan.</p></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="listCustomerTable">
                        <thead><tr><th>Awalan</th><th>Nama</th><th>Alamat</th><th>Telepon</th><th>Billing</th><th class="customer-table-action">Aksi</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
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
const requestedTabHash = window.location.hash;
if (['#rekap', '#arsip', '#dokumen'].includes(requestedTabHash)) {
    const requestedTab = document.querySelector(`[data-bs-target="${requestedTabHash}"]`);
    if (requestedTab) bootstrap.Tab.getOrCreateInstance(requestedTab).show();
}

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

        const totalEl = document.getElementById('documentTotal');
        if (totalEl) totalEl.textContent = String((Number(totalEl.textContent) || 0) + 2);
        showToast('ZIP berisi Excel dan PDF berhasil diunduh serta disimpan.', 'success');
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
        const totalEl = document.getElementById('documentTotal');
        if (totalEl) totalEl.textContent = String((Number(totalEl.textContent) || 0) + 1);
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

document.getElementById('ef').addEventListener('change', function() {
    document.getElementById('selectedFileName').textContent = this.files.length
        ? this.files[0].name
        : 'Tidak ada file yang dipilih';
});

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!this.reportValidity()) return;
    const fileInput = document.getElementById('ef');
    if (!fileInput.files.length) { showToast('Silakan pilih file terlebih dahulu.', 'warning'); return; }
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    const progressWrap = document.getElementById('progressWrap');
    const progressStatus = document.getElementById('progressStatus');
    const uploadBtn = document.getElementById('uploadBtn');
    let processingTimer = null;
    let processingProgress = 70;
    progressWrap.classList.remove('d-none');
    progressStatus.classList.remove('d-none');
    uploadBtn.disabled = true;
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
        uploadBtn.disabled = false;
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
        uploadBtn.disabled = false;
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
        if (data.success) setTimeout(() => location.reload(), 900);
    })
    .catch(() => showToast('Gagal menyimpan pelanggan.', 'danger'));
});

function appendPaketHargaInput(paket) {
    const existing = Array.from(document.querySelectorAll('.harga-input'))
        .find(input => input.dataset.paket === paket);
    if (existing) {
        existing.focus();
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
    input.focus();
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

document.getElementById('customerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('customerForm').reset();
    document.getElementById('modalTitle').textContent = 'Tambah Pelanggan';
    document.getElementById('custAwalan').readOnly = false;
    document.getElementById('billingModeExisting').checked = true;
    toggleBillingMode();
    document.querySelectorAll('.harga-input').forEach(inp => inp.value = '0');
});

const listModal = document.getElementById('listCustomerModal');
listModal.addEventListener('show.bs.modal', () => {
    fetch('index.php?action=list_customers')
    .then(r => r.json())
    .then(data => {
        const tbody = document.querySelector('#listCustomerTable tbody');
        tbody.innerHTML = '';
        data.forEach(c => {
            tbody.innerHTML += `<tr>
                <td>${escapeHtml(c.awalan)}</td>
                <td>${escapeHtml(c.nama_pelanggan)}</td>
                <td class="customer-address">${escapeHtml(c.alamat || 'N/A')}</td>
                <td>${escapeHtml(c.telepon || 'N/A')}</td>
                <td>${escapeHtml(c.billing || 'N/A')}</td>
                                <td class="customer-table-action"><button class="btn btn-sm btn-warning edit-cust customer-edit-button" type="button" data-awalan="${escapeHtml(c.awalan)}" aria-label="Edit pelanggan ${escapeHtml(c.nama_pelanggan)}"><i class="fas fa-edit"></i><span>Edit</span></button></td>
            </tr>`;
        });
        document.querySelectorAll('.edit-cust').forEach(btn => {
            btn.addEventListener('click', function() {
                const awalan = this.dataset.awalan;
                fetch(`index.php?action=get_customer&awalan=${encodeURIComponent(awalan)}`)
                .then(r => r.json())
                .then(cust => {
                    if (cust) {
                        document.getElementById('modalTitle').textContent = 'Edit Pelanggan';
                        document.getElementById('custAwalan').value = cust.awalan;
                        document.getElementById('custAwalan').readOnly = true;
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
    });
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

function filterDateAccordion(accordionId, emptyId, paginationId, query) {
    const accordion = document.getElementById(accordionId);
    if (!accordion) return;
    const items = Array.from(accordion.querySelectorAll(':scope > .accordion-item[data-search-date]'));
    let visible = 0;
    items.forEach(item => {
        const matches = !query || item.dataset.searchDate.includes(query);
        item.classList.toggle('d-none', !matches);
        if (matches) visible++;

        const collapseElement = item.querySelector('.accordion-collapse');
        const button = item.querySelector('.accordion-button');
        if (!collapseElement || !button) return;
        const collapse = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });
        const shouldOpen = query ? matches : item.dataset.initialOpen === '1';
        if (shouldOpen) collapse.show(); else collapse.hide();
    });
    document.getElementById(emptyId)?.classList.toggle('d-none', visible > 0);
    document.getElementById(paginationId)?.classList.toggle('d-none', query !== '');
}

setupRealtimeSearch('archiveSearch', '.tab-search-clear', query => {
    filterDateAccordion('archiveDateAccordion', 'archiveSearchEmpty', 'archivePagination', query);
});
setupRealtimeSearch('documentSearch', '.tab-search-clear', query => {
    filterDateAccordion('documentDateAccordion', 'documentSearchEmpty', 'documentPagination', query);
});
</script>
</body>
</html>
