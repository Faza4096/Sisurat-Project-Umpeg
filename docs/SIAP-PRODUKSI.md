# Checklist Kesiapan Produksi — SiSurat

Daftar periksa sebelum aplikasi dipakai di lingkungan instansi. Semua perintah dijalankan dari
folder proyek. Nilai rahasia (kunci aplikasi, sandi basis data, sandi SMTP) hanya ditulis di
`.env` server, tidak pernah di dokumen, repositori, atau halaman web.

---

## 1. Environment aplikasi

- [ ] `APP_ENV=production` — mematikan perilaku khusus pengembangan.
- [ ] `APP_DEBUG=false` — galat tidak lagi menampilkan stack trace, query, dan isi variabel.
- [ ] `APP_KEY` terisi. Untuk instalasi baru: `php artisan key:generate --force`
      (mengganti kunci akan membatalkan sesi dan data terenkripsi lama — jangan dilakukan
      pada instalasi yang sudah berjalan).
- [ ] `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`.
- [ ] Setelah `.env` final:

  ```sh
  php artisan optimize:clear
  php artisan optimize
  php artisan about --only=environment   # pastikan Environment=production, Debug Mode=DISABLED
  ```

- [ ] `storage/` dan `bootstrap/cache/` dapat ditulis proses web server.
- [ ] `.env` tidak ikut repositori (sudah masuk `.gitignore`).

## 2. URL dan HTTPS

- [ ] `APP_URL=https://surat.instansi.go.id` — dipakai saat membuat tautan di email reset sandi,
      jadi harus URL publik yang benar, bukan `localhost`.
- [ ] Sertifikat TLS terpasang dan seluruh permintaan `http://` dialihkan ke `https://`
      (redirect di web server, bukan hanya di aplikasi).
- [ ] `SESSION_SECURE_COOKIE=true` — cookie sesi hanya dikirim lewat HTTPS.
- [ ] `SESSION_DOMAIN=surat.instansi.go.id`, `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`
      bila ingin isi sesi ikut terenkripsi.
- [ ] Bila aplikasi berada di belakang reverse proxy/load balancer, daftarkan proxy tepercaya
      pada `bootstrap/app.php`:

  ```php
  ->withMiddleware(function (Middleware $middleware): void {
      $middleware->trustProxies(at: ['10.0.0.10']); // IP proxy, jangan '*'
      // ...
  })
  ```

  Tanpa ini Laravel membaca skema/IP dari proxy, sehingga tautan bisa kembali ke `http`.

## 3. Basis data dan penyimpanan

- [ ] Kredensial MySQL produksi terisi (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
      `DB_PASSWORD`). Pengguna basis data hanya diberi hak yang dibutuhkan.
- [ ] `php artisan migrate --force` dijalankan (tanpa konfirmasi interaktif).
- [ ] Berkas unggahan (lampiran, surat final, bukti, kartu disposisi) berada di `storage/app`
      yang **tidak** terekspos web server; unduhan hanya lewat rute ber-otorisasi.
- [ ] Cadangan rutin untuk basis data dan `storage/app`; uji pemulihan sekali.
- [ ] `QUEUE_CONNECTION=database` aman dibiarkan: notifikasi aplikasi dikirim langsung, tidak
      ada job yang menunggu worker, jadi worker antrean belum wajib.

## 4. SMTP untuk email reset sandi

- [ ] Isi `MAIL_MAILER=smtp` beserta `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, dan
      `MAIL_PASSWORD`. Port 587 (STARTTLS) umumnya cukup dengan `MAIL_PORT=587`; untuk server
      SMTPS port 465 isi `MAIL_SCHEME=smtps`.
- [ ] `MAIL_FROM_ADDRESS` memakai alamat resmi instansi dan boleh mengirim (SPF/DKIM domain
      pengirim sudah diatur agar email tidak masuk spam).
- [ ] Uji kirim dari server:

  ```sh
  php artisan optimize:clear
  php artisan tinker --execute="Mail::raw('Uji SMTP SiSurat', fn (\$m) => \$m->to('alamat-uji@instansi.go.id')->subject('Uji SMTP'));"
  ```

- [ ] Uji alur nyata: buka `/forgot-password`, minta tautan, pastikan email diterima dan tautan
      mengarah ke `https://…/reset-password/…` (bukan `localhost`).
- [ ] Saat pengembangan `MAIL_MAILER=log` hanya menulis email ke `storage/logs/laravel.log`;
      nilai itu **tidak boleh** terbawa ke produksi.

## 5. Akun administrator pertama

Seeder tidak membuat akun apa pun; administrator pertama dibuat dari terminal agar tidak ada
sandi bawaan yang diketahui orang lain.

- [ ] Data master tersedia:

  ```sh
  php artisan migrate --seed --force
  ```

- [ ] Buat administrator (sandi diketik tersembunyi, minimal 10 karakter, memuat huruf dan angka):

  ```sh
  php artisan sisurat:buat-admin
  ```

  Untuk otomatisasi, argumen dan opsi tersedia — hindari `--sandi` di server produksi karena
  nilainya tersimpan di riwayat shell:

  ```sh
  php artisan sisurat:buat-admin kepala@instansi.go.id --nama="Nama Lengkap"
  ```

- [ ] Masuk lewat `/login` dengan akun tersebut (tidak diminta ganti sandi karena sandinya sudah
      dipilih sendiri).
- [ ] Dari menu **Pengguna**, buat akun staf. Sandi sementara akun staf wajib diganti saat login
      pertama (middleware `WajibGantiSandi`).
- [ ] Hapus atau nonaktifkan akun percobaan sebelum serah terima.

## 6. Halaman login bersih dari kredensial

- [ ] Tidak ada email/sandi contoh di antarmuka:

  ```sh
  grep -rni "akun demo\|admin@sisurat\|staf@sisurat" resources/views
  ```

  Keluaran harus kosong.

- [ ] Buka `/login` di peramban (mode privat) dan pastikan hanya ada formulir, tautan
      "Lupa kata sandi?", dan catatan "Akun pegawai hanya dibuat administrator instansi".
- [ ] Login gagal 5 kali berturut-turut dari satu IP + email menghasilkan pesan tunggu
      (rate limit bawaan `LoginRequest`).
- [ ] Akun yang dinonaktifkan administrator tidak bisa masuk dan sesinya langsung diputus
      (middleware `AkunAktif`).

## 7. Operasional

- [ ] Cron scheduler aktif agar pengingat disposisi terkirim:

  ```
  * * * * * cd /var/www/sisurat && php artisan schedule:run >> /dev/null 2>&1
  ```

- [ ] Log aplikasi terpantau (`storage/logs/laravel.log`) dengan rotasi, dan `LOG_LEVEL`
      disetel `warning` atau `error` di produksi.
- [ ] Uji asap setelah deploy:
  - login administrator dan staf;
  - catat satu surat masuk, ACC & disposisi, unduh PDF kartu;
  - buat surat keluar, ajukan, ACC, unggah berkas final, kirim, arsipkan;
  - ekspor laporan CSV dan PDF;
  - minta tautan reset sandi dan pastikan email diterima.
- [ ] `php artisan test` lulus sebelum artefak dirilis (dijalankan di CI/staging, bukan di server
      produksi dengan data asli).
