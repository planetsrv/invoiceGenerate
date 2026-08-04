<?php

// ===================== FUNGSI INDEX.PHP=====================
function getDB(): ?mysqli {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) $db = null;
        else $db->set_charset('utf8mb4');
    }
    return $db;
}

function ensureDatabase(): void {
    static $done = false;
    if ($done) return;

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) die('Database connection failed: ' . $conn->connect_error);
    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);

    $conn->query("CREATE TABLE IF NOT EXISTS `uploaded_files` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `saved_name` VARCHAR(100) NOT NULL UNIQUE,
        `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
        `periode` VARCHAR(100) NOT NULL,
        `tanggal` DATE NOT NULL,
        `uploaded_by_user_id` INT NOT NULL,
        `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_uploaded_date` (`tanggal`, `id`),
        INDEX `idx_uploaded_created` (`uploaded_at`, `id`),
        INDEX `idx_uploaded_user` (`uploaded_by_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `rincian` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `file_id` INT NOT NULL DEFAULT 1,
        `kode` VARCHAR(100) NOT NULL,
        `prefix` VARCHAR(10) NOT NULL,
        `paket` VARCHAR(100) NOT NULL,
        `biaya` DECIMAL(15,2) NOT NULL DEFAULT 0,
        INDEX `idx_file_id` (`file_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `rekap` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `file_id` INT NOT NULL DEFAULT 1,
        `prefix` VARCHAR(10) NOT NULL,
        `paket` VARCHAR(100) NOT NULL,
        `jumlah` INT NOT NULL DEFAULT 0,
        `total_biaya` DECIMAL(15,2) NOT NULL DEFAULT 0,
        UNIQUE KEY `uk_file_prefix_paket` (`file_id`, `prefix`, `paket`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrasi satu kali: padatkan rincian lama menjadi total biaya per rekap.
    // Setelah diringkas, kode voucher lengkap tetap tersedia pada file upload asli.
    $totalBiayaColumn = $conn->query("SHOW COLUMNS FROM `rekap` LIKE 'total_biaya'");
    if ($totalBiayaColumn && $totalBiayaColumn->num_rows === 0) {
        $conn->query("ALTER TABLE `rekap` ADD COLUMN `total_biaya` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `jumlah`");
        $conn->query("UPDATE rekap r
            LEFT JOIN (
                SELECT file_id, prefix, paket, SUM(biaya) AS total_biaya
                FROM rincian
                GROUP BY file_id, prefix, paket
            ) detail ON detail.file_id = r.file_id AND detail.prefix = r.prefix AND detail.paket = r.paket
            SET r.total_biaya = COALESCE(detail.total_biaya, 0)");
        $conn->query("DELETE FROM rincian");
        // Kembalikan ruang tabel setelah rincian lama selesai dipadatkan.
        $conn->query("OPTIMIZE TABLE rincian");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS `prefix_customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `prefix` VARCHAR(10) NOT NULL UNIQUE,
        `nama_pelanggan` VARCHAR(255) NOT NULL,
        `alamat` TEXT,
        `telepon` VARCHAR(20),
        `billing_id` INT NULL,
        INDEX `idx_billing_id` (`billing_id`),
        INDEX `idx_customer_name` (`nama_pelanggan`, `prefix`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `customer_paket_harga` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `prefix` VARCHAR(10) NOT NULL,
        `paket` VARCHAR(100) NOT NULL,
        `harga` DECIMAL(12,2) NOT NULL DEFAULT 0,
        UNIQUE KEY `uk_cust_paket` (`prefix`, `paket`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `paket_master` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(100) NOT NULL UNIQUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `billing_master` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(100) NOT NULL UNIQUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `generated_documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `file_id` INT NOT NULL,
        `document_type` VARCHAR(10) NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `saved_name` VARCHAR(100) NOT NULL UNIQUE,
        `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
        `billing_id` INT NULL,
        `generated_by_user_id` INT NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_generated_file_id` (`file_id`),
        INDEX `idx_generated_created_at` (`created_at`),
        INDEX `idx_generated_billing` (`billing_id`),
        INDEX `idx_generated_user` (`generated_by_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `invoices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `file_id` INT NOT NULL,
        `prefix` VARCHAR(10) NOT NULL,
        `total_harga` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_file_prefix` (`file_id`, `prefix`),
        INDEX `idx_invoice_customer` (`prefix`, `file_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Indeks untuk database yang dibuat sebelum optimasi pagination.
    $uploadedCreatedIndex = $conn->query("SHOW INDEX FROM uploaded_files WHERE Key_name = 'idx_uploaded_created'");
    if ($uploadedCreatedIndex && $uploadedCreatedIndex->num_rows === 0) {
        $conn->query("ALTER TABLE uploaded_files ADD INDEX idx_uploaded_created (uploaded_at, id)");
    }
    $customerNameIndex = $conn->query("SHOW INDEX FROM prefix_customers WHERE Key_name = 'idx_customer_name'");
    if ($customerNameIndex && $customerNameIndex->num_rows === 0) {
        $conn->query("ALTER TABLE prefix_customers ADD INDEX idx_customer_name (nama_pelanggan, prefix)");
    }

    $conn->close();
    $done = true;
}

function getPaketList(mysqli $db): array {
    $paketList = [];
    if (authIsAdmin()) {
        $res = $db->query("SELECT nama FROM paket_master ORDER BY id ASC");
    } else {
        // User hanya melihat paket yang dimiliki pelanggan dalam billing izinnya.
        $packageAccess = authBillingCondition('package_customer.billing_id', $db);
        $res = $db->query("SELECT DISTINCT p.id, p.nama
            FROM paket_master p
            INNER JOIN customer_paket_harga h ON h.paket = p.nama
            INNER JOIN prefix_customers package_customer ON package_customer.prefix = h.prefix
            WHERE {$packageAccess}
            ORDER BY p.id ASC");
    }
    while ($res && $row = $res->fetch_assoc()) $paketList[] = $row['nama'];
    return $paketList;
}

function getBillingList(mysqli $db): array {
    $billingList = [];
    $where = authIsAdmin() ? '1=1' : authBillingCondition('id', $db);
    $res = $db->query("SELECT id, nama FROM billing_master WHERE {$where} ORDER BY nama ASC");
    while ($res && $row = $res->fetch_assoc()) $billingList[] = $row;
    return $billingList;
}

function reportBillingCondition(mysqli $db, int $billingId, string $column = 'c.billing_id'): string {
    if ($billingId > 0) {
        authRequireBilling($billingId, $db);
        return $column.' = '.(int)$billingId;
    }
    return authIsAdmin() ? '1=1' : authBillingCondition($column, $db);
}

function formatBytes(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2, ',', '.').' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.').' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1, ',', '.').' KB';
    return $bytes.' B';
}

function groupRowsByDate(array $rows, string $dateField): array {
    $groups = [];
    foreach ($rows as $row) {
        $dateKey = substr((string)($row[$dateField] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) $dateKey = 'unknown';
        $groups[$dateKey][] = $row;
    }
    return $groups;
}

function formatDateGroup(string $dateKey): string {
    if ($dateKey === 'unknown') return 'Tanggal tidak diketahui';
    $date = DateTime::createFromFormat('!Y-m-d', $dateKey);
    if (!$date) return $dateKey;
    $days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $months = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $days[(int)$date->format('w')].', '.$date->format('j').' '.$months[(int)$date->format('n')].' '.$date->format('Y');
}

function paginateDateGroups(array $groups, int $requestedPage): array {
    $totalPages = max(1, (int)ceil(count($groups) / DATE_GROUPS_PER_PAGE));
    $page = min(max(1, $requestedPage), $totalPages);
    $offset = ($page - 1) * DATE_GROUPS_PER_PAGE;
    return [array_slice($groups, $offset, DATE_GROUPS_PER_PAGE, true), $page, $totalPages];
}

function paginationPageItems(int $currentPage, int $totalPages): array {
    if ($totalPages <= 7) return range(1, $totalPages);
    $pages = [1, $totalPages];
    for ($page = $currentPage - 2; $page <= $currentPage + 2; $page++) {
        if ($page > 1 && $page < $totalPages) $pages[] = $page;
    }
    $pages = array_values(array_unique($pages));
    sort($pages);
    $items = [];
    $previous = 0;
    foreach ($pages as $page) {
        if ($previous > 0 && $page - $previous > 1) $items[] = null;
        $items[] = $page;
        $previous = $page;
    }
    return $items;
}

function generateUploadSavedName(mysqli $db, string $extension, string $uploadDate): string {
    $stmt = $db->prepare("SELECT id FROM uploaded_files WHERE saved_name = ? LIMIT 1");
    for ($attempt = 0; $attempt < 200; $attempt++) {
        // Nama fisik upload: upload_4angka_tanggal-bulan-tahun.extensi.
        $savedName = 'upload_'.random_int(1000, 9999).'_'.date('d-m-Y', strtotime($uploadDate)).'.'.$extension;
        $stmt->bind_param('s', $savedName);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $stmt->close();
            return $savedName;
        }
    }
    $stmt->close();
    throw new RuntimeException('Nama file upload unik gagal dibuat. Silakan coba kembali.');
}

function saveGeneratedDocument(
    mysqli $db,
    int $fileId,
    string $type,
    string $extension,
    string $content,
    ?int $billingId = null
): array {
    $type = strtoupper($type);
    $storageDirectory = $type === 'ZIP' ? ZIP_DIR : GENERATED_DIR;
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0755, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('Folder penyimpanan dokumen tidak dapat dibuat.');
    }

    $stmtName = $db->prepare("SELECT id FROM generated_documents WHERE saved_name = ? LIMIT 1");
    $savedName = '';
    for ($attempt = 0; $attempt < 200; $attempt++) {
        // Nama fisik dokumen mengikuti jenis, 4 angka acak, dan tanggal pembuatan.
        $candidate = strtolower($type).'_'.random_int(1000, 9999).'_'.date('d-m-Y').'.'.$extension;
        $stmtName->bind_param('s', $candidate);
        $stmtName->execute();
        if (!$stmtName->get_result()->fetch_assoc()) {
            $savedName = $candidate;
            break;
        }
    }
    $stmtName->close();
    if ($savedName === '') throw new RuntimeException('Nama dokumen unik gagal dibuat. Silakan coba kembali.');

    // original_name hanya menjadi nama ramah pengguna saat dokumen ditampilkan,
    // diunduh, atau dimasukkan ke ZIP. Nama file fisik server selalu saved_name.
    $originalName = $type.'_'.date('d-m-Y_H-i-s').'.'.$extension;
    // ZIP dipisahkan dari PDF/Excel agar penyimpanan file tetap terstruktur.
    $path = $storageDirectory.$savedName;
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Dokumen gagal disimpan ke server.');
    }

    $fileSize = filesize($path) ?: strlen($content);
    $generatedByUserId = authUserId();
    $billingId = $billingId && $billingId > 0 ? $billingId : null;
    $stmt = $db->prepare("INSERT INTO generated_documents
        (file_id, document_type, original_name, saved_name, file_size, billing_id, generated_by_user_id)
        VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('isssiii', $fileId, $type, $originalName, $savedName, $fileSize, $billingId, $generatedByUserId);
    if (!$stmt->execute()) {
        @unlink($path);
        throw new RuntimeException('Metadata dokumen gagal disimpan ke database.');
    }
    $documentId = $stmt->insert_id;
    $stmt->close();

    return ['id' => $documentId, 'name' => $originalName, 'size' => $fileSize];
}

function getUploadMetadata(mysqli $db, int $fileId): array {
    $stmt = $db->prepare("SELECT periode, tanggal FROM uploaded_files WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $metadata = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$metadata) throw new RuntimeException('Arsip upload tidak ditemukan.');

    return ['periode' => (string)$metadata['periode'], 'tanggal' => (string)$metadata['tanggal']];
}
