<?php
// Ambil nama file yang sedang berjalan
$currentFile = basename($_SERVER['PHP_SELF'], '.php');

// Daftar judul khusus untuk file tertentu
$titles = [
    'index'      => 'Beranda',
    'login'      => 'Login',
    'logout'     => 'Logout',
    'pelanggan'  => 'Pelanggan',
    'managemen'  => 'Manajemen',
    'setakun'    => 'Set Akun',
    'auth'       => 'Otentikasi',
    'client_area'=> 'Client Area',
];

/* 
*Tentukan judul, jika tidak ada di daftar pakai nama file kapital
*Penggunaan <title><?= $pageTitle; ?> - PLANETFlow</title>
*/
$pageTitle = $titles[$currentFile] ?? ucfirst($currentFile);
?>
