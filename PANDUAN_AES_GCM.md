# SIRUKUN 1.1 — AES-256-GCM

Versi ini mengenkripsi seluruh **nilai data aplikasi yang dipersistenkan**: seluruh tabel logis (termasuk ID, relasi, tanggal, status, hash kata sandi, audit, dan konfigurasi menu), dokumen unggahan, PDF surat, backup, sesi Laravel, cache aplikasi, serta isi log aplikasi. Data ditampilkan kembali hanya melalui layanan aplikasi dengan pemeriksaan hak akses yang sama.

## Arsitektur dan batas penggunaan

- MySQL/MariaDB menyimpan tabel fisik `sirukun_vault` berisi potongan ciphertext dan manifest terautentikasi. Struktur 25 tabel layanan tetap digunakan sebagai skema logis, tetapi **tidak lagi dapat dibaca atau diubah langsung sebagai tabel warga/surat melalui phpMyAdmin**.
- Saat koneksi dibuka, snapshot didekripsi ke database SQLite **`:memory:`**. Pencarian, relasi, unique constraint, pengurutan, agregasi, dan transaksi berjalan di RAM; tidak dibuat berkas SQLite plaintext. Temporary store dan journal SQLite juga di memori.
- Penyimpanan dilakukan secara atomik melalui transaksi InnoDB. Lock MySQL per database dipertahankan selama koneksi aplikasi hidup untuk mencegah dua proses menimpa pembaruan satu sama lain. Dengan demikian, akses database antarpemintaan diproses bergiliran, termasuk pembacaan. Snapshot ditulis ulang pada commit.
- Batas snapshot JSON adalah **16 MiB**, bukan batas ukuran total dokumen unggahan. Penulisan yang melampaui batas dibatalkan. Sediakan memori PHP sedikitnya 256 MiB untuk pemakaian awal; uji kapasitas dengan data mitra. Ini dirancang untuk pemasangan RT/RW berskala kecil. Belum dilakukan benchmark beban; jangan gunakan untuk volume besar atau worker persisten seperti Octane, Horizon, `queue:work`, dan `schedule:work`. Gunakan Apache biasa dan `schedule:run` melalui scheduler.bat.
- Skema ini diperlukan agar status, tanggal, ID, dan seluruh isi tabel tidak menjadi kolom plaintext. Ini merupakan perubahan format penyimpanan fisik, bukan sekadar penggantian cipher Laravel.

## Kunci dan format

`DATA_ENCRYPTION_KEY` adalah kunci acak 32 byte dalam format `base64:...`; dipakai untuk database, dokumen, backup, cache, dan log. `APP_KEY` tetap terpisah untuk sesi/cookie Laravel, kini dengan cipher `AES-256-GCM`.

Envelope data memakai versi format, nonce acak **12 byte**, authentication tag **16 byte**, dan ciphertext AES-256-GCM. AAD mengikat ciphertext pada konteks pemakaiannya, termasuk path berkas atau generasi/nomor potongan database. Setiap enkripsi memakai nonce baru. Manifest mengikat jumlah dan hash seluruh potongan. Perubahan byte, pemotongan, salah konteks, atau salah kunci menyebabkan kegagalan; aplikasi tidak menerima plaintext sebagai pengganti ciphertext.

**Jangan mengganti atau menghapus kunci setelah data tersimpan.** Kehilangan kunci menyebabkan data tidak dapat dipulihkan. Simpan salinan kedua kunci di tempat aman terpisah dari backup. Perintah pembuatan kunci menolak menimpa kunci yang sudah ada. Rotasi/re-enkripsi kunci belum disediakan dalam versi ini.

Kunci, kredensial database, dan konfigurasi bootstrap di `.env` adalah bahan rahasia untuk membuka sistem; tidak dapat dienkripsi memakai dirinya sendiri. Lindungi hak akses file dan jangan unggah ke GitHub. Nama tabel vault, nomor potongan, ukuran ciphertext, path acak berkas, dan metadata filesystem masih terlihat. AES-GCM melindungi kerahasiaan dan integritas nilai, bukan penghapusan/replay keseluruhan backup oleh administrator database.

## Pemasangan baru di XAMPP

1. Gunakan PHP 8.2+ dengan OpenSSL dan ekstensi yang tercantum pada panduan instalasi, **ditambah `pdo_sqlite`**. Aktifkan `pdo_mysql` juga karena penyimpanan tetap pada MySQL/MariaDB.
2. Ekstrak paket lengkap ke `C:\xampp\htdocs\sirukun`. Paket RAR menyertakan vendor. Untuk source GitHub, jalankan `composer install --no-dev --prefer-dist --optimize-autoloader` terlebih dahulu.
3. Start Apache/MySQL. Buka `http://localhost/sirukun/public/install.php` dari komputer XAMPP.
4. Pilih database baru/kosong dan buat akun Administrator. Installer membuat dua kunci acak secara otomatis, skema logis, serta vault terenkripsi; tidak membuat akun/kunci bawaan.
5. Simpan `.env` di tempat aman terpisah, kemudian masuk dan lengkapi data wilayah/warga.

Tidak perlu menjalankan `key:generate` atau membuat `.env` lebih dahulu untuk instalasi browser baru. Jangan menyalin `.env` dari contoh pengujian.

## Memperbarui pemasangan SIRUKUN 1.0 yang sudah berisi data

Lakukan pada komputer XAMPP Anda. Perubahan source tidak otomatis mengubah database yang sudah terpasang. Aplikasi web akan menampilkan 503 bila masih menunjuk koneksi lama atau kunci data belum tersedia.

1. Simpan backup yang dapat dipulihkan dari instalasi lama: database, folder `storage/app/private`, serta `.env`. Simpan backup tersebut secara terlindungi selama proses pemindahan. Hentikan penjadwal dan Apache agar tidak ada permintaan berjalan; MySQL tetap aktif. Jangan menjalankan dua versi aplikasi pada data yang sama.
2. Perbarui source/vendor dengan paket baru, **pertahankan `.env`, `storage/app/private`, dan pengunci instalasi lama**. Jangan menggantinya dengan folder kosong dari paket. Simpan salinan `.env` sebelum penggantian source.
3. Dari folder proyek, jalankan menggunakan PHP XAMPP:

   ```bat
   C:\xampp\php\php.exe artisan config:clear
   C:\xampp\php\php.exe artisan down
   C:\xampp\php\php.exe artisan sirukun:encryption-key
   C:\xampp\php\php.exe artisan sirukun:encrypt-legacy --target=sirukun_encrypted
   ```

   Saat menjalankan `encrypt-legacy`, nilai `DB_DATABASE` masih nama database lama. Nama target harus baru dan berbeda. Akun database memerlukan izin membuat database. Jika target percobaan sebelumnya sudah terisi sebagian, gunakan nama target baru. Sumber tidak ditimpa. Jika `DATA_ENCRYPTION_KEY` sudah ada, lewati `encryption-key`; jangan ganti kuncinya.

4. Migrasi menyalin seluruh record, memeriksa jumlah/hash isi dan relasi, membuka ulang ciphertext untuk verifikasi, lalu mengenkripsi berkas lama secara atomik pada path yang sama. Log lama diarsipkan sebagai berkas terenkripsi; sesi dan cache lama dibersihkan. Semua pengguna harus masuk kembali.
5. Ubah `.env` menjadi:

   ```dotenv
   DB_CONNECTION=encrypted
   DB_DATABASE=sirukun_encrypted
   ```

   Pertahankan `APP_KEY` serta `DATA_ENCRYPTION_KEY` yang ada. Jalankan `artisan config:clear` lalu `artisan migrate --force` untuk memastikan migrasi versi terbaru tercatat. Jangan menjalankan instalasi browser ulang.
6. Jalankan `artisan sirukun:backup` dan simpan hasil unduh terenkripsi setelah pemeriksaan. Periksa login, jumlah warga/KK, pencarian NIK, mutasi, dokumen lama, dan alur surat RT–RW. **Database asal tetap plaintext dan tidak dihapus otomatis.** Setelah salinan baru terbukti benar dan backup terenkripsi tersedia, hapus database asal serta backup SQL/plaintext lama dengan prosedur penyimpanan institusi. Salinan di perangkat lain, recycle bin, atau backup OS perlu ditangani terpisah. Penghapusan biasa tidak menjamin penghapusan forensik media.
7. Jika langkah migrasi berhenti pada konversi berkas, pertahankan kunci yang sama dan jalankan `artisan sirukun:encrypt-files` saat maintenance. Perintah ini dapat diulang dan memverifikasi berkas yang sudah terenkripsi. Jangan kembali ke versi aplikasi lama setelah berkas dikonversi tanpa memulihkan backup versi lama secara lengkap.
8. Setelah pemeriksaan selesai, jalankan `artisan up`, aktifkan Apache, dan nyalakan kembali penjadwal.

## Backup dan restore

Backup baru memakai ekstensi `.sirukun` dan tetap berupa AES-256-GCM ketika diunduh oleh Administrator. Backup database tidak menyertakan isi dokumen; salin juga `storage/app/private` yang sudah terenkripsi, dengan path relatif tetap sama. Backup SQL lama yang sudah dimigrasikan diunduh sebagai `.sql.gcm`; format ini adalah arsip SQL terenkripsi, bukan snapshot baru untuk perintah restore berikut.

Untuk memulihkan backup baru, gunakan instalasi source yang sama, kedua kunci dari pemasangan asal, folder dokumen terenkripsi asal, serta database MySQL baru/kosong. Arahkan `.env` ke database baru dengan `DB_CONNECTION=encrypted`, jalankan maintenance, kemudian:

```bat
php artisan config:clear
php artisan down
php artisan sirukun:restore-encrypted C:\backup\SIRUKUN-xxxx.sirukun
php artisan up
```

Restore menolak database tujuan yang sudah memiliki tabel logis. Kunci salah dan backup rusak ditolak. Jangan menjalankan installer browser sebelum restore karena installer akan mengisi database tujuan. File backup hasil salinan raw disk berlapis enkripsi filesystem; gunakan hasil tombol Unduh terenkripsi untuk perintah restore di atas.

## Batas enkripsi

- Layar, respons unduhan dokumen/laporan yang diminta pengguna berwenang, dan data saat diproses di RAM merupakan plaintext. Gunakan HTTPS untuk akses melalui jaringan. Laporan PDF/Excel yang diunduh pengguna menjadi tanggung jawab penyimpanan perangkat pengguna.
- PDF/Excel dibuat di memori. QR PDF digambar sebagai vektor tanpa cache gambar plaintext. Unggahan diterima lebih dahulu oleh mekanisme temporary upload PHP; gunakan direktori temporary pada volume terenkripsi jika seluruh file sementara OS juga wajib terlindungi. Swap, hibernasi, crash dump, backup sistem operasi, dan log web server berada di luar enkripsi aplikasi; atur enkripsi disk/volume dan kebijakan server sesuai kebutuhan.
- Hash kata sandi tetap satu arah di dalam record terenkripsi. Aplikasi tidak menyediakan dekripsi kata sandi pengguna.
- Ini bukan sertifikasi keamanan atau pengujian penetrasi menyeluruh. Belum diuji langsung pada Windows/XAMPP.

## Menjalankan pengujian

```sh
php vendor/bin/phpunit
```

Untuk mengulangi alur aplikasi melalui koneksi vault terenkripsi pada Windows CMD:

```bat
set SIRUKUN_TEST_ENCRYPTED=1
php vendor/bin/phpunit --filter ApplicationTest
set SIRUKUN_TEST_ENCRYPTED=
```

Pengujian MariaDB opsional membuat/menghapus database acak berawalan `sirukun_crypto_test_`; jalankan hanya pada server lokal untuk pengujian, dengan akun yang berhak membuat database:

```bat
set SIRUKUN_MYSQL_TEST_DSN=mysql:host=127.0.0.1;port=3306
set SIRUKUN_MYSQL_TEST_USER=root
php tests/Integration/MariaDbEncryption.php
```

Pengujian mencakup ciphertext fisik, reopen, dua penulis paralel, kunci salah, dan manipulasi data. Fixture kunci dalam `phpunit.xml` hanya dipakai pengujian dan tidak dipakai installer/pemasangan nyata.
