<?php
// Nama aplikasi / website
$appName = 'plannetINV';

// Daftar judul khusus untuk file tertentu
$titles = [
    'index'       => 'Beranda',
    'login'       => 'Login',
    'logout'      => 'Logout',
    'pelanggan'   => 'Pelanggan',
    'manajemen'   => 'Manajemen', // Perbaiki typo di sini
    'setakun'     => 'Set Akun',
    'auth'        => 'Otentikasi',
    'client_area' => 'Client Area',
];

// Ambil nama file yang sedang berjalan dengan aman
// Gunakan SCRIPT_NAME karena lebih aman daripada PHP_SELF
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$currentFile = pathinfo($scriptPath, PATHINFO_FILENAME);

// Sanitasi ketat: hanya izinkan huruf, angka, garis bawah, dan tanda hubung
$currentFile = preg_replace('/[^a-zA-Z0-9_-]/', '', $currentFile);

// Tentukan judul halaman
// Jika tidak ada di daftar, gunakan judul default 'Halaman' sebagai fallback yang aman
$pageTitle = $titles[$currentFile] ?? 'Halaman';

// Fungsi bantu untuk escape output HTML (opsional, tapi disarankan)
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
