## Generate Invoice
PHP 8.2

Sebelum memulai lakukan composer install di dalam folder proyek

- Aplikasi ini memproes data prefik voucher (Mengambil 3 prefix kode voucher)
- Aplikasi membaca data berdasarkan posisi kolom, bukan berdasarkan nama header.
- Pengaturan saat ini berada di `assets/php/cont/cont.php`:

## Contoh format data
Data pertama berada di baris ke 6 
```php
define('EXCEL_HEADER_ROW', 5); // Menentukan Baris Header
```

Kode voucher berada di kolom ke 3
```php
define('EXCEL_CODE_COLUMN', 3); //Menentuka Kolom Voucher
```

Paket pelanggan berada di kolom ke 4
```php
define('EXCEL_PACKAGE_COLUMN', 4); //Menentukan Kolom Paket 
```
Harga dari billing 
```php
define('EXCEL_COST_COLUMN', 6);
```
Harga untuk pelanggan di buat ketika menambahkan pelanggan

Pastikan extensi aktif di `php.ini` tidak di awali `;` 
```ini
extension=mysqli
extension=gd
extension=zip
extension=mbstring
extension=fileinfo
extension=xml
extension=xmlreader
extension=xmlwriter
allow_url_include=on
```

Yang paling penting untuk aplikasi ini adalah:

- `gd` untuk membaca dan mengolah gambar/logo.
- `zip` untuk membuat ZIP dan memproses file Excel.
- `mysqli` untuk koneksi database.

Untuk mengecek ekstensi yang aktif:

```powershell
php -m
```

Default akun
```text
Username : admin
Password : admin
```

## Catatan

- Versi PHP minimal 8.2.
- Ekstensi `gd`, `zip`, `mysqli`, `mbstring`, `fileinfo`, dan XML aktif.
- Jalankan `composer install --no-dev --optimize-autoloader`.
- Ubah konfigurasi database di `assets/php/cont/cont.php`.
- Pastikan folder upload, generated, dan logo dapat ditulis.
- Ubah akun admin bawaan.
- Gunakan HTTPS.
- Jangan menampilkan error PHP kepada pengunjung.
- Buat backup database dan folder file.

Contoh batas upload di `php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 24M
max_execution_time = 120
memory_limit = 256M
```

## Masalah yang sering terjadi

### Composer tidak ditemukan

Pastikan Composer sudah terpasang dan dapat dijalankan dari PowerShell:

```powershell
composer --version
```

### ZIP tidak dapat dibuat

Aktifkan:

```ini
extension=zip
```

### Logo atau gambar bermasalah

Aktifkan:

```ini
extension=gd
extension=fileinfo
```

### File terlalu besar

Naikkan `upload_max_filesize` dan `post_max_size` pada `php.ini`, kemudian restart Apache.

