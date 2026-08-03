<?php
require_once dirname(__DIR__).'/auth.php';

customerRequireLogin('../login.php');

$autoloadPath = dirname(__DIR__, 2).'/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    http_response_code(500);
    exit('Dependensi PDF belum tersedia. Jalankan composer install.');
}
require_once $autoloadPath;

$db = authDb();
$companySettings = getCompanySettings($db);
$awalan = customerAuthAwalan();
$fileId = max(0, (int)($_GET['file_id'] ?? 0));

if ($fileId > 0) {
    $stmt = $db->prepare("SELECT f.id AS file_id, f.uploaded_at, f.periode, f.tanggal,
            i.total_harga, c.nama_pelanggan, c.alamat, c.telepon,
            COALESCE(b.nama, '-') AS billing_name
        FROM invoices i
        INNER JOIN uploaded_files f ON f.id = i.file_id
        INNER JOIN prefix_customers c ON c.awalan = i.awalan
        LEFT JOIN billing_master b ON b.id = c.billing_id
        WHERE i.file_id = ? AND i.awalan = ?
        LIMIT 1");
    $stmt->bind_param('is', $fileId, $awalan);
} else {
    $stmt = $db->prepare("SELECT f.id AS file_id, f.uploaded_at, f.periode, f.tanggal,
            i.total_harga, c.nama_pelanggan, c.alamat, c.telepon,
            COALESCE(b.nama, '-') AS billing_name
        FROM invoices i
        INNER JOIN uploaded_files f ON f.id = i.file_id
        INNER JOIN prefix_customers c ON c.awalan = i.awalan
        LEFT JOIN billing_master b ON b.id = c.billing_id
        WHERE i.awalan = ?
        ORDER BY f.uploaded_at DESC, f.id DESC
        LIMIT 1");
    $stmt->bind_param('s', $awalan);
}
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    $db->close();
    http_response_code(404);
    exit('Tagihan tidak ditemukan.');
}

$fileId = (int)$invoice['file_id'];
$stmt = $db->prepare("SELECT r.paket, r.jumlah, COALESCE(h.harga, 0) AS harga
    FROM rekap r
    LEFT JOIN customer_paket_harga h ON h.awalan = r.awalan AND h.paket = r.paket
    LEFT JOIN paket_master pm ON pm.nama = r.paket
    WHERE r.file_id = ? AND r.awalan = ?
    ORDER BY pm.id ASC, r.paket ASC");
$stmt->bind_param('is', $fileId, $awalan);
$stmt->execute();
$result = $stmt->get_result();
$items = '';
while ($row = $result->fetch_assoc()) {
    $package = htmlspecialchars((string)$row['paket'], ENT_QUOTES, 'UTF-8');
    $quantity = (int)$row['jumlah'];
    $price = (float)$row['harga'];
    $subtotal = $quantity * $price;
    $items .= '<tr>'
        .'<td>'.$package.'</td>'
        .'<td class="text-end">'.$quantity.'</td>'
        .'<td class="text-end">'.number_format($price, 0, ',', '.').'</td>'
        .'<td class="text-end">'.number_format($subtotal, 0, ',', '.').'</td>'
        .'</tr>';
}
$stmt->close();
$db->close();

if ($items === '') {
    $items = '<tr><td colspan="4" class="empty-row">Rincian paket belum tersedia.</td></tr>';
}

$storedDate = trim((string)($invoice['tanggal'] ?? ''));
$timestamp = strtotime($storedDate !== '' ? $storedDate : (string)$invoice['uploaded_at']) ?: time();
$invoiceDate = date('d-m-Y', $timestamp);
$period = trim((string)($invoice['periode'] ?? '')) ?: date('m/Y', $timestamp);
$invoiceNumber = 'INV-'.date('Ym', $timestamp).str_pad((string)$fileId, 3, '0', STR_PAD_LEFT);
$escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$name = $escape($invoice['nama_pelanggan']);
$prefix = $escape($awalan);
$address = nl2br($escape($invoice['alamat'] ?: '-'));
$telephone = $escape($invoice['telepon'] ?: '-');
$billingName = $escape($invoice['billing_name'] ?: '-');
$total = (float)$invoice['total_harga'];

$companyName = $escape($companySettings['company_name'] ?: 'PLANETFlow');
$companyAddress = nl2br($escape($companySettings['address'] ?: '-'));
$companyPhone = $escape($companySettings['phone']);
$companyEmail = $escape($companySettings['email']);
$companyWebsite = $escape($companySettings['website']);
$contactName = $escape($companySettings['contact_name']);
$paymentInfo = nl2br($escape($companySettings['payment_info']));
$invoiceNote = nl2br($escape($companySettings['invoice_note']));

// Logo dibuat menjadi data URI supaya tetap tampil tanpa akses URL eksternal.
$logoHtml = '';
$logoRelativePath = ltrim(str_replace('\\', '/', (string)$companySettings['logo_path']), '/');
$logoRoot = realpath(dirname(__DIR__, 2).'/assets/uploads/company');
$logoFile = $logoRelativePath !== '' ? realpath(dirname(__DIR__, 2).'/'.$logoRelativePath) : false;
if ($logoRoot !== false && $logoFile !== false
    && str_starts_with($logoFile, $logoRoot.DIRECTORY_SEPARATOR)
    && is_file($logoFile)) {
    $imageInfo = @getimagesize($logoFile);
    $mime = (string)($imageInfo['mime'] ?? '');
    if (in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        $logoData = base64_encode((string)file_get_contents($logoFile));
        $logoHtml = '<img class="company-logo" src="data:'.$mime.';base64,'.$logoData.'" alt="Logo">';
    }
}
if ($logoHtml === '') {
    $logoHtml = '<div class="logo-placeholder">'.strtoupper(substr(strip_tags($companyName), 0, 2)).'</div>';
}

$companyContactLines = [];
if ($companyPhone !== '') $companyContactLines[] = 'Tel: '.$companyPhone;
if ($companyEmail !== '') $companyContactLines[] = 'Email: '.$companyEmail;
if ($companyWebsite !== '') $companyContactLines[] = $companyWebsite;
if ($contactName !== '') $companyContactLines[] = 'Kontak: '.$contactName;
$companyContactHtml = $companyContactLines ? implode('<br>', $companyContactLines) : '-';
$additionalInfoHtml = '';
if ($paymentInfo !== '' || $invoiceNote !== '') {
    $additionalInfoHtml = '<table class="additional-info"><tr>'
        .'<td><div class="small-title">INFORMASI PEMBAYARAN</div><div>'.($paymentInfo !== '' ? $paymentInfo : '-').'</div></td>'
        .'<td><div class="small-title">CATATAN</div><div>'.($invoiceNote !== '' ? $invoiceNote : '-').'</div></td>'
        .'</tr></table>';
}

$html = '<!doctype html>
<html><head><meta charset="utf-8"><style>
    @page { size: A4 portrait; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; background: #fff; }
    .invoice-page { position: relative; width: auto; min-height: 265mm; padding: 14mm 14mm 18mm; overflow: hidden; background: #fff; }
    .top-accent { position: absolute; top: 0; right: 0; left: 0; height: 5mm; background: #1e40af; }
    .header-table, .invoice-summary, .customer-table, .additional-info { width: 100%; table-layout: fixed; border-collapse: collapse; }
    .header-table { margin-bottom: 8mm; }
    .header-table td { padding: 0; vertical-align: top; }
    .brand-cell { width: 48%; }
    .company-logo { display: block; max-width: 42mm; max-height: 20mm; margin-bottom: 3mm; }
    .logo-placeholder { width: 18mm; height: 18mm; padding-top: 4.5mm; margin-bottom: 3mm; color: #fff; background: #1e40af; border-radius: 4mm; font-size: 18px; font-weight: bold; text-align: center; }
    .company-name { margin-bottom: 1.5mm; color: #102a66; font-size: 18px; font-weight: bold; }
    .company-address { max-width: 78mm; color: #596579; line-height: 1.45; overflow-wrap: break-word; }
    .invoice-title-cell { width: 52%; text-align: right; }
    .invoice-title { color: #1e40af; font-size: 29px; font-weight: bold; letter-spacing: 1px; }
    .invoice-number { margin-top: 1mm; color: #3f4c61; font-size: 11px; }
    .company-contact { margin-top: 4mm; color: #596579; line-height: 1.55; overflow-wrap: break-word; }
    .invoice-summary { margin-bottom: 6mm; background: #f2f5fb; border-left: 3px solid #1e40af; }
    .invoice-summary td { width: 25%; padding: 3mm; vertical-align: top; }
    .summary-label, .small-title { margin-bottom: 1mm; color: #667085; font-size: 8px; font-weight: bold; letter-spacing: .45px; }
    .summary-value { color: #172033; font-size: 10.5px; font-weight: bold; overflow-wrap: break-word; }
    .customer-table { margin-bottom: 6mm; }
    .customer-table td { width: 50%; padding: 0; vertical-align: top; }
    .customer-card { min-height: 29mm; padding: 4mm; border: 1px solid #dce2ec; border-radius: 3px; }
    .customer-card.right { margin-left: 3mm; }
    .customer-name { margin: 1mm 0 2mm; color: #102a66; font-size: 13px; font-weight: bold; }
    .detail-line { margin-top: 1.2mm; color: #4d596c; line-height: 1.4; overflow-wrap: break-word; }
    .items-table { width: 100%; margin-bottom: 4mm; table-layout: fixed; border-collapse: collapse; font-size: 9.5px; }
    .items-table th, .items-table td { padding: 2.6mm 2.4mm; border-bottom: 1px solid #dce2ec; }
    .items-table th { color: #fff; background: #1e40af; font-size: 8.5px; font-weight: bold; letter-spacing: .2px; }
    .items-table th:first-child { width: 43%; }
    .items-table th:nth-child(2) { width: 11%; }
    .items-table th:nth-child(3), .items-table th:nth-child(4) { width: 23%; }
    .items-table td:first-child { overflow-wrap: break-word; }
    .items-table tbody tr:nth-child(even) { background: #f8fafc; }
    .text-end { text-align: right; }
    .empty-row { color: #667085; text-align: center; }
    .total-table { width: 72mm; margin: 0 0 5mm auto; border-collapse: collapse; }
    .total-table td { padding: 3mm 3.5mm; color: #fff; background: #102a66; }
    .total-label { font-size: 10px; font-weight: bold; }
    .total-value { font-size: 15px; font-weight: bold; text-align: right; }
    .additional-info { margin-top: 4mm; }
    .additional-info td { width: 50%; padding: 3.5mm; vertical-align: top; background: #f8fafc; border: 1px solid #e2e7ef; color: #4d596c; line-height: 1.45; overflow-wrap: break-word; }
    .additional-info td + td { border-left: 3mm solid #fff; }
    .invoice-footer { position: absolute; right: 15mm; bottom: 8mm; left: 15mm; padding-top: 3mm; color: #778195; border-top: 1px solid #dce2ec; font-size: 8.5px; text-align: center; }
</style></head><body>
<div class="invoice-page">
    <div class="top-accent"></div>
    <table class="header-table"><tr>
        <td class="brand-cell">'.$logoHtml.'<div class="company-name">'.$companyName.'</div><div class="company-address">'.$companyAddress.'</div></td>
        <td class="invoice-title-cell"><div class="invoice-title">INVOICE</div><div class="invoice-number">'.$escape($invoiceNumber).'</div><div class="company-contact">'.$companyContactHtml.'</div></td>
    </tr></table>
    <table class="invoice-summary"><tr>
        <td><div class="summary-label">NOMOR INVOICE</div><div class="summary-value">'.$escape($invoiceNumber).'</div></td>
        <td><div class="summary-label">TANGGAL</div><div class="summary-value">'.$escape($invoiceDate).'</div></td>
        <td><div class="summary-label">PERIODE</div><div class="summary-value">'.$escape($period).'</div></td>
        <td><div class="summary-label">STATUS</div><div class="summary-value">DITERBITKAN</div></td>
    </tr></table>
    <table class="customer-table"><tr>
        <td><div class="customer-card"><div class="small-title">DITAGIHKAN KEPADA</div><div class="customer-name">'.$name.'</div><div class="detail-line">'.$address.'</div><div class="detail-line">Tel: '.$telephone.'</div></div></td>
        <td><div class="customer-card right"><div class="small-title">INFORMASI PELANGGAN</div><div class="detail-line"><strong>Awalan:</strong> '.$prefix.'</div><div class="detail-line"><strong>Billing:</strong> '.$billingName.'</div><div class="detail-line"><strong>Periode layanan:</strong> '.$escape($period).'</div></div></td>
    </tr></table>
        <table class="items-table">
            <thead><tr><th>PAKET</th><th class="text-end">QTY</th><th class="text-end">HARGA</th><th class="text-end">SUBTOTAL</th></tr></thead>
            <tbody>'.$items.'</tbody>
        </table>
    <table class="total-table"><tr><td class="total-label">TOTAL TAGIHAN</td><td class="total-value">Rp '.number_format($total, 0, ',', '.').'</td></tr></table>
    '.$additionalInfoHtml.'
    <div class="invoice-footer">Invoice ini dibuat oleh '.$companyName.' &middot; '.$companyContactHtml.'</div>
</div>
</body></html>';

$options = new Dompdf\Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('Invoice_'.$awalan.'_'.$invoiceDate.'.pdf', ['Attachment' => false]);
exit;
