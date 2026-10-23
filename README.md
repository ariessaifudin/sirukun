# SIRUKUN 1.1 — Sistem Informasi RT/RW Terpadu

Aplikasi web administrasi lingkungan berbasis **Laravel 12, PHP 8.2+, dan MySQL/MariaDB**, dengan tampilan responsif dan empat peran: Warga, RT, RW, serta Administrator (super user).

## Fitur

- Data wilayah, hunian, kartu keluarga, dan warga.
- Mutasi pindah masuk, pindah keluar, kelahiran, dan kematian.
- Pelaporan tamu menginap serta penghuni kontrakan/indekos dan pengingat masa tinggal.
- Lima jenis surat dengan persetujuan Ketua RT → Ketua RW, PDF bernomor urut, QR verifikasi, dan arsip digital.
- Dashboard, laporan PDF/Excel, pengelolaan pengguna/menu, log aktivitas, dan backup SQL.
- Pembatasan akses sesuai peran/wilayah dan penyimpanan dokumen privat.

## Enkripsi AES-256-GCM

Seluruh nilai pada 25 tabel logis, dokumen, backup, sesi, cache, dan log disimpan terenkripsi. MySQL/MariaDB menyimpan ciphertext dalam `sirukun_vault`; pencarian dan relasi dijalankan di SQLite `:memory:`. Batas snapshot 16 MiB dan akses database bergiliran membuat format ini ditujukan untuk pemasangan RT/RW kecil; kapasitas beban belum dibenchmark. Database tidak lagi dapat diedit langsung melalui tabel warga di phpMyAdmin.

**Untuk pemasangan lama, wajib jalankan migrasi.** Source baru tidak mengenkripsi database lama secara otomatis. Baca [PANDUAN_AES_GCM.md](PANDUAN_AES_GCM.md) sebelum memperbarui. Panduan mencakup pembuatan kunci, verifikasi migrasi, penghapusan salinan plaintext lama, backup, restore, dan batas enkripsi.

## Instalasi dari GitHub di XAMPP

Repositori ini berisi source code dan `composer.lock`. Folder `vendor` tidak disertakan; jalankan Composer sekali untuk mengunduh dependensi. Paket ZIP/RAR XAMPP yang telah dibuat sebelumnya menyertakan dependensi dan dapat dipasang tanpa Composer. Node.js/npm tidak diperlukan.

1. Gunakan XAMPP dengan PHP **8.2 atau lebih baru**. Aktifkan Apache dan MySQL.
2. Aktifkan ekstensi PHP `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `tokenizer`, `dom`, `xml`, `xmlwriter`, `gd`, dan `zip`. Apache memerlukan `mod_rewrite` dan `AllowOverride All`.
3. Clone repositori ke `C:\xampp\htdocs\sirukun`, atau unduh source lalu ubah nama folder hasil ekstraksi menjadi `sirukun`.
4. Dari terminal, jalankan:

   ```bat
   cd C:\xampp\htdocs\sirukun
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

   Pastikan Composer memakai PHP XAMPP yang sama dengan Apache.
5. Buka **http://localhost/sirukun/public/install.php** melalui browser pada komputer XAMPP.
6. Isi koneksi database **baru/kosong**, identitas RW/RT, serta nama, email, dan kata sandi Administrator minimal 12 karakter.
7. Installer membuat konfigurasi `.env`, tabel database, data awal, dan akun Administrator, lalu mengunci dirinya. Jangan menjalankan migrasi atau membuat `.env` sebelum memakai installer ini.
8. Buka **http://localhost/sirukun/public/** dan masuk dengan akun yang baru dibuat. Lengkapi wilayah, hunian, KK, warga, dan akun pengurus melalui aplikasi.

Tidak tersedia akun Administrator dengan kata sandi bawaan. Untuk port Apache selain 80, sesuaikan URL. Direktori `storage`, `bootstrap/cache`, serta root proyek harus dapat ditulis saat instalasi.

## Pengembangan dan pengujian

Pasang seluruh dependensi termasuk PHPUnit:

```sh
composer install
php vendor/bin/phpunit
```

Pengujian menggunakan SQLite dalam memori; aktifkan `pdo_sqlite` dan ekstensi PHP yang dibutuhkan aplikasi. Data pada pengujian adalah data contoh. Hasil pengujian paket sebelumnya dan batas verifikasinya tercatat di [HASIL_PENGUJIAN.txt](HASIL_PENGUJIAN.txt).

## Struktur proyek

| Folder/file | Fungsi |
|---|---|
| `app/` | Controller, model, middleware, dan layanan aplikasi |
| `database/` | Migrasi dan pengisian data dasar |
| `resources/views/` | Tampilan Blade |
| `public/` | Entry point, installer, CSS, dan JavaScript |
| `routes/` | Rute aplikasi dan penjadwalan |
| `config/` | Konfigurasi Laravel |
| `storage/` | Data privat, surat, cache, sesi, dan log saat aplikasi dijalankan |
| `tests/` | Pengujian aplikasi |
| `scheduler.bat` | Pemanggilan penjadwal melalui Windows Task Scheduler |
| `PANDUAN_INSTALL.txt` | Panduan lengkap paket XAMPP dan operasional |

Panduan paket menjelaskan pemasangan ZIP/RAR yang sudah menyertakan `vendor`. Untuk unduhan source GitHub, ikuti langkah Composer di atas terlebih dahulu.

## Data dan operasional

Konfigurasi `.env`, kata sandi, dokumen warga, database, sesi, log, cache, dan hasil pengujian tidak dimasukkan ke repositori. Backup `.sirukun` tetap terenkripsi ketika diunduh; simpan kunci dari `.env` secara aman dan terpisah dari backup. Atur penjadwalan sesuai [PANDUAN_INSTALL.txt](PANDUAN_INSTALL.txt) untuk pengingat dan pencadangan berkala.

## Lisensi

[MIT](LICENSE).
