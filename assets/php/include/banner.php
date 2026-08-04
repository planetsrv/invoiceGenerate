<?php
$xccLkmn = 'planetINV';
$titles = [
    'index'       => 'Beranda',
    'login'       => 'Login',
    'logout'      => 'Logout',
    'pelanggan'   => 'Pelanggan',
    'manajemen'   => 'Manajemen',
    'setakun'     => 'Set Akun',
    'auth'        => 'Otentikasi',
    'client_area' => 'Client Area',
];
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$currentFile = pathinfo($scriptPath, PATHINFO_FILENAME);
$currentFile = preg_replace('/[^a-zA-Z0-9_-]/', '', $currentFile);
$pageTitle = $titles[$currentFile] ?? 'Halaman';
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
