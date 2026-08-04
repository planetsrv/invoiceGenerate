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
    $documentType = strtoupper((string)$document['document_type']);
    $storageDirectory = $documentType === 'ZIP' ? ZIP_DIR : GENERATED_DIR;
    $filePath = $storageDirectory.basename($document['saved_name']);
    if (!is_file($filePath)) {
        http_response_code(404);
        exit('File dokumen tidak tersedia.');
    }

    $isPdf = $documentType === 'PDF';
    $contentType = match ($documentType) {
        'PDF' => 'application/pdf',
        'ZIP' => 'application/zip',
        default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    };
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
    $stmt = $db->prepare("SELECT id, file_id, document_type, original_name, saved_name, billing_id, generated_by_user_id
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
    if ((int)$documents['EXCEL']['file_id'] !== (int)$documents['PDF']['file_id']
        || (int)$documents['EXCEL']['billing_id'] !== (int)$documents['PDF']['billing_id']) {
        http_response_code(409);
        exit('Dokumen ZIP harus berasal dari file dan billing yang sama.');
    }
    foreach ($documents as $document) {
        if (!authCanAccessDocument($document, $db)) {
            http_response_code(403);
            exit('Anda tidak memiliki akses ke salah satu dokumen.');
        }
    }

    if (!is_dir(ZIP_DIR) && !mkdir(ZIP_DIR, 0755, true) && !is_dir(ZIP_DIR)) {
        http_response_code(500);
        exit('Folder ZIP tidak dapat dibuat.');
    }
    $zipPath = tempnam(sys_get_temp_dir(), 'invoice_bundle_');
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

    $zipContent = file_get_contents($zipPath);
    @unlink($zipPath);
    if ($zipContent === false) {
        http_response_code(500);
        exit('ZIP gagal dibaca setelah dibuat.');
    }
    $zipDocument = saveGeneratedDocument(
        $db,
        (int)$documents['PDF']['file_id'],
        'ZIP',
        'zip',
        $zipContent,
        (int)$documents['PDF']['billing_id'] ?: null
    );

    $zipName = $zipDocument['name'];
    header('Content-Type: application/zip');
    header('Content-Length: '.strlen($zipContent));
    header('Content-Disposition: attachment; filename="'.$zipName.'"');
    header('X-Document-Id: '.$zipDocument['id']);
    echo $zipContent;
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

        // Database hanya menyimpan hasil ringkas. Kode voucher lengkap tetap ada
        // pada file upload sehingga tabel tidak terus membesar setiap periode.
        $summary = [];
        foreach ($rows as $row) {
            $kode = $row['kode'];
            $paket = $row['paket'];
            $biaya = $row['biaya'];
            $prefix = strtoupper(substr($kode, 0, 3));
            if (!isset($summary[$prefix][$paket])) {
                $summary[$prefix][$paket] = ['jumlah' => 0, 'total_biaya' => 0.0];
            }
            $summary[$prefix][$paket]['jumlah']++;
            $summary[$prefix][$paket]['total_biaya'] += $biaya;
        }

        $stmtS = $db->prepare("INSERT INTO rekap (file_id, prefix, paket, jumlah, total_biaya)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah), total_biaya = VALUES(total_biaya)");
        foreach ($summary as $prefix => $pakets) {
            foreach ($pakets as $paket => $packageSummary) {
                $jumlah = (int)$packageSummary['jumlah'];
                $totalBiaya = (float)$packageSummary['total_biaya'];
                $stmtS->bind_param('issid', $fileId, $prefix, $paket, $jumlah, $totalBiaya);
                $stmtS->execute();
            }
        }
        $stmtS->close();

        // Hitung invoice dari customer_paket_harga (hanya yg >0)
        $hargaCust = [];
        $resH = $db->query("SELECT prefix, paket, harga FROM customer_paket_harga");
        while ($h = $resH->fetch_assoc()) {
            $hargaCust[$h['prefix']][$h['paket']] = (float)$h['harga'];
        }

        $stmtInv = $db->prepare("INSERT INTO invoices (file_id, prefix, total_harga) VALUES (?,?,?) ON DUPLICATE KEY UPDATE total_harga = VALUES(total_harga)");
        foreach ($summary as $prefix => $pakets) {
            $total = 0;
            foreach ($pakets as $paket => $packageSummary) {
                $jumlah = (int)$packageSummary['jumlah'];
                $harga = $hargaCust[$prefix][$paket] ?? 0;
                $total += $jumlah * $harga;
            }
            $stmtInv->bind_param('isd', $fileId, $prefix, $total);
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

// ===================== KELOLA PAKET MASTER =====================
if ($action === 'list_packages') {
    header('Content-Type: application/json');
    $db = getDB();
    if (authIsAdmin()) {
        $result = $db->query("SELECT p.id, p.nama,
            (SELECT COUNT(*) FROM customer_paket_harga h WHERE h.paket = p.nama) AS customer_count,
            (SELECT COUNT(*) FROM rekap r WHERE r.paket = p.nama) AS usage_count
            FROM paket_master p ORDER BY p.id ASC");
    } else {
        // Nama paket boleh dilihat user, tetapi angka penggunaan hanya dihitung
        // dari pelanggan pada billing yang diizinkan agar statistik tidak bocor.
        $customerAccess = authBillingCondition('package_customer.billing_id', $db);
        $usageAccess = authBillingCondition('usage_customer.billing_id', $db);
        $visibleAccess = authBillingCondition('visible_customer.billing_id', $db);
        $result = $db->query("SELECT p.id, p.nama,
            (SELECT COUNT(*) FROM customer_paket_harga h
                INNER JOIN prefix_customers package_customer ON package_customer.prefix = h.prefix
                WHERE h.paket = p.nama AND {$customerAccess}) AS customer_count,
            (SELECT COUNT(*) FROM rekap r
                INNER JOIN prefix_customers usage_customer ON usage_customer.prefix = r.prefix
                WHERE r.paket = p.nama AND {$usageAccess}) AS usage_count
            FROM paket_master p
            WHERE EXISTS (SELECT 1 FROM customer_paket_harga visible_package
                INNER JOIN prefix_customers visible_customer ON visible_customer.prefix = visible_package.prefix
                WHERE visible_package.paket = p.nama AND {$visibleAccess})
            ORDER BY p.id ASC");
    }
    $packages = [];
    while ($row = $result->fetch_assoc()) {
        $packages[] = [
            'id' => (int)$row['id'],
            'nama' => $row['nama'],
            'customer_count' => (int)$row['customer_count'],
            'usage_count' => (int)$row['usage_count'],
        ];
    }
    echo json_encode([
        'success' => true,
        'can_manage' => authIsAdmin(),
        'packages' => $packages,
    ]);
    exit;
}

if ($action === 'update_package') {
    header('Content-Type: application/json');
    if (!authIsAdmin()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Hanya admin yang dapat mengubah paket.']));
    }

    $packageId = (int)($_POST['package_id'] ?? 0);
    $newName = trim((string)($_POST['nama_paket'] ?? ''));
    if ($packageId < 1 || $newName === '' || mb_strlen($newName) > 100) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Nama paket wajib diisi dan maksimal 100 karakter.']));
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT nama FROM paket_master WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $package = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$package) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Paket tidak ditemukan.']));
    }

    $oldName = (string)$package['nama'];
    $stmt = $db->prepare("SELECT id FROM paket_master WHERE nama = ? AND id <> ? LIMIT 1");
    $stmt->bind_param('si', $newName, $packageId);
    $stmt->execute();
    $duplicate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($duplicate) {
        http_response_code(409);
        die(json_encode(['success' => false, 'message' => 'Nama paket tersebut sudah digunakan.']));
    }

    $db->begin_transaction();
    try {
        foreach (['customer_paket_harga', 'rincian', 'rekap'] as $table) {
            $stmt = $db->prepare("UPDATE `{$table}` SET paket = ? WHERE paket = ?");
            $stmt->bind_param('ss', $newName, $oldName);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $db->prepare("UPDATE paket_master SET nama = ? WHERE id = ?");
        $stmt->bind_param('si', $newName, $packageId);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollback();
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Paket gagal diubah: '.$exception->getMessage()]));
    }

    echo json_encode(['success' => true, 'message' => 'Paket berhasil diubah.', 'package' => ['id' => $packageId, 'nama' => $newName]]);
    exit;
}

if ($action === 'delete_package') {
    header('Content-Type: application/json');
    if (!authIsAdmin()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menghapus paket.']));
    }

    $packageId = (int)($_POST['package_id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("SELECT nama FROM paket_master WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $package = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$package) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Paket tidak ditemukan.']));
    }

    $packageName = (string)$package['nama'];
    $stmt = $db->prepare("SELECT
        (SELECT COUNT(*) FROM rekap WHERE paket = ?) +
        (SELECT COUNT(*) FROM rincian WHERE paket = ?) AS total_usage");
    $stmt->bind_param('ss', $packageName, $packageName);
    $stmt->execute();
    $usage = (int)($stmt->get_result()->fetch_assoc()['total_usage'] ?? 0);
    $stmt->close();
    if ($usage > 0) {
        http_response_code(409);
        die(json_encode(['success' => false, 'message' => 'Paket tidak dapat dihapus karena sudah digunakan oleh data upload.']));
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("DELETE FROM customer_paket_harga WHERE paket = ?");
        $stmt->bind_param('s', $packageName);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare("DELETE FROM paket_master WHERE id = ?");
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollback();
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Paket gagal dihapus.']));
    }

    echo json_encode(['success' => true, 'message' => 'Paket berhasil dihapus.']);
    exit;
}

// ===================== 3. TAMBAH/EDIT PELANGGAN =====================
if ($action === 'tambah_pelanggan') {
    $prefix = strtoupper(trim($_POST['prefix'] ?? ''));
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $billingMode = $_POST['billing_mode'] ?? 'existing';
    $billingId = isset($_POST['billing_id']) && $_POST['billing_id'] !== '' ? (int)$_POST['billing_id'] : null;
    $billingBaru = trim($_POST['billing_baru'] ?? '');
    $harga = $_POST['harga'] ?? [];

    if ($prefix === '' || $nama === '') {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Prefix dan Nama wajib diisi.']));
    }

    $db = getDB();
    if (!$db) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database tidak tersedia.']));
    }

    $stmtCurrent = $db->prepare("SELECT billing_id FROM prefix_customers WHERE prefix = ? LIMIT 1");
    $stmtCurrent->bind_param('s', $prefix);
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
        $stmt = $db->prepare("INSERT INTO prefix_customers (prefix, nama_pelanggan, alamat, telepon, billing_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nama_pelanggan=VALUES(nama_pelanggan), alamat=VALUES(alamat), telepon=VALUES(telepon), billing_id=VALUES(billing_id)");
        $stmt->bind_param('ssssi', $prefix, $nama, $alamat, $telepon, $billingId);
        $stmt->execute();
        $stmt->close();

        // Pelanggan baru langsung memperoleh akun pada tabel customer terpisah.
        $customerAccount = createCustomerAccount($db, $prefix, $nama);

    $db->query("DELETE FROM customer_paket_harga WHERE prefix = '".$db->real_escape_string($prefix)."'");
    if (!empty($harga)) {
        $stmtH = $db->prepare("INSERT INTO customer_paket_harga (prefix, paket, harga) VALUES (?,?,?)");
        foreach ($harga as $paket => $hrg) {
            $hrg = (float)$hrg;
            if ($hrg > 0 && $paket !== '') {
                $stmtH->bind_param('ssd', $prefix, $paket, $hrg);
                $stmtH->execute();
            }
        }
        $stmtH->close();
    }

    // Update ulang invoices untuk semua file yang ada
        $files = $db->query("SELECT DISTINCT file_id FROM rekap WHERE prefix = '".$db->real_escape_string($prefix)."'");
        while ($f = $files->fetch_assoc()) {
            $fileId = $f['file_id'];
            $pakets = $db->query("SELECT paket, jumlah FROM rekap WHERE file_id = $fileId AND prefix = '".$db->real_escape_string($prefix)."'");
            $total = 0;
            while ($p = $pakets->fetch_assoc()) {
                $hrg = $harga[$p['paket']] ?? 0;
                $total += (int)$p['jumlah'] * (float)$hrg;
            }
            $db->query("INSERT INTO invoices (file_id, prefix, total_harga) VALUES ($fileId, '".$db->real_escape_string($prefix)."', $total) ON DUPLICATE KEY UPDATE total_harga = $total");
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
if ($action === 'get_customer' && isset($_GET['prefix'])) {
    $prefix = trim($_GET['prefix']);
    $db = getDB();
    // Filter billing dimasukkan langsung ke query agar pelanggan di luar izin
    // tidak dapat ditebak keberadaannya melalui manipulasi parameter prefix.
    $customerAccess = authIsAdmin() ? '1=1' : authBillingCondition('c.billing_id', $db);
    $stmt = $db->prepare("SELECT c.* FROM prefix_customers c
        WHERE c.prefix = ? AND {$customerAccess} LIMIT 1");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $cust = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cust) {
        http_response_code(404);
        die(json_encode(null));
    }

    $harga = [];
    $res = $db->query("SELECT paket, harga FROM customer_paket_harga WHERE prefix = '".$db->real_escape_string($prefix)."'");
    while ($h = $res->fetch_assoc()) $harga[$h['paket']] = (float)$h['harga'];
    $cust['harga'] = $harga;
    echo json_encode($cust);
    exit;
}

// ===================== 5. CUSTOMER BILLING GROUPS (JSON) =====================
if ($action === 'list_customer_billings') {
    header('Content-Type: application/json; charset=utf-8');
    $db = getDB();
    $customerSearch = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
    $customerWhere = authBillingCondition('c.billing_id', $db);
    $searchSql = $customerSearch !== '' ? ' AND c.nama_pelanggan LIKE ?' : '';
    $searchPattern = '%'.$customerSearch.'%';
    $stmt = $db->prepare("SELECT COALESCE(c.billing_id, 0) AS billing_id,
            COALESCE(NULLIF(TRIM(b.nama), ''), 'Tanpa billing') AS billing,
            COUNT(*) AS customer_count
        FROM prefix_customers c
        LEFT JOIN billing_master b ON b.id = c.billing_id
        WHERE {$customerWhere}{$searchSql}
        GROUP BY c.billing_id, b.nama
        ORDER BY billing ASC");
    if ($customerSearch !== '') $stmt->bind_param('s', $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $billings = [];
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $count = (int)$row['customer_count'];
        $total += $count;
        $billings[] = [
            'id' => (int)$row['billing_id'],
            'name' => (string)$row['billing'],
            'customer_count' => $count,
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'billings' => $billings, 'total' => $total]);
    exit;
}

// ===================== 6. LIST CUSTOMERS (JSON) =====================
if ($action === 'list_customers') {
    header('Content-Type: application/json; charset=utf-8');
    $db = getDB();
    $customerPage = max(1, (int)($_GET['customer_page'] ?? 1));
    $customerPageSize = 25;
    $customerSearch = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
    $customerWhere = authBillingCondition('c.billing_id', $db);
    if (array_key_exists('billing_id', $_GET)) {
        $requestedBillingId = (int)$_GET['billing_id'];
        $customerWhere .= $requestedBillingId > 0
            ? ' AND c.billing_id = '.$requestedBillingId
            : ' AND c.billing_id IS NULL';
    }
    $searchSql = $customerSearch !== '' ? ' AND c.nama_pelanggan LIKE ?' : '';
    $searchPattern = '%'.$customerSearch.'%';

    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM prefix_customers c
        WHERE {$customerWhere}{$searchSql}");
    if ($customerSearch !== '') $stmt->bind_param('s', $searchPattern);
    $stmt->execute();
    $totalCustomers = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    $customerTotalPages = max(1, (int)ceil($totalCustomers / $customerPageSize));
    $customerPage = min($customerPage, $customerTotalPages);
    $customerOffset = ($customerPage - 1) * $customerPageSize;
    $stmt = $db->prepare("SELECT c.prefix, c.nama_pelanggan, c.alamat, c.telepon, c.billing_id,
            COALESCE(b.nama, '') AS billing,
            COALESCE(a.username, '') AS account_username,
            COALESCE(a.password, '') AS account_password,
            COALESCE(a.is_active, 0) AS account_is_active,
            a.created_at AS account_created_at,
            (SELECT COUNT(*) FROM customer_paket_harga h WHERE h.prefix = c.prefix) AS package_count
        FROM prefix_customers c
        LEFT JOIN billing_master b ON b.id = c.billing_id
        LEFT JOIN customer_accounts a ON a.customer_prefix = c.prefix
        WHERE {$customerWhere}{$searchSql}
        ORDER BY c.nama_pelanggan ASC, c.prefix ASC
        LIMIT ?, ?");
    if ($customerSearch !== '') {
        $stmt->bind_param('sii', $searchPattern, $customerOffset, $customerPageSize);
    } else {
        $stmt->bind_param('ii', $customerOffset, $customerPageSize);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;
    $stmt->close();
    echo json_encode([
        'success' => true,
        'customers' => $data,
        'page' => $customerPage,
        'total_pages' => $customerTotalPages,
        'total' => $totalCustomers,
        'query' => $customerSearch,
    ]);
    exit;
}

// Menghapus profil dan akun customer tanpa menghapus data voucher mentah.
if ($action === 'delete_customer') {
    header('Content-Type: application/json');
    $prefix = strtoupper(trim((string)($_POST['prefix'] ?? '')));
    if ($prefix === '') {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Prefix pelanggan tidak valid.']));
    }

    $db = getDB();
    $deleteAccess = authIsAdmin() ? '1=1' : authBillingCondition('c.billing_id', $db);
    $stmt = $db->prepare("SELECT c.nama_pelanggan, c.billing_id FROM prefix_customers c
        WHERE c.prefix = ? AND {$deleteAccess} LIMIT 1");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$customer) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Pelanggan tidak ditemukan.']));
    }
    $db->begin_transaction();
    try {
        foreach (['customer_paket_harga', 'invoices', 'customer_accounts', 'prefix_customers'] as $table) {
            $column = $table === 'customer_accounts' ? 'customer_prefix' : 'prefix';
            $stmt = $db->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
            $stmt->bind_param('s', $prefix);
            $stmt->execute();
            $stmt->close();
        }
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollback();
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Pelanggan gagal dihapus.']));
    }

    echo json_encode(['success' => true, 'message' => 'Pelanggan '.$customer['nama_pelanggan'].' berhasil dihapus.']);
    exit;
}
// ===================== 6. DOWNLOAD SEMUA INVOICE ) =====================
if ($action === 'download_all_invoices' && isset($_GET['file_id'])) {
    $fileId = (int)$_GET['file_id'];
    $viewMode = isset($_GET['view']);
    $saveDocument = isset($_GET['save_document']);
    $db = getDB();
    // Cegah file ID milik billing lain dibuka dengan URL yang dimanipulasi.
    authRequireUpload($fileId, $db);
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
    $allPrefix = [];
    $res = $db->query("SELECT DISTINCT r.prefix
        FROM rekap r
        LEFT JOIN prefix_customers c ON c.prefix = r.prefix
        WHERE r.file_id = $fileId AND {$billingCondition}
        ORDER BY r.prefix");
    while ($r = $res->fetch_assoc()) $allPrefix[] = $r['prefix'];
    if (empty($allPrefix)) die('Data belum tersedia.');

    $customers = [];
    $res = $db->query("SELECT c.*, COALESCE(b.nama, '-') AS billing_name
        FROM prefix_customers c
        LEFT JOIN billing_master b ON b.id = c.billing_id
        WHERE {$billingCondition}");
    while ($c = $res->fetch_assoc()) $customers[$c['prefix']] = $c;

    $invoices = [];
    $invRes = $db->query("SELECT prefix, total_harga FROM invoices WHERE file_id = $fileId");
    while ($inv = $invRes->fetch_assoc()) $invoices[$inv['prefix']] = (float)$inv['total_harga'];

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

    $totalPrefix = count($allPrefix);
    $noUrut = 1;
    for ($i = 0; $i < $totalPrefix; $i++) {
        if ($i % 2 == 0) {
            $html .= '<div class="page">';
        }

        $prefix = $allPrefix[$i];
        $cust = $customers[$prefix] ?? null;
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
        $resH = $db->query("SELECT paket, harga FROM customer_paket_harga WHERE prefix = '".$db->real_escape_string($prefix)."'");
        while ($h = $resH->fetch_assoc()) $hargaCust[$h['paket']] = (float)$h['harga'];

        $rows = $db->query("SELECT r.paket, r.jumlah
            FROM rekap r
            LEFT JOIN paket_master pm ON pm.nama = r.paket
            WHERE r.file_id = $fileId AND r.prefix = '".$db->real_escape_string($prefix)."'
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

        $total = $invoices[$prefix] ?? 0;
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
                    <div class="customer-detail">Tlp: '.$telepon.'</div>
                </div></td>
                <td><div class="customer-card right">
                    <div class="card-label">INFORMASI PELANGGAN</div>
                    <div class="customer-detail"><strong>Prefix:</strong> '.htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8').'</div>
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

        if ($i % 2 === 0 && $i + 1 < $totalPrefix) {
            $html .= '<div class="cut-divider"><div class="cut-rule"></div><span class="cut-scissors">&#9986;</span><span class="cut-label">POTONG DI SINI</span></div>';
        }

        if ($i % 2 == 1 || $i == $totalPrefix - 1) {
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
    // Pemeriksaan dilakukan sebelum metadata maupun isi arsip dibaca.
    authRequireUpload($fileId, $db);
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
        SELECT r.prefix, r.paket, r.jumlah, 
               COALESCE(NULLIF(TRIM(c.nama_pelanggan), ''), '') AS nama,
               COALESCE(i.total_harga, 0) AS total_harga
        FROM rekap r
        LEFT JOIN prefix_customers c ON r.prefix = c.prefix
        LEFT JOIN invoices i ON i.file_id = r.file_id AND i.prefix = r.prefix
        WHERE r.file_id = $fileId AND {$billingCondition}
        ORDER BY nama ASC, r.prefix ASC, r.paket ASC
    ");

    // Susun data per prefix
    $rekapByPrefix = [];
    while ($row = $data->fetch_assoc()) {
        $prefix = $row['prefix'];
        if (!isset($rekapByPrefix[$prefix])) {
            $rekapByPrefix[$prefix] = [
                'nama' => $row['nama'],
                'paket_jumlah' => [],
                'total_harga' => (float)$row['total_harga']
            ];
        }
        $rekapByPrefix[$prefix]['paket_jumlah'][$row['paket']] = (int)$row['jumlah'];
    }

    // Total biaya sudah dipadatkan per rekap; rincian lengkap berada di file asli.
    $stmtBiaya = $db->prepare("SELECT COALESCE(SUM(r.total_biaya), 0) AS total_biaya
        FROM rekap r
        LEFT JOIN prefix_customers c ON c.prefix = r.prefix
        WHERE r.file_id = ? AND {$billingCondition}");
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
    foreach ($rekapByPrefix as $prefix => $info) {
        $col = 'A';
        $sheet->setCellValue($col++.$rowNum, $info['nama']);
        $sheet->setCellValue($col++.$rowNum, $prefix);
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
