<?php
$baseDir = __DIR__ . '/..';
$files = [
    'index.php',
    'pelanggan.php',
    'files.php',
    'managemen.php',
    'auth.php',
    'README.md',
    'assets/css/main-style.css',
    'assets/php/functions/logic.php',
    'assets/php/functions/main-function.php',
    'customer/auth.php',
    'customer/index.php',
    'customer/actions/update_profile.php',
    'customer/actions/invoice_pdf.php',
    'customer/login.php',
    'backup sql/db_invgenerator.sql',
];

foreach ($files as $relativePath) {
    $fullPath = $baseDir . '/' . $relativePath;
    if (!is_file($fullPath)) {
        continue;
    }
    $content = file_get_contents($fullPath);
    $newContent = str_replace('awalan', 'prefix', $content);
    $newContent = str_replace('Awalan', 'Prefix', $newContent);
    $newContent = str_replace('AWALAN', 'PREFIX', $newContent);
    if ($newContent !== $content) {
        file_put_contents($fullPath, $newContent);
        echo "updated $relativePath\n";
    }
}

$dsn = 'mysql:host=localhost;dbname=db_invGenerator;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = ['rincian', 'rekap', 'prefix_customers', 'customer_paket_harga', 'invoices'];
    foreach ($tables as $table) {
        if ($table === 'rincian') {
            $pdo->exec("ALTER TABLE `rincian` CHANGE COLUMN `awalan` `prefix` VARCHAR(10) NOT NULL");
        } elseif ($table === 'rekap') {
            $pdo->exec("ALTER TABLE `rekap` CHANGE COLUMN `awalan` `prefix` VARCHAR(10) NOT NULL");
        } elseif ($table === 'prefix_customers') {
            $pdo->exec("ALTER TABLE `prefix_customers` CHANGE COLUMN `awalan` `prefix` VARCHAR(10) NOT NULL");
        } elseif ($table === 'customer_paket_harga') {
            $pdo->exec("ALTER TABLE `customer_paket_harga` CHANGE COLUMN `awalan` `prefix` VARCHAR(10) NOT NULL");
        } elseif ($table === 'invoices') {
            $pdo->exec("ALTER TABLE `invoices` CHANGE COLUMN `awalan` `prefix` VARCHAR(10) NOT NULL");
        }
    }

    $pdo->exec("ALTER TABLE `customer_accounts` CHANGE COLUMN `customer_awalan` `customer_prefix` VARCHAR(10) NOT NULL");
    echo "database columns updated\n";
} catch (Throwable $e) {
    echo "database migration skipped: " . $e->getMessage() . "\n";
}
