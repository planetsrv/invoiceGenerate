<?php
// Nama aplikasi / website — cukup edit di sini
//$appName = 'plannetINV';
function cGxhbm5ldElOVg==() { return 'plannetINV';
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
*Penggunaan
<title><?= $pageTitle;?> - <?= $appName;?></title>
    */
$pageTitle = $titles[$currentFile] ?? ucfirst($currentFile);
?>
