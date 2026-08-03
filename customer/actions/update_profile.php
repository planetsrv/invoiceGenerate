<?php
require_once dirname(__DIR__).'/auth.php';

customerRequireLogin('../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php#profil');
    exit;
}

try {
    if (!verifyCustomerCsrf((string)($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Permintaan tidak valid. Muat ulang halaman dan coba kembali.');
    }

    $field = (string)($_POST['field'] ?? '');
    if (!in_array($field, ['telepon', 'alamat'], true)) {
        throw new RuntimeException('Bagian profil yang akan diedit tidak valid.');
    }

    $value = trim((string)($_POST[$field] ?? ''));
    if ($value === '') {
        throw new RuntimeException(
            $field === 'telepon' ? 'Nomor telepon wajib diisi.' : 'Alamat wajib diisi.'
        );
    }
    if ($field === 'telepon' && mb_strlen($value) > 20) {
        throw new RuntimeException('Nomor telepon maksimal 20 karakter.');
    }
    if ($field === 'alamat' && mb_strlen($value) > 2000) {
        throw new RuntimeException('Alamat maksimal 2.000 karakter.');
    }

    $db = authDb();
    $awalan = customerAuthAwalan();
    $stmt = $field === 'telepon'
        ? $db->prepare("UPDATE prefix_customers SET telepon = ? WHERE awalan = ?")
        : $db->prepare("UPDATE prefix_customers SET alamat = ? WHERE awalan = ?");
    $stmt->bind_param('ss', $value, $awalan);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();
    $db->close();

    if ($updated < 1) {
        // Nilai yang sama tetap dianggap berhasil selama pelanggan memang ada.
        $db = authDb();
        $stmt = $db->prepare("SELECT id FROM prefix_customers WHERE awalan = ? LIMIT 1");
        $stmt->bind_param('s', $awalan);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        $db->close();
        if (!$exists) throw new RuntimeException('Data pelanggan tidak ditemukan.');
    }

    $_SESSION['customer_profile_message'] = $field === 'telepon'
        ? 'Nomor telepon berhasil diperbarui.'
        : 'Alamat berhasil diperbarui.';
} catch (Throwable $exception) {
    $_SESSION['customer_profile_error'] = $exception->getMessage();
    $_SESSION['customer_profile_edit_field'] = (string)($_POST['field'] ?? '');
}

header('Location: ../index.php#profil');
exit;
