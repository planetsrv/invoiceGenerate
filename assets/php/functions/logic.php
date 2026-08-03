<?php
// ===================== FILE EXPLORER: LIHAT / DOWNLOAD DOKUMEN =====================
if ($action === 'document_file' && isset($_GET['id'])) {
    $documentId = (int)$_GET['id'];
    $db = getDB();
    $stmt = $db->prepare("SELECT original_name, saved_name, document_type, billing_id, generated_by_user_id FROM generated_documents WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $documentId);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$document) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
    if (!authCanAccessDocument($document, $db)) {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke dokumen ini.');
    }
    $filePath = GENERATED_DIR.basename($document['saved_name']);
    if (!is_file($filePath)) {
        http_response_code(404);
        exit('File dokumen tidak tersedia.');
    }

    $isPdf = strtoupper($document['document_type']) === 'PDF';
    $contentType = $isPdf
        ? 'application/pdf'
        : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $inline = $isPdf && isset($_GET['view']);
    header('Content-Type: '.$contentType);
    header('Content-Length: '.filesize($filePath));
    header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.basename($document['original_name']).'"');
    readfile($filePath);
    exit;
}
// ===================== DOWNLOAD ZIP EXCEL + PDF =====================
if ($action === 'download_bundle' && isset($_GET['excel_id'], $_GET['pdf_id'])) {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('Ekstensi ZIP belum tersedia.');
    }
    $excelId = (int)$_GET['excel_id'];
    $pdfId = (int)$_GET['pdf_id'];
    $db = getDB();
    $stmt = $db->prepare("SELECT id, document_type, original_name, saved_name, billing_id, generated_by_user_id
        FROM generated_documents WHERE id IN (?,?)");
    $stmt->bind_param('ii', $excelId, $pdfId);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = [];
    while ($row = $result->fetch_assoc()) $documents[strtoupper($row['document_type'])] = $row;
    $stmt->close();

    if (!isset($documents['EXCEL'], $documents['PDF'])) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
    foreach ($documents as $document) {
        if (!authCanAccessDocument($document, $db)) {
            http_response_code(403);
            exit('Anda tidak memiliki akses ke salah satu dokumen.');
        }
    }

    $zipPath = tempnam(GENERATED_DIR, 'bundle_');
    $zip = new ZipArchive();
    if ($zipPath === false || $zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        exit('Gagal membuat ZIP');
    }
    foreach ($documents as $document) {
        $documentPath = GENERATED_DIR.basename($document['saved_name']);
        if (!is_file($documentPath)) {
            $zip->close();
            @unlink($zipPath);
            http_response_code(404);
            exit('Salah satu file dokumen tidak tersedia.');
        }
        $zip->addFile($documentPath, basename($document['original_name']));
    }
    $zip->close();

    $zipName = 'INV_plannet-'.date('Ymd_His').'.zip';
    header('Content-Type: application/zip');
    header('Content-Length: '.filesize($zipPath));
    header('Content-Disposition: attachment; filename="'.$zipName.'"');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

// ===================== VIEWER EXCEL =====================
if ($action === 'view_excel' && isset($_GET['id'])) {
    $documentId = (int)$_GET['id'];
    $db = getDB();
    $stmt = $db->prepare("SELECT original_name, saved_name, billing_id, generated_by_user_id FROM generated_documents
        WHERE id = ? AND document_type = 'EXCEL' LIMIT 1");
    $stmt->bind_param('i', $documentId);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$document) {
        http_response_code(404);
        exit('Dokumen Excel tidak ditemukan.');
    }
    if (!authCanAccessDocument($document, $db)) {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke dokumen ini.');
    }
    $filePath = GENERATED_DIR.basename($document['saved_name']);
    if (!is_file($filePath)) {
        http_response_code(404);
        exit('File Excel tidak tersedia di server.');
    }
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    ?>
    <!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($document['original_name']) ?></title>
    <style>
        body{margin:0;background:#f5f7fb;color:#172033;font-family:Arial,sans-serif}.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 20px;background:#0b1220;color:#fff}.topbar h1{margin:0;font-size:16px}.topbar a{color:#fff;text-decoration:none;background:#16845d;padding:9px 14px;border-radius:8px}.sheet-wrap{padding:18px;overflow:auto}.sheet{border-collapse:collapse;background:#fff;box-shadow:0 12px 35px rgba(15,23,42,.09)}.sheet td{min-width:90px;padding:7px 10px;border:1px solid #d8dee8;white-space:nowrap}.sheet tr:nth-child(4) td{background:#afc6dd;font-weight:bold;text-align:center}.sheet tr:nth-child(-n+3) td:first-child{font-weight:bold;text-align:right}@media(max-width:600px){.topbar{align-items:flex-start;flex-direction:column}.sheet-wrap{padding:10px}.sheet td{font-size:12px}}
    </style></head><body>
    <div class="topbar"><h1><?= htmlspecialchars($document['original_name']) ?></h1><a href="?action=document_file&id=<?= $documentId ?>">Excel</a></div>
    <div class="sheet-wrap"><table class="sheet"><tbody>
    <?php foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?= htmlspecialchars((string)$value) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div></body></html>
    <?php
    exit;
}

// ===================== 1. UPLOAD FILE EXCEL & HITUNG INVOICE =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelfile']) && $_FILES['excelfile']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['excelfile'];
    $uploadPeriode = trim((string)($_POST['periode'] ?? ''));
    $uploadTanggal = trim((string)($_POST['tanggal'] ?? ''));
    $dateObject = DateTime::createFromFormat('!Y-m-d', $uploadTanggal);
    if ($uploadPeriode === '' || mb_strlen($uploadPeriode) > 100) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Periode wajib diisi dan maksimal 100 karakter.']));
    }
    if (!$dateObject || $dateObject->format('Y-m-d') !== $uploadTanggal) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Tanggal upload wajib diisi dengan format yang valid.']));
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx','xls','ods','csv'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Format file tidak didukung. Pastikan file "xlsx","xls","ods","xlsx", "csv"']));
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Upload gagal. Kode error: ' . $file['error']]));
    }

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        // Posisi data ditentukan melalui konfigurasi; nama header tidak dibaca.
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        if (EXCEL_HEADER_ROW < 1 || EXCEL_CODE_COLUMN < 1 || EXCEL_PACKAGE_COLUMN < 1 || EXCEL_COST_COLUMN < 1) {
            throw new Exception('Pengaturan baris dan kolom Excel harus lebih besar dari 0.');
        }
        if (EXCEL_CODE_COLUMN > $highestColumnIndex || EXCEL_PACKAGE_COLUMN > $highestColumnIndex || EXCEL_COST_COLUMN > $highestColumnIndex) {
            throw new Exception('Kolom tidak sesuai.');
        }

        $rows = [];
        $kodeColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(EXCEL_CODE_COLUMN);
        $paketColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(EXCEL_PACKAGE_COLUMN);
        $biayaColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(EXCEL_COST_COLUMN);
        for ($r = EXCEL_HEADER_ROW + 1; $r <= $sheet->getHighestRow(); $r++) {
            $kode = trim((string)$sheet->getCell($kodeColumn.$r)->getFormattedValue());
            $paket = trim((string)$sheet->getCell($paketColumn.$r)->getFormattedValue());
            $biayaValue = $sheet->getCell($biayaColumn.$r)->getCalculatedValue();
            $biaya = is_numeric($biayaValue) ? (float)$biayaValue : 0;
            if ($kode !== '' && $paket !== '') $rows[] = ['kode' => $kode, 'paket' => $paket, 'biaya' => $biaya];
        }
        $totalRows = count($rows);
        if ($totalRows === 0) throw new Exception('Data tidak valid.');

        $db = getDB();
        if (!$db) throw new Exception('Database tidak tersedia.');

        // Database hanya menyimpan saved_name; lokasi lengkap selalu diturunkan dari UPLOAD_DIR.
        $savedName = generateUploadSavedName($db, $ext, $uploadTanggal);
        $targetPath = UPLOAD_DIR . $savedName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Gagal menyimpan.');
        }

        // Paket baru dari file upload otomatis didaftarkan ke master paket.
        $stmtPaket = $db->prepare("INSERT IGNORE INTO paket_master (nama) VALUES (?)");
        $paketUnik = [];
        foreach ($rows as $row) {
            $namaPaket = trim($row['paket']);
            if ($namaPaket !== '') $paketUnik[$namaPaket] = true;
        }
        foreach (array_keys($paketUnik) as $namaPaket) {
            $stmtPaket->bind_param('s', $namaPaket);
            $stmtPaket->execute();
        }
        $stmtPaket->close();

        $uploadedByUserId = authUserId();
        $stmt = $db->prepare("INSERT INTO uploaded_files
            (saved_name, total_rows, periode, tanggal, uploaded_by_user_id)
            VALUES (?,?,?,?,?)");
        $stmt->bind_param('sissi', $savedName, $totalRows, $uploadPeriode, $uploadTanggal, $uploadedByUserId);
        $stmt->execute();
        $fileId = $stmt->insert_id;
        $stmt->close();

        $stmtR = $db->prepare("INSERT INTO rincian (file_id, kode, awalan, paket, biaya) VALUES (?,?,?,?,?)");
        $summary = [];
        foreach ($rows as $row) {
            $kode = $row['kode'];
            $paket = $row['paket'];
            $biaya = $row['biaya'];
            $awalan = strtoupper(substr($kode, 0, 3));
            $stmtR->bind_param('isssd', $fileId, $kode, $awalan, $paket, $biaya);
            $stmtR->execute();
            $summary[$awalan][$paket] = ($summary[$awalan][$paket] ?? 0) + 1;
        }
        $stmtR->close();

        $stmtS = $db->prepare("INSERT INTO rekap (file_id, awalan, paket, jumlah) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah)");
        foreach ($summary as $awalan => $pakets) {
            foreach ($pakets as $paket => $jumlah) {
                $stmtS->bind_param('issi', $fileId, $awalan, $paket, $jumlah);
                $stmtS->execute();
            }
        }
        $stmtS->close();

        // Hitung invoice dari customer_paket_harga (hanya yg >0)
        $hargaCust = [];
        $resH = $db->query("SELECT awalan, paket, harga FROM customer_paket_harga");
        while ($h = $resH->fetch_assoc()) {
            $hargaCust[$h['awalan']][$h['paket']] = (float)$h['harga'];
        }

        $stmtInv = $db->prepare("INSERT INTO invoices (file_id, awalan, total_harga) VALUES (?,?,?) ON DUPLICATE KEY UPDATE total_harga = VALUES(total_harga)");
        foreach ($summary as $awalan => $pakets) {
            $total = 0;
            foreach ($pakets as $paket => $jumlah) {
                $harga = $hargaCust[$awalan][$paket] ?? 0;
                $total += $jumlah * $harga;
            }
            $stmtInv->bind_param('isd', $fileId, $awalan, $total);
            $stmtInv->execute();
        }
        $stmtInv->close();
        $db->close();

        echo json_encode([
            'success' => true,
            'file_id' => $fileId,
            'message' => "Berhasil upload {$totalRows} baris untuk periode {$uploadPeriode}, tanggal ".date('d-m-Y', strtotime($uploadTanggal)).". Arsip: {$savedName}"
        ]);
        exit;
    } catch (Exception $e) {
        if (isset($targetPath) && file_exists($targetPath)) unlink($targetPath);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// ===================== 2. TAMBAH PAKET MASTER =====================
if ($action === 'tambah_paket') {
    header('Content-Type: application/json');
    if (!authIsAdmin()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menambah paket master.']));
    }
    $namaPaket = trim($_POST['nama_paket'] ?? '');
    if ($namaPaket === '' || mb_strlen($namaPaket) > 100) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Nama paket wajib diisi dan maksimal 100 karakter.']));
    }

    $db = getDB();
    if (!$db) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database tidak tersedia.']));
    }
    $stmt = $db->prepare("INSERT IGNORE INTO paket_master (nama) VALUES (?)");
    $stmt->bind_param('s', $namaPaket);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("SELECT nama FROM paket_master WHERE nama = ? LIMIT 1");
    $stmt->bind_param('s', $namaPaket);
    $stmt->execute();
    $paket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    echo json_encode([
        'success' => true,
        'message' => 'Berhasil.',
        'paket' => $paket['nama'] ?? $namaPaket
    ]);
    exit;
}

// ===================== 3. TAMBAH/EDIT PELANGGAN =====================
if ($action === 'tambah_pelanggan') {
    $awalan = strtoupper(trim($_POST['awalan'] ?? ''));
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $billingMode = $_POST['billing_mode'] ?? 'existing';
    $billingId = isset($_POST['billing_id']) && $_POST['billing_id'] !== '' ? (int)$_POST['billing_id'] : null;
    $billingBaru = trim($_POST['billing_baru'] ?? '');
    $harga = $_POST['harga'] ?? [];

    if ($awalan === '' || $nama === '') {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Awalan dan Nama wajib diisi.']));
    }

    $db = getDB();
    if (!$db) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database tidak tersedia.']));
    }

    $stmtCurrent = $db->prepare("SELECT billing_id FROM prefix_customers WHERE awalan = ? LIMIT 1");
    $stmtCurrent->bind_param('s', $awalan);
    $stmtCurrent->execute();
    $currentCustomer = $stmtCurrent->get_result()->fetch_assoc();
    $stmtCurrent->close();
    if ($currentCustomer && !authIsAdmin()) {
        authRequireBilling((int)$currentCustomer['billing_id'], $db);
    }
    if ($billingMode === 'new' && !authIsAdmin()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Tidak memiliki izin membuat billing baru.']));
    }

    if ($billingMode === 'new') {
        if ($billingBaru === '' || mb_strlen($billingBaru) > 100) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Maksimal 100 karakter.']));
        }
        $stmtBilling = $db->prepare("INSERT IGNORE INTO billing_master (nama) VALUES (?)");
        $stmtBilling->bind_param('s', $billingBaru);
        $stmtBilling->execute();
        $stmtBilling->close();

        $stmtBilling = $db->prepare("SELECT id FROM billing_master WHERE nama = ? LIMIT 1");
        $stmtBilling->bind_param('s', $billingBaru);
        $stmtBilling->execute();
        $billingRow = $stmtBilling->get_result()->fetch_assoc();
        $billingId = $billingRow ? (int)$billingRow['id'] : null;
        $stmtBilling->close();
    } elseif ($billingId !== null) {
        $stmtBilling = $db->prepare("SELECT id FROM billing_master WHERE id = ? LIMIT 1");
        $stmtBilling->bind_param('i', $billingId);
        $stmtBilling->execute();
        if (!$stmtBilling->get_result()->fetch_assoc()) $billingId = null;
        $stmtBilling->close();
    }

    if (!authIsAdmin()) {
        if ($billingId === null) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Tidak memiliki izin untuk billing ini.']));
        }
        authRequireBilling((int)$billingId, $db);
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO prefix_customers (awalan, nama_pelanggan, alamat, telepon, billing_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nama_pelanggan=VALUES(nama_pelanggan), alamat=VALUES(alamat), telepon=VALUES(telepon), billing_id=VALUES(billing_id)");
        $stmt->bind_param('ssssi', $awalan, $nama, $alamat, $telepon, $billingId);
        $stmt->execute();
        $stmt->close();

        // Pelanggan baru langsung memperoleh akun pada tabel customer terpisah.
        $customerAccount = createCustomerAccount($db, $awalan, $nama);

    $db->query("DELETE FROM customer_paket_harga WHERE awalan = '".$db->real_escape_string($awalan)."'");
    if (!empty($harga)) {
        $stmtH = $db->prepare("INSERT INTO customer_paket_harga (awalan, paket, harga) VALUES (?,?,?)");
        foreach ($harga as $paket => $hrg) {
            $hrg = (float)$hrg;
            if ($hrg > 0 && $paket !== '') {
                $stmtH->bind_param('ssd', $awalan, $paket, $hrg);
                $stmtH->execute();
            }
        }
        $stmtH->close();
    }

    // Update ulang invoices untuk semua file yang ada
        $files = $db->query("SELECT DISTINCT file_id FROM rekap WHERE awalan = '".$db->real_escape_string($awalan)."'");
        while ($f = $files->fetch_assoc()) {
            $fileId = $f['file_id'];
            $pakets = $db->query("SELECT paket, jumlah FROM rekap WHERE file_id = $fileId AND awalan = '".$db->real_escape_string($awalan)."'");
            $total = 0;
            while ($p = $pakets->fetch_assoc()) {
                $hrg = $harga[$p['paket']] ?? 0;
                $total += (int)$p['jumlah'] * (float)$hrg;
            }
            $db->query("INSERT INTO invoices (file_id, awalan, total_harga) VALUES ($fileId, '".$db->real_escape_string($awalan)."', $total) ON DUPLICATE KEY UPDATE total_harga = $total");
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $db->close();
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Pelanggan dan akun gagal disimpan: '.$e->getMessage()]));
    }

    $db->close();
    $message = "Pelanggan $nama berhasil disimpan.";
    if (!empty($customerAccount['created'])) {
        $message .= " Akun pelanggan dibuat dengan username {$customerAccount['username']}.";
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'customer_account' => [
            'username' => $customerAccount['username'],
            'created' => (bool)$customerAccount['created']
        ]
    ]);
    exit;
}

// ===================== 4. GET CUSTOMER (JSON) =====================
if ($action === 'get_customer' && isset($_GET['awalan'])) {
    $awalan = trim($_GET['awalan']);
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM prefix_customers WHERE awalan = ?");
    $stmt->bind_param('s', $awalan);
    $stmt->execute();
    $cust = $stmt->get_result()->fetch_assoc();
    if (!$cust) die(json_encode(null));
    if (!authIsAdmin()) authRequireBilling((int)$cust['billing_id'], $db);

    $harga = [];
    $res = $db->query("SELECT paket, harga FROM customer_paket_harga WHERE awalan = '".$db->real_escape_string($awalan)."'");
    while ($h = $res->fetch_assoc()) $harga[$h['paket']] = (float)$h['harga'];
    $cust['harga'] = $harga;
    echo json_encode($cust);
    exit;
}

// ===================== 5. LIST CUSTOMERS (JSON) =====================
if ($action === 'list_customers') {
    $db = getDB();
    $res = $db->query("SELECT c.awalan, c.nama_pelanggan, c.alamat, c.telepon, c.billing_id,
            COALESCE(b.nama, '') AS billing
        FROM prefix_customers c
        LEFT JOIN billing_master b ON b.id = c.billing_id
        WHERE ".authBillingCondition('c.billing_id', $db)."
        ORDER BY c.nama_pelanggan ASC, c.awalan ASC");
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    echo json_encode($data);
    exit;
}
// ===================== 6. DOWNLOAD SEMUA INVOICE ) =====================
if ($action === 'download_all_invoices' && isset($_GET['file_id'])) {
    $fileId = (int)$_GET['file_id'];
    $viewMode = isset($_GET['view']);
    $saveDocument = isset($_GET['save_document']);
    $db = getDB();
    $uploadMetadata = getUploadMetadata($db, $fileId);
    $periodeRaw = $uploadMetadata['periode'];
    $periode = htmlspecialchars($periodeRaw, ENT_QUOTES, 'UTF-8');
    $tanggalInvoiceTimestamp = strtotime($uploadMetadata['tanggal']) ?: time();
    $tanggalPdf = date('d-m-Y', $tanggalInvoiceTimestamp);

    $billingName = '';
    $billingId = isset($_GET['billing_id']) && $_GET['billing_id'] !== '' ? (int)$_GET['billing_id'] : 0;
    if ($billingId > 0) {
        $stmtBilling = $db->prepare("SELECT nama FROM billing_master WHERE id = ? LIMIT 1");
        $stmtBilling->bind_param('i', $billingId);
        $stmtBilling->execute();
        $billingRow = $stmtBilling->get_result()->fetch_assoc();
        $stmtBilling->close();
        if (!$billingRow) {
            http_response_code(404);
            exit('Billing tidak ditemukan.');
        }
        authRequireBilling($billingId, $db);
        $billingName = $billingRow['nama'];
    }
    $billingCondition = reportBillingCondition($db, $billingId, 'c.billing_id');
    $allAwalan = [];
    $res = $db->query("SELECT DISTINCT r.awalan
        FROM rekap r
        LEFT JOIN prefix_customers c ON c.awalan = r.awalan
        WHERE r.file_id = $fileId AND {$billingCondition}
        ORDER BY r.awalan");
    while ($r = $res->fetch_assoc()) $allAwalan[] = $r['awalan'];
    if (empty($allAwalan)) die('Data belum tersedia.');

    $customers = [];
    $res = $db->query("SELECT c.*, COALESCE(b.nama, '-') AS billing_name
        FROM prefix_customers c
        LEFT JOIN billing_master b ON b.id = c.billing_id
        WHERE {$billingCondition}");
    while ($c = $res->fetch_assoc()) $customers[$c['awalan']] = $c;

    $invoices = [];
    $invRes = $db->query("SELECT awalan, total_harga FROM invoices WHERE file_id = $fileId");
    while ($inv = $invRes->fetch_assoc()) $invoices[$inv['awalan']] = (float)$inv['total_harga'];

    $html = '
        <!DOCTYPE html><html><head><meta charset="utf-8"><style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; }
        .page { width: 100%; height: 277mm; overflow: hidden; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .invoice-box { position: relative; width: 100%; height: 130mm; overflow: hidden; padding: 2.5mm 2mm 2mm; background: #fff; }
        .invoice-summary, .customer-table, .items-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .invoice-summary { margin-bottom: 3mm; background: #f1f4fa; border-left: 2.5px solid #2447c6; }
        .invoice-summary td { width: 25%; padding: 2.2mm 2.5mm; vertical-align: top; }
        .summary-label, .card-label { margin-bottom: .8mm; color: #5d6b82; font-size: 6.5px; font-weight: bold; letter-spacing: .35px; }
        .summary-value { color: #0f1f42; font-size: 8.5px; font-weight: bold; overflow-wrap: break-word; }
        .customer-table { margin-bottom: 3mm; }
        .customer-table td { width: 50%; padding: 0; vertical-align: top; }
        .customer-card { height: 28mm; overflow: hidden; padding: 3mm; border: 1px solid #d9e0ec; border-radius: 2px; }
        .customer-card.right { margin-left: 3mm; }
        .customer-name { margin-bottom: 1.4mm; color: #102a66; font-size: 10px; font-weight: bold; }
        .customer-detail { margin-top: 1mm; color: #4b5b73; line-height: 1.35; overflow-wrap: break-word; }
        .items-table { margin-bottom: 2.5mm; font-size: 7.5px; }
        .items-table th, .items-table td { padding: 1.7mm 2mm; border-bottom: 1px solid #d6deea; }
        .items-table th { color: #fff; background: #2447c6; font-size: 6.5px; font-weight: bold; letter-spacing: .2px; }
        .items-table th:first-child { width: 43%; }
        .items-table th:nth-child(2) { width: 11%; }
        .items-table th:nth-child(3), .items-table th:nth-child(4) { width: 23%; }
        .items-table tbody tr:nth-child(even) { background: #f6f8fb; }
        .items-table td:first-child { overflow-wrap: break-word; }
        .text-end { text-align: right; }
        .total-table { width: 68mm; margin: 0 0 0 auto; border-collapse: collapse; }
        .total-table td { padding: 2.4mm 3mm; color: #fff; background: #112d6b; }
        .total-label { font-size: 7px; font-weight: bold; }
        .total-value { font-size: 11px; font-weight: bold; text-align: right; white-space: nowrap; }
        .cut-divider { position: relative; width: 100%; height: 7mm; }
        .cut-rule { position: absolute; top: 3.5mm; right: 0; left: 0; border-top: 1px dashed #8f9bad; }
        .cut-scissors { position: absolute; top: 1.4mm; left: 4mm; z-index: 2; padding: 0 2mm; color: #667085; background: #fff; font-size: 10px; }
        .cut-label { position: absolute; top: 2mm; left: 50%; z-index: 2; width: 28mm; margin-left: -14mm; color: #7a8699; background: #fff; font-size: 6.5px; text-align: center; letter-spacing: .4px; }
        p { margin: 0; }
    </style></head><body>';

    $totalAwalan = count($allAwalan);
    $noUrut = 1;
    for ($i = 0; $i < $totalAwalan; $i++) {
        if ($i % 2 == 0) {
            $html .= '<div class="page">';
        }

        $awalan = $allAwalan[$i];
        $cust = $customers[$awalan] ?? null;
        $nama = $cust ? htmlspecialchars($cust['nama_pelanggan'], ENT_QUOTES, 'UTF-8') : 'N/A';
        $alamat = $cust && trim((string)$cust['alamat']) !== ''
            ? nl2br(htmlspecialchars($cust['alamat'], ENT_QUOTES, 'UTF-8'))
            : '-';
        $telepon = $cust && trim((string)$cust['telepon']) !== ''
            ? htmlspecialchars($cust['telepon'], ENT_QUOTES, 'UTF-8')
            : '-';
        $billingCustomer = $cust
            ? htmlspecialchars((string)($cust['billing_name'] ?: '-'), ENT_QUOTES, 'UTF-8')
            : '-';

        $hargaCust = [];
        $resH = $db->query("SELECT paket, harga FROM customer_paket_harga WHERE awalan = '".$db->real_escape_string($awalan)."'");
        while ($h = $resH->fetch_assoc()) $hargaCust[$h['paket']] = (float)$h['harga'];

        $rows = $db->query("SELECT r.paket, r.jumlah
            FROM rekap r
            LEFT JOIN paket_master pm ON pm.nama = r.paket
            WHERE r.file_id = $fileId AND r.awalan = '".$db->real_escape_string($awalan)."'
            ORDER BY pm.id ASC, r.paket ASC");
        $items = '';
        while ($row = $rows->fetch_assoc()) {
            $paketRaw = (string)$row['paket'];
            $paket = htmlspecialchars($paketRaw, ENT_QUOTES, 'UTF-8');
            $jumlah = (int)$row['jumlah'];
            $harga = $hargaCust[$paketRaw] ?? 0;
            $sub = $jumlah * $harga;
            $items .= "<tr><td>{$paket}</td><td class='text-end'>{$jumlah}</td><td class='text-end'>".number_format($harga,0,',','.')."</td><td class='text-end'>".number_format($sub,0,',','.')."</td></tr>";
        }

        $total = $invoices[$awalan] ?? 0;
        $noInvoice = 'INV-' . date('Ym', $tanggalInvoiceTimestamp) . str_pad($noUrut, 3, '0', STR_PAD_LEFT);

        $html .= '
        <div class="invoice-box">
            <table class="invoice-summary"><tr>
                <td><div class="summary-label">NOMOR INVOICE</div><div class="summary-value">'.$noInvoice.'</div></td>
                <td><div class="summary-label">TANGGAL</div><div class="summary-value">'.$tanggalPdf.'</div></td>
                <td><div class="summary-label">PERIODE</div><div class="summary-value">'.$periode.'</div></td>
                <td><div class="summary-label">STATUS</div><div class="summary-value">DITERBITKAN</div></td>
            </tr></table>
            <table class="customer-table"><tr>
                <td><div class="customer-card">
                    <div class="card-label">DITAGIHKAN KEPADA</div>
                    <div class="customer-name">'.$nama.'</div>
                    <div class="customer-detail">'.$alamat.'</div>
                    <div class="customer-detail">Tel: '.$telepon.'</div>
                </div></td>
                <td><div class="customer-card right">
                    <div class="card-label">INFORMASI PELANGGAN</div>
                    <div class="customer-detail"><strong>Awalan:</strong> '.htmlspecialchars($awalan, ENT_QUOTES, 'UTF-8').'</div>
                    <div class="customer-detail"><strong>Billing:</strong> '.$billingCustomer.'</div>
                    <div class="customer-detail"><strong>Periode layanan:</strong> '.$periode.'</div>
                </div></td>
            </tr></table>
            <table class="items-table">
                <thead><tr><th>PAKET</th><th class="text-end">QTY</th><th class="text-end">HARGA</th><th class="text-end">SUBTOTAL</th></tr></thead>
                <tbody>'.$items.'</tbody>
            </table>
            <table class="total-table"><tr>
                <td class="total-label">TOTAL TAGIHAN</td>
                <td class="total-value">Rp '.number_format($total,0,',','.').'</td>
            </tr></table>
        </div>';

        if ($i % 2 === 0 && $i + 1 < $totalAwalan) {
            $html .= '<div class="cut-divider"><div class="cut-rule"></div><span class="cut-scissors">&#9986;</span><span class="cut-label">POTONG DI SINI</span></div>';
        }

        if ($i % 2 == 1 || $i == $totalAwalan - 1) {
            $html .= '</div>';
        }
        $noUrut++;
    }

    $html .= '</body></html>';

    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    if ($saveDocument) {
        $pdfContent = $dompdf->output();
        $document = saveGeneratedDocument(
            $db, $fileId, 'PDF', 'pdf', $pdfContent, $billingId ?: null
        );
        if (isset($_GET['save_only'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'document' => $document]);
        } else {
            header('Content-Type: application/pdf');
            header('Content-Length: '.strlen($pdfContent));
            header('Content-Disposition: attachment; filename="'.$document['name'].'"');
            header('X-Document-Id: '.$document['id']);
            echo $pdfContent;
        }
    } elseif ($viewMode) {
        $dompdf->stream("Invoice.pdf.".date('d-m-Y'), ["Attachment" => false]);
    } else {
        $dompdf->stream("Invoice_".date('d-m-Y').".pdf", ["Attachment" => true]);
    }
    exit;
}


// ===================== 7. EXPORT REKAP EXCEL =====================
if ($action === 'export_rekap' && isset($_GET['file_id'])) {
    $fileId = (int)$_GET['file_id'];
    $saveDocument = isset($_GET['save_document']);
    $billingId = isset($_GET['billing_id']) && $_GET['billing_id'] !== '' ? (int)$_GET['billing_id'] : 0;
    $db = getDB();
    $uploadMetadata = getUploadMetadata($db, $fileId);
    $periode = $uploadMetadata['periode'];
    $tanggalTimestamp = strtotime($uploadMetadata['tanggal']) ?: time();
    $tanggal = date('d-m-Y', $tanggalTimestamp);

    $billingName = '';
    if ($billingId > 0) {
        $stmtBilling = $db->prepare("SELECT nama FROM billing_master WHERE id = ? LIMIT 1");
        $stmtBilling->bind_param('i', $billingId);
        $stmtBilling->execute();
        $billingRow = $stmtBilling->get_result()->fetch_assoc();
        $stmtBilling->close();
        if (!$billingRow) {
            http_response_code(404);
            exit('Billing tidak ditemukan.');
        }
        authRequireBilling($billingId, $db);
        $billingName = $billingRow['nama'];
    }
    $billingCondition = reportBillingCondition($db, $billingId, 'c.billing_id');

    // Semua paket mengikuti indeks (id) pada tabel master paket.
    $pakets = getPaketList($db);

    // Ambil data rekap + invoice + nama pelanggan
    $data = $db->query("
        SELECT r.awalan, r.paket, r.jumlah, 
               COALESCE(NULLIF(TRIM(c.nama_pelanggan), ''), '') AS nama,
               COALESCE(i.total_harga, 0) AS total_harga
        FROM rekap r
        LEFT JOIN prefix_customers c ON r.awalan = c.awalan
        LEFT JOIN invoices i ON i.file_id = r.file_id AND i.awalan = r.awalan
        WHERE r.file_id = $fileId AND {$billingCondition}
        ORDER BY nama ASC, r.awalan ASC, r.paket ASC
    ");

    // Susun data per awalan
    $rekapByAwalan = [];
    while ($row = $data->fetch_assoc()) {
        $awalan = $row['awalan'];
        if (!isset($rekapByAwalan[$awalan])) {
            $rekapByAwalan[$awalan] = [
                'nama' => $row['nama'],
                'paket_jumlah' => [],
                'total_harga' => (float)$row['total_harga']
            ];
        }
        $rekapByAwalan[$awalan]['paket_jumlah'][$row['paket']] = (int)$row['jumlah'];
    }

    // Total biaya berasal dari penjumlahan kolom biaya seluruh rincian file.
    $stmtBiaya = $db->prepare("SELECT COALESCE(SUM(ri.biaya), 0) AS total_biaya FROM rincian ri LEFT JOIN prefix_customers c ON c.awalan = ri.awalan WHERE ri.file_id = ? AND {$billingCondition}");
    $stmtBiaya->bind_param('i', $fileId);
    $stmtBiaya->execute();
    $biayaRow = $stmtBiaya->get_result()->fetch_assoc();
    $totalBiaya = (float)($biayaRow['total_biaya'] ?? 0);
    $stmtBiaya->close();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rekap data reseller');

    $totalColumnIndex = count($pakets) + 3;
    $totalColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumnIndex);
    $biayaLabelColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumnIndex - 1);

    // Judul dan metadata mengikuti tata letak contoh.
    $sheet->mergeCells("A1:{$totalColumn}2");
    $sheet->setCellValue('A1', 'Rekap data reseller');
    $sheet->setCellValue('A3', 'Billing :');
    $sheet->setCellValue('B3', $billingName);
    $sheet->setCellValue('A4', 'Periode :');
    $sheet->setCellValue('B4', $periode);
    $sheet->setCellValue('A5', 'Tanggal :');
    $sheet->setCellValue('B5', $tanggal);
    $sheet->setCellValue($biayaLabelColumn.'5', 'Biaya :');
    $sheet->setCellValue($totalColumn.'5', $totalBiaya);

    // Header tabel berada di baris 7 dan data dimulai dari baris 8.
    $col = 'A';
    $sheet->setCellValue($col++.'7', 'Nama Pelanggan');
    $sheet->setCellValue($col++.'7', 'Prefix');
    foreach ($pakets as $p) {
        $sheet->setCellValue($col++.'7', $p);
    }
    $sheet->setCellValue($totalColumn.'7', 'Total Tagihan');

    $rowNum = 8;
    foreach ($rekapByAwalan as $awalan => $info) {
        $col = 'A';
        $sheet->setCellValue($col++.$rowNum, $info['nama']);
        $sheet->setCellValue($col++.$rowNum, $awalan);
        foreach ($pakets as $p) {
            $jumlah = $info['paket_jumlah'][$p] ?? 0;
            $sheet->setCellValue($col++.$rowNum, $jumlah);
        }
        $sheet->setCellValue($totalColumn.$rowNum, $info['total_harga']);
        $rowNum++;
    }

    $lastRow = max(7, $rowNum - 1);
    $sheet->getStyle("A1:{$totalColumn}2")->applyFromArray([
        'font' => ['bold' => true, 'size' => 16],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'AFC6DD']
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ]
    ]);
    $sheet->getStyle('A3:A5')->getFont()->setBold(true);
    $sheet->getStyle('A3:A5')->getAlignment()->setHorizontal(
        \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
    );
    $sheet->getStyle($biayaLabelColumn.'5')->getFont()->setBold(true);
    $sheet->getStyle($biayaLabelColumn.'5')->getAlignment()->setHorizontal(
        \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
    );
    $sheet->getStyle($totalColumn.'5')->getFont()->setBold(true);
    $sheet->getStyle($totalColumn.'5')->getAlignment()->setHorizontal(
        \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
    );
    $sheet->getStyle($totalColumn.'5')->getNumberFormat()->setFormatCode('"Rp" #,##0');
    $sheet->getStyle("A7:{$totalColumn}7")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'AFC6DD']
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ]
    ]);
    $sheet->getStyle("A7:{$totalColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(
        \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
    );
    if ($lastRow >= 8) {
        $sheet->getStyle("B8:{$totalColumn}{$lastRow}")->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        );
        $sheet->getStyle("{$totalColumn}8:{$totalColumn}{$lastRow}")->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
        );
        $sheet->getStyle("{$totalColumn}8:{$totalColumn}{$lastRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
    }
    for ($columnIndex = 1; $columnIndex <= $totalColumnIndex; $columnIndex++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
    }
    $sheet->getRowDimension(1)->setRowHeight(22);
    $sheet->getRowDimension(2)->setRowHeight(22);
    $sheet->getRowDimension(7)->setRowHeight(24);
    $sheet->freezePane('A8');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    if ($saveDocument) {
        ob_start();
        $writer->save('php://output');
        $excelContent = ob_get_clean();
        $document = saveGeneratedDocument(
            $db, $fileId, 'EXCEL', 'xlsx', $excelContent, $billingId ?: null
        );
        if (isset($_GET['save_only'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'document' => $document]);
        } else {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Length: '.strlen($excelContent));
            header('Content-Disposition: attachment;filename="'.$document['name'].'"');
            header('X-Document-Id: '.$document['id']);
            echo $excelContent;
        }
    } else {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="rekap_'.$fileId.'.xlsx"');
        $writer->save('php://output');
    }
    exit;
}
