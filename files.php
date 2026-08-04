<?php
session_start();
require_once __DIR__.'/assets/php/cont/cont.php';
require_once __DIR__.'/auth.php';

$autoloadPath = __DIR__.'/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    die('Dependensi Composer tidak ditemukan. Jalankan "composer install" terlebih dahulu.');
}
require_once $autoloadPath;
require_once __DIR__.'/assets/php/functions/main-function.php';

ensureDatabase();
ensureAuthSchema();
requireStaff();
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(GENERATED_DIR)) mkdir(GENERATED_DIR, 0755, true);
if (!is_dir(ZIP_DIR)) mkdir(ZIP_DIR, 0755, true);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
require_once __DIR__.'/assets/php/functions/logic.php';

$db = getDB();
$archiveAccessCondition = authIsAdmin()
    ? '1=1'
    : 'EXISTS (SELECT 1 FROM rekap access_rekap
        INNER JOIN prefix_customers access_customer ON access_customer.prefix = access_rekap.prefix
        WHERE access_rekap.file_id = f.id AND '.authBillingCondition('access_customer.billing_id', $db).')';
$documentAccessCondition = authIsAdmin()
    ? '1=1'
    : '('.authBillingCondition('d.billing_id', $db).' OR (d.billing_id IS NULL AND d.generated_by_user_id = '.authUserId().'))';

// Arsip dipaginasi berdasarkan tanggal agar tetap ringan ketika file bertambah.
$totalArchiveFiles = (int)($db->query("SELECT COUNT(*) AS total FROM uploaded_files f
    WHERE {$archiveAccessCondition}")->fetch_assoc()['total'] ?? 0);
$archiveDateTotal = (int)($db->query("SELECT COUNT(DISTINCT f.tanggal) AS total FROM uploaded_files f
    WHERE {$archiveAccessCondition}")->fetch_assoc()['total'] ?? 0);
$archiveTotalPages = max(1, (int)ceil($archiveDateTotal / DATE_GROUPS_PER_PAGE));
$archivePage = min(max(1, (int)($_GET['archive_page'] ?? 1)), $archiveTotalPages);
$archiveOffset = ($archivePage - 1) * DATE_GROUPS_PER_PAGE;
$archiveDates = [];
$result = $db->query("SELECT f.tanggal FROM uploaded_files f WHERE {$archiveAccessCondition}
    GROUP BY f.tanggal ORDER BY f.tanggal DESC LIMIT {$archiveOffset}, ".DATE_GROUPS_PER_PAGE);
while ($result && $row = $result->fetch_assoc()) $archiveDates[] = (string)$row['tanggal'];
$archiveGroups = [];
if ($archiveDates) {
    $dateSql = "'".implode("','", array_map([$db, 'real_escape_string'], $archiveDates))."'";
    $rows = [];
    $result = $db->query("SELECT f.id, f.saved_name, f.total_rows, f.periode, f.tanggal,
            COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Tidak diketahui') AS uploaded_by
        FROM uploaded_files f
        LEFT JOIN users u ON u.id = f.uploaded_by_user_id
        WHERE f.tanggal IN ({$dateSql}) AND {$archiveAccessCondition}
        ORDER BY f.tanggal DESC, f.id DESC");
    while ($result && $row = $result->fetch_assoc()) $rows[] = $row;
    $archiveGroups = groupRowsByDate($rows, 'tanggal');
}

/** Memuat PDF/Excel atau ZIP dalam pagination tanggal yang terpisah. */
$loadDocumentSection = static function(mysqli $db, string $typeCondition, string $pageKey, string $accessCondition): array {
    $where = "{$accessCondition} AND {$typeCondition}";
    $total = (int)($db->query("SELECT COUNT(*) AS total FROM generated_documents d WHERE {$where}")
        ->fetch_assoc()['total'] ?? 0);
    $dateTotal = (int)($db->query("SELECT COUNT(DISTINCT DATE(d.created_at)) AS total
        FROM generated_documents d WHERE {$where}")->fetch_assoc()['total'] ?? 0);
    $totalPages = max(1, (int)ceil($dateTotal / DATE_GROUPS_PER_PAGE));
    $page = min(max(1, (int)($_GET[$pageKey] ?? 1)), $totalPages);
    $offset = ($page - 1) * DATE_GROUPS_PER_PAGE;
    $dates = [];
    $result = $db->query("SELECT DATE(d.created_at) AS document_date FROM generated_documents d
        WHERE {$where} GROUP BY DATE(d.created_at) ORDER BY document_date DESC
        LIMIT {$offset}, ".DATE_GROUPS_PER_PAGE);
    while ($result && $row = $result->fetch_assoc()) $dates[] = (string)$row['document_date'];

    $groups = [];
    if ($dates) {
        $dateSql = "'".implode("','", array_map([$db, 'real_escape_string'], $dates))."'";
        $rows = [];
        $result = $db->query("SELECT d.id, d.document_type, d.original_name, d.file_size,
                d.billing_id, d.created_at, f.periode, f.tanggal,
                COALESCE(b.nama, 'Semua billing') AS billing,
                COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Tidak diketahui') AS generated_by
            FROM generated_documents d
            INNER JOIN uploaded_files f ON f.id = d.file_id
            LEFT JOIN billing_master b ON b.id = d.billing_id
            LEFT JOIN users u ON u.id = d.generated_by_user_id
            WHERE {$where} AND DATE(d.created_at) IN ({$dateSql})
            ORDER BY d.created_at DESC, d.id DESC");
        while ($result && $row = $result->fetch_assoc()) $rows[] = $row;
        $groups = groupRowsByDate($rows, 'created_at');
    }

    return compact('total', 'page', 'totalPages', 'groups');
};

$generatedSection = $loadDocumentSection(
    $db,
    "d.document_type IN ('PDF','EXCEL')",
    'document_page',
    $documentAccessCondition
);
$zipSection = $loadDocumentSection(
    $db,
    "d.document_type = 'ZIP'",
    'zip_page',
    $documentAccessCondition
);
$db->close();

function filesPagination(int $current, int $total, string $key, string $hash): string {
    if ($total <= 1) return '';
    $html = '<nav class="date-pagination" aria-label="Pagination file"><div class="pagination-caption">Halaman '.$current.' dari '.$total.'</div><ul class="pagination pagination-sm mb-0">';
    $previous = max(1, $current - 1);
    $next = min($total, $current + 1);
    $html .= '<li class="page-item '.($current <= 1 ? 'disabled' : '').'"><a class="page-link" href="?'.$key.'='.$previous.$hash.'">&lsaquo;</a></li>';
    foreach (paginationPageItems($current, $total) as $page) {
        if ($page === null) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        else $html .= '<li class="page-item '.($page === $current ? 'active' : '').'"><a class="page-link" href="?'.$key.'='.$page.$hash.'">'.$page.'</a></li>';
    }
    $html .= '<li class="page-item '.($current >= $total ? 'disabled' : '').'"><a class="page-link" href="?'.$key.'='.$next.$hash.'">&rsaquo;</a></li></ul></nav>';
    return $html;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Files - PLANETFlow</title>
    <link rel="stylesheet" href="assets/vendor/poppins/poppins.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main-style.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/main-style.css') ?>">
    <link rel="icon" type="image/ico" href="assets/favicon.ico">
</head>
<body class="files-page">
<?php include __DIR__.'/assets/php/include/navbar.php'; ?>
<main class="container-fluid page-container app-shell px-lg-4 pb-5">
    <section class="page-heading">
        <div>
            <div class="eyebrow">File Explorer</div>
            <h1 class="page-title">Files</h1>
            <p class="page-description">Kelola arsip upload, dokumen generated, dan paket ZIP dari satu halaman.</p>
        </div>
        <div class="files-summary">
            <span><i class="fas fa-box-archive"></i><strong><?= $totalArchiveFiles ?></strong> arsip</span>
            <span><i class="fas fa-file-lines"></i><strong><?= $generatedSection['total'] ?></strong> dokumen</span>
            <span><i class="fas fa-file-zipper"></i><strong><?= $zipSection['total'] ?></strong> ZIP</span>
        </div>
    </section>

    <ul class="nav nav-pills app-tabs files-tabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#arsip"><i class="fas fa-box-archive"></i>Arsip</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dokumen"><i class="fas fa-folder-open"></i>Dokumen generated</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#zip"><i class="fas fa-file-zipper"></i>ZIP</button></li>
    </ul>

    <div class="tab-content data-panel files-data-panel">
        <section class="tab-pane fade show active" id="arsip">
            <div class="tab-search"><i class="fas fa-calendar-days tab-search-icon"></i><input type="search" class="form-control files-date-search" data-target="archiveDateAccordion" data-empty="archiveSearchEmpty" placeholder="Cari tanggal arsip..."><button type="button" class="tab-search-clear d-none"><i class="fas fa-xmark"></i></button></div>
            <?php if (!$archiveGroups): ?>
                <div class="text-center text-muted py-5">Belum ada arsip upload.</div>
            <?php else: ?>
                <div class="accordion date-accordion" id="archiveDateAccordion">
                <?php $groupIndex = 0; foreach ($archiveGroups as $date => $files): $collapseId = 'archive-'.$groupIndex; ?>
                    <article class="accordion-item" data-search-date="<?= htmlspecialchars(mb_strtolower(formatDateGroup($date).' '.$date), ENT_QUOTES, 'UTF-8') ?>">
                        <h2 class="accordion-header"><button class="accordion-button <?= $groupIndex ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"><span class="date-title"><i class="fas fa-calendar-day me-2"></i><?= htmlspecialchars(formatDateGroup($date)) ?></span><span class="badge rounded-pill text-bg-primary ms-2"><?= count($files) ?> arsip</span></button></h2>
                        <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $groupIndex ? '' : 'show' ?>" data-bs-parent="#archiveDateAccordion"><div class="accordion-body"><div class="file-card-list">
                        <?php foreach ($files as $file): ?>
                            <article class="file-entry"><div class="file-entry-main"><span class="file-entry-icon archive-icon"><i class="fas fa-file-arrow-up"></i></span><div class="file-entry-info"><h3><?= htmlspecialchars($file['saved_name']) ?></h3><div class="file-entry-meta"><span><i class="fas fa-list-ol"></i><?= (int)$file['total_rows'] ?> baris</span><span><i class="fas fa-calendar"></i><?= htmlspecialchars($file['periode'] ?: '-') ?></span><span><i class="fas fa-user"></i><?= htmlspecialchars($file['uploaded_by']) ?></span></div></div></div><div class="file-entry-actions"><a href="index.php?upload_id=<?= (int)$file['id'] ?>#rekap" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i>Rekap</a><a href="files.php?action=export_rekap&amp;file_id=<?= (int)$file['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i>Excel</a><a href="files.php?action=download_all_invoices&amp;file_id=<?= (int)$file['id'] ?>&amp;view=1" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i>PDF</a></div></article>
                        <?php endforeach; ?>
                        </div></div></div>
                    </article>
                <?php $groupIndex++; endforeach; ?>
                </div>
                <div class="tab-search-empty d-none" id="archiveSearchEmpty"><i class="fas fa-calendar-xmark"></i>Arsip tidak ditemukan.</div>
                <?= filesPagination($archivePage, $archiveTotalPages, 'archive_page', '#arsip') ?>
            <?php endif; ?>
        </section>

        <?php foreach ([['id' => 'dokumen', 'data' => $generatedSection, 'page_key' => 'document_page', 'empty' => 'Belum ada dokumen generated.'], ['id' => 'zip', 'data' => $zipSection, 'page_key' => 'zip_page', 'empty' => 'Belum ada ZIP tersimpan.']] as $section): ?>
        <section class="tab-pane fade" id="<?= $section['id'] ?>">
            <div class="tab-search"><i class="fas fa-calendar-days tab-search-icon"></i><input type="search" class="form-control files-date-search" data-target="<?= $section['id'] ?>DateAccordion" data-empty="<?= $section['id'] ?>SearchEmpty" placeholder="Cari tanggal <?= $section['id'] ?>..."><button type="button" class="tab-search-clear d-none"><i class="fas fa-xmark"></i></button></div>
            <?php if (!$section['data']['groups']): ?>
                <div class="text-center text-muted py-5"><?= $section['empty'] ?></div>
            <?php else: ?>
                <div class="accordion date-accordion" id="<?= $section['id'] ?>DateAccordion">
                <?php $groupIndex = 0; foreach ($section['data']['groups'] as $date => $documents): $collapseId = $section['id'].'-'.$groupIndex; ?>
                    <article class="accordion-item" data-search-date="<?= htmlspecialchars(mb_strtolower(formatDateGroup($date).' '.$date), ENT_QUOTES, 'UTF-8') ?>">
                        <h2 class="accordion-header"><button class="accordion-button <?= $groupIndex ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"><span class="date-title"><i class="fas fa-calendar-day me-2"></i><?= htmlspecialchars(formatDateGroup($date)) ?></span><span class="badge rounded-pill text-bg-primary ms-2"><?= count($documents) ?> file</span></button></h2>
                        <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $groupIndex ? '' : 'show' ?>" data-bs-parent="#<?= $section['id'] ?>DateAccordion"><div class="accordion-body"><div class="file-card-list">
                        <?php foreach ($documents as $document): $type = strtoupper($document['document_type']); ?>
                            <article class="file-entry"><div class="file-entry-main"><span class="file-entry-icon <?= $type === 'PDF' ? 'document-pdf-icon' : ($type === 'ZIP' ? 'archive-icon' : 'document-excel-icon') ?>"><i class="fas <?= $type === 'PDF' ? 'fa-file-pdf' : ($type === 'ZIP' ? 'fa-file-zipper' : 'fa-file-excel') ?>"></i></span><div class="file-entry-info"><h3><?= htmlspecialchars($document['original_name']) ?></h3><div class="file-entry-meta"><span><i class="fas fa-building"></i><?= htmlspecialchars($document['billing']) ?></span><span><i class="fas fa-hard-drive"></i><?= htmlspecialchars(formatBytes((int)$document['file_size'])) ?></span><span><i class="fas fa-user"></i><?= htmlspecialchars($document['generated_by']) ?></span></div></div></div><div class="file-entry-actions"><?php if ($type === 'PDF'): ?><a href="files.php?action=document_file&amp;id=<?= (int)$document['id'] ?>&amp;view=1" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i>Lihat</a><?php elseif ($type === 'EXCEL'): ?><a href="files.php?action=view_excel&amp;id=<?= (int)$document['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i>Lihat</a><?php endif; ?><a href="files.php?action=document_file&amp;id=<?= (int)$document['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-download"></i>Download</a></div></article>
                        <?php endforeach; ?>
                        </div></div></div>
                    </article>
                <?php $groupIndex++; endforeach; ?>
                </div>
                <div class="tab-search-empty d-none" id="<?= $section['id'] ?>SearchEmpty"><i class="fas fa-calendar-xmark"></i>File tidak ditemukan.</div>
                <?= filesPagination($section['data']['page'], $section['data']['totalPages'], $section['page_key'], '#'.$section['id']) ?>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
</main>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
const fileTabHash = window.location.hash;
if (['#arsip', '#dokumen', '#zip'].includes(fileTabHash)) {
    const tab = document.querySelector(`[data-bs-target="${fileTabHash}"]`);
    if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
}

document.querySelectorAll('.files-date-search').forEach(input => {
    const clear = input.parentElement.querySelector('.tab-search-clear');
    const filter = () => {
        const query = input.value.trim().toLocaleLowerCase('id-ID');
        clear.classList.toggle('d-none', query === '');
        const accordion = document.getElementById(input.dataset.target);
        const items = Array.from(accordion?.querySelectorAll(':scope > .accordion-item') || []);
        let visible = 0;
        items.forEach(item => {
            const match = !query || item.dataset.searchDate.includes(query);
            item.classList.toggle('d-none', !match);
            if (match) visible++;
        });
        document.getElementById(input.dataset.empty)?.classList.toggle('d-none', visible > 0);
    };
    input.addEventListener('input', filter);
    clear.addEventListener('click', () => { input.value = ''; filter(); input.focus(); });
});
</script>
<script src="assets/js/mobile-keyboard.js"></script>
<script src="assets/js/interaction-loading.js"></script>
</body>
</html>
