## Generate Invoice
 Aplikasi ini memproes data prefik voucher (Mengambil 3 awalan kode voucher)

## Data yang di proses upload

Aplikasi membaca data berdasarkan posisi kolom, bukan berdasarkan nama header.

Pengaturan saat ini berada di `assets/php/cont/cont.php`:

```php
define('EXCEL_HEADER_ROW', 5);
define('EXCEL_CODE_COLUMN', 3);
define('EXCEL_PACKAGE_COLUMN', 4);
define('EXCEL_COST_COLUMN', 6);
```

Data yang dibaca:

| Posisi | Data | Keterangan |
|---|---|---|
| Baris 5 | Header | Tidak ikut diproses |
| Kolom C | Kode voucher | Tiga karakter pertama menjadi prefix pelanggan |
| Kolom D | Paket | Nama paket voucher |
| Kolom F | Biaya | Biaya dari data voucher |

Data mulai diproses dari baris 6 sampai baris terakhir.

Contoh:

| Kode voucher | Paket | Biaya |
|---|---|---:|
| AKH0001 | 12 JAM | 1.000 |
| AKH0002 | 6 JAM | 5.000 |
| BCD0001 | 7 HARI | 15.000 |

Proses upload:

   - Baris diproses jika kode dan paket terisi.
   - Tiga karakter pertama kode diambil sebagai prefix. Contoh `AKH0001` menjadi `AKH`.
   - Paket baru dari file otomatis dimasukkan ke master paket.
   - Jumlah voucher dihitung berdasarkan prefix dan paket.
   - Biaya yang bukan angka akan dianggap `0`.
   - Total invoice dihitung dari jumlah voucher dikalikan harga paket pelanggan.
   - Periode dan tanggal dari formulir upload digunakan pada rekap, Excel, dan PDF.

Data disimpan ke:

- `uploaded_files` untuk informasi file upload.
- `rincian` untuk kode, prefix, paket, dan biaya setiap voucher.
- `rekap` untuk jumlah voucher per prefix dan paket.
- `invoices` untuk total tagihan pelanggan.
- `paket_master` untuk daftar paket.

Catatan:

- Sesuaikan dokumen yang akan i proses `cont.php`.
- Kode voucher sebaiknya memiliki minimal tiga karakter.
- Nama paket pada file harus konsisten dengan harga paket pelanggan.
- Paket yang belum memiliki harga akan menghasilkan tagihan `0`.

## Insatalasi
Cari ekstensi berikut di `php.ini`, kemudian hapus tanda `;` di depannya agar aktif:

```ini
extension=mysqli
extension=gd
extension=zip
extension=mbstring
extension=fileinfo
extension=xml
extension=xmlreader
extension=xmlwriter
```

Yang paling penting untuk aplikasi ini adalah:

- `gd` untuk membaca dan mengolah gambar/logo.
- `zip` untuk membuat ZIP dan memproses file Excel.
- `mysqli` untuk koneksi database.

Untuk mengecek ekstensi yang aktif:

```powershell
php -m
```
`
Jika folder `vendor` hilang atau muncul error `vendor/autoload.php`

```powershell
composer install
```
K
## Login saya

Login awal manajemen:

```text
Username : admin
Password : admin
```

Login manajemen:

```text
http://localhost/login.php
```

Login pelanggan:

```text
http://localhost/customer/login.php
`

### Siapkan data awal

1. Login sebagai admin.
2. Buat data billing jika belum tersedia.
3. Tambahkan pelanggan.
4. Isi paket dan harga pelanggan.

Ketika pelanggan ditambahkan, akun customer otomatis dibuat dan dapat dilihat pada halaman Manajemen.

### Atur identitas perusahaan

Buka halaman Manajemen, kemudian isi:

   - Nama perusahaan.
   - Nama kontak.
   - Nomor telepon.
   - Email.
   - Website.
   - Alamat perusahaan.
   - Informasi pembayaran.
   - Catatan invoice.
   - Logo perusahaan melalui tombol **Upload Logo**.
      
      Informasi ini digunakan pada PDF invoice customer.

Upload data voucher

   1. Buka halaman utama.
   2. Pilih billing.
   3. Isi periode.
   4. Isi tanggal.
   5. Tekan tombol pilih file.
   6. Pilih file `.xlsx`, `.xls`, `.ods`, atau `.csv`.

Setelah file dipilih, proses upload berjalan otomatis.

### Generate dokumen
   - Melihat rekap.
   - Mencari pelanggan berdasarkan nama.
   - Membuka Excel.
   - Membuka PDF.
   - Mengunduh ZIP.
   - Melihat arsip dokumen.

Tanggal dan periode yang saya isi sebelum upload akan digunakan secara konsisten pada Excel, PDF manajemen, dan PDF customer.

### Area customer

Customer dapat:

- Melihat tagihan terbaru.
- Melihat riwayat tagihan sebanyak 25 data per halaman.
- Mencetak invoice PDF.
- Mengubah nomor telepon.
- Mengubah alamat.

Jika telepon atau alamat belum diisi, aplikasi menampilkan peringatan di bagian atas halaman customer.

## Jika dipindahkan ke server

Catatan yang harus saya periksa:

- Versi PHP minimal 8.2.
- Ekstensi `gd`, `zip`, `mysqli`, `mbstring`, `fileinfo`, dan XML aktif.
- Jalankan `composer install --no-dev --optimize-autoloader`.
- Ubah konfigurasi database di `auth.php`.
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

Restart Apache atau PHP setelah mengubah konfigurasi.

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

### Perubahan tampilan tidak muncul

Lakukan hard refresh pada browser:

```text
Ctrl + F5
```
