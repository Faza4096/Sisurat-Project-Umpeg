# SiSurat — Alur, Status, dan Hak Akses

Sistem otomatisasi registrasi surat Pemerintah Kabupaten Bogor. Dokumen ini menjelaskan
alur kerja surat masuk dan surat keluar, daftar status, matriks hak akses per peran, serta
catatan operasional (notifikasi, jadwal, ekspor, dan keamanan).

---

## 1. Peran pengguna

Sistem hanya memakai **dua peran**: administrator dan staf. Staf adalah operator tunggal
tata naskah — ia mencatat surat masuk dan surat keluar, memberi ACC sekaligus membuat kertas
disposisi, menyetujui atau meminta revisi surat keluar, mengirim, dan mengarsipkan.

| Peran (`roles.nama`) | Sebutan | Tugas utama |
| --- | --- | --- |
| `admin` | Administrator | Kelola pengguna, unit kerja, klasifikasi, instansi, pengaturan instansi, dan log aktivitas. Akses penuh atas surat, disposisi, dan laporan. |
| `staf` | Staf / operator | Catat surat masuk & surat keluar, ACC & disposisi, setujui/tolak surat keluar, unggah berkas final, kirim, arsipkan, serta memperbarui tindak lanjut dan mengunggah bukti. |

Peran lama `petugas_tu` dan `pimpinan` sudah dihapus. Migrasi
`2026_11_17_000001_sederhanakan_peran_admin_dan_staf` memindahkan pemilik peran lama ke
`staf` sebelum menghapus kedua peran tersebut. Middleware `role:staf` menjaga seluruh rute
operasional, dan administrator selalu diloloskan `EnsureRole`.

Akun **hanya dibuat administrator** melalui menu *Pengguna*. Pendaftaran publik dihapus.
Akun baru memakai kata sandi sementara dan **wajib menggantinya saat login pertama**
(middleware `WajibGantiSandi`). Penghapusan akun sendiri dinonaktifkan.

Administrator pertama dibuat dari terminal dengan `php artisan sisurat:buat-admin`; seeder tidak
membuat akun apa pun sehingga tidak ada sandi bawaan yang diketahui orang lain.

---

## 2. Alur surat masuk

```
Staf catat + scan  →  [diterima]
      ↓ staf ACC & disposisi (instruksi, batas waktu, unit tujuan)
[didisposisi]  →  unit tujuan menindaklanjuti
      ↓ ada tujuan berstatus diproses / selesai
[diproses]
      ↓ semua tujuan disposisi = selesai
[selesai]
      ↓ staf mencatat lokasi arsip fisik
[diarsipkan]
```

### Status surat masuk

| Status | Arti | Pemicu |
| --- | --- | --- |
| `diterima` | Surat tercatat, belum didisposisi. | Staf menyimpan surat (nomor agenda otomatis `AGD/0001/XI/2026`). |
| `didisposisi` | Disposisi sudah dibuat, semua unit tujuan masih `belum`. | Staf menekan *ACC & Disposisi*. |
| `diproses` | Minimal satu unit tujuan berstatus `diproses` atau `selesai`. | Unit tujuan memperbarui tindak lanjut / mengunggah bukti. |
| `selesai` | Seluruh unit tujuan berstatus `selesai`. | Unit tujuan terakhir menyatakan selesai. |
| `diarsipkan` | Surat selesai dan lokasi arsip fisik dicatat. | Staf menekan *Arsipkan* pada surat berstatus `selesai`. |

Aturan penting: status surat **tidak lagi** menjadi `diproses` bila semua unit tujuan masih
`belum`. Perhitungan dilakukan terpusat di `DisposisiController::hitungStatusSurat()`.

### Tindak lanjut disposisi (`disposisi_tujuan.status_tindak_lanjut`)

| Status | Arti |
| --- | --- |
| `belum` | Unit tujuan belum memulai. |
| `diproses` | Sedang ditindaklanjuti (otomatis saat bukti diunggah pertama kali). |
| `selesai` | Selesai; sistem mencatat `tgl_selesai` dan `selesai_oleh`. |

Status tindak lanjut dan unggahan bukti terbuka untuk pengguna dari **unit tujuan** maupun
staf/administrator (`DisposisiTujuanPolicy::ubah`). Staf memegang seluruh unit karena dialah
operator yang memantau tindak lanjut; administrator selalu boleh.

---

## 3. Alur surat keluar

```
Staf buat draf + nomor otomatis  →  [draf]
      ↓ staf mengajukan
[menunggu_ttd]  → staf (operator lain) memutuskan
      ├─ ACC → [disetujui]
      └─ tolak / minta revisi (+ catatan) → [revisi] → staf perbaiki → ajukan lagi
[disetujui]
      ↓ staf unggah berkas final PDF + catat pengiriman (tanggal, penerima, metode, no. bukti)
[terkirim]
      ↓ staf arsipkan
[diarsipkan]
```

### Status surat keluar

| Status | Arti | Pemicu |
| --- | --- | --- |
| `draf` | Surat dibuat, belum diajukan. | Staf menyimpan surat baru. |
| `menunggu_ttd` | Menunggu keputusan. | Staf menekan *Ajukan*. |
| `revisi` | Dikembalikan dengan catatan. | *Tolak / Minta Revisi* (catatan wajib). |
| `disetujui` | Disetujui & ditandatangani (tercatat `penandatangan_id`, `disetujui_pada`). | *ACC Surat* — **hanya dari** `menunggu_ttd`. |
| `terkirim` | Berkas final tersimpan dan pengiriman tercatat. | Staf menyimpan form pengiriman + PDF final. |
| `diarsipkan` | Arsip dinamis. | Staf menekan *Arsipkan* dari status `terkirim`. |

Validasi alur dijaga `SuratKeluarPolicy` (`ajukan`, `setujui`/`acc`, `tolak`, `kirim`,
`arsipkan`), bukan hanya oleh tampilan tombol. Penomoran memakai kode unit kerja; unit
dengan kode `TU` adalah unit tata usaha yang menerima kartu disposisi hasil ACC.

---

## 4. Relasi surat masuk ↔ surat keluar

`surat_keluar.surat_masuk_id` menyimpan relasi balasan. Dari detail surat masuk, tombol
**Buat Balasan** membuka form surat keluar dengan instansi, perihal, dan isi ringkas yang
sudah terisi otomatis. Daftar balasan tampil pada halaman surat masuk.

---

## 5. Matriks hak akses

Keterangan: ✅ penuh, 🚫 tidak boleh.

| Tindakan | admin | staf |
| --- | --- | --- |
| Lihat daftar & detail surat masuk (termasuk `rahasia`) | ✅ | ✅ |
| Catat / ubah surat masuk | ✅ | ✅ |
| Hapus surat masuk (soft delete) | ✅ | ✅ |
| Hapus permanen surat + disposisi, semua status (tidak dapat dibatalkan) | ✅ | 🚫 |
| Unduh lampiran surat | ✅ | ✅ |
| Kelola (unggah/hapus) lampiran | ✅ | ✅ |
| ACC & buat disposisi surat masuk | ✅ | ✅ |
| Batalkan disposisi | ✅ | ✅ |
| Ubah status tindak lanjut | ✅ | ✅ semua unit |
| Unggah bukti tindak lanjut | ✅ | ✅ semua unit |
| Arsipkan surat masuk | ✅ | ✅ |
| Buat / ubah / hapus draf surat keluar | ✅ | ✅ |
| Ajukan surat keluar | ✅ | ✅ |
| Setujui / tolak surat keluar | ✅ | ✅ |
| Kirim (unggah final + data kirim) | ✅ | ✅ |
| Arsipkan surat keluar | ✅ | ✅ |
| Lihat surat keluar (termasuk `rahasia`) | ✅ | ✅ |
| Laporan & ekspor | ✅ | ✅ |
| Kelola pengguna & data master | ✅ | 🚫 |
| Log aktivitas & pengaturan instansi | ✅ | 🚫 |

Catatan `rahasia`: sifat surat tetap tersimpan dan ditandai pada tampilan, tetapi tidak lagi
membatasi akses — staf dan administrator dapat membuka seluruh surat. Batas akses yang
tersisa hanya administratif: pengguna, data master, log, dan pengaturan instansi.

### Aturan hapus

- Surat masuk: **tidak boleh** dihapus bila sudah pernah didisposisi atau sudah `diarsipkan`.
- Surat keluar: hanya boleh dihapus pada status `draf` atau `revisi`.
- Penghapusan memakai **SoftDeletes** (`deleted_at`) untuk `surat_masuk`, `surat_keluar`, dan
  `disposisi`; berkas di disk tidak dihapus agar jejak arsip tetap utuh.
- Pengguna yang memiliki riwayat surat hanya **dinonaktifkan** (`users.aktif = false`).
- **Hapus permanen** dibuka dari kartu *Zona berbahaya* yang terpisah di bawah detail surat, lalu
  melewati halaman konfirmasi tersendiri: nomor surat harus diketik persis (nomor agenda untuk
  surat masuk, nomor surat untuk surat keluar) sebelum tombol aktif. Hanya **administrator** yang
  boleh melakukannya, untuk surat apa pun statusnya — termasuk yang sudah didisposisi, diproses,
  atau diarsipkan. Yang ikut terhapus: seluruh disposisi
  dan tujuannya, lampiran beserta berkasnya, kartu disposisi PDF, bukti tindak lanjut, dan berkas
  final surat keluar. Berkas di disk ikut dibuang sehingga tidak dapat dipulihkan; gunakan
  *Hapus* biasa bila hanya ingin memindahkan ke arsip terhapus. Surat keluar yang membalas surat
  masuk tetap ada dan relasinya (`surat_masuk_id`) menjadi kosong. Log aktivitas mencatat
  `surat_masuk.hapus_permanen` / `surat_keluar.hapus_permanen`.

---

## 6. Notifikasi

Notifikasi dikirim lewat kanal **database** (lonceng di topbar, halaman `/notifikasi`) dan
**email**.

| Notifikasi | Penerima | Pemicu |
| --- | --- | --- |
| `DisposisiBaru` | Pengguna unit tujuan | Disposisi disimpan (surat masuk, atau kartu hasil ACC surat keluar). |
| `MenungguTandaTangan` | Staf lain selain pengaju | Staf mengajukan surat keluar. |
| `MenungguTandaTangan` (mode revisi) | Pembuat surat | Staf menolak / meminta revisi. |
| `StatusTindakLanjut` | Pengguna unit tujuan | Staf memperbarui status tindak lanjut. |
| `PengingatBatasWaktu` | Pengguna unit tujuan | Command `disposisi:ingatkan`. |

Karena satu peran memegang seluruh alur, notifikasi pengajuan ditujukan ke operator staf
lain (`User::scopeOperator`) agar keputusan ACC tidak hanya masuk ke akun pengaju sendiri.

Jadwal pengingat ada di `routes/console.php`:

```php
Schedule::command('disposisi:ingatkan')->dailyAt('07:30')->weekdays();
```

Command dapat dijalankan manual (mis. untuk uji):

```sh
php artisan disposisi:ingatkan --hari=2
```

Agar jadwal berjalan di server, cron Laravel scheduler harus aktif:

```
* * * * * cd /path/sisurat && php artisan schedule:run >> /dev/null 2>&1
```

Email memakai `MAIL_MAILER` pada `.env`. Untuk produksi isi SMTP instansi; pada lingkungan
pengembangan `log` cukup (email ditulis ke `storage/logs/laravel.log`).

---

## 7. Laporan & ekspor

Menu **Laporan** (`/laporan`) menyediakan:

- Filter `dari`–`sampai`, `instansi_id`, `klasifikasi_id`, `status`, dan `jenis` (masuk/keluar).
- **Ekspor Excel**: CSV UTF-8 dengan pemisah titik koma (langsung rapi di Excel Indonesia).
- **Ekspor PDF**: dibuat dengan dompdf (`dompdf/dompdf`).
- **Laporan Tindak Lanjut** (`/laporan/tindak-lanjut`): status per unit, batas waktu,
  keterlambatan (merah), catatan, dan tautan bukti.

Semua laporan mematuhi `SuratMasuk::scopeTerlihatOleh()` / `SuratKeluar::scopeTerlihatOleh()`.
Untuk peran `staf` dan `admin` scope tersebut mengembalikan seluruh data.

---

## 8. Keamanan & konfigurasi

- Basis data memakai **MySQL** (`DB_CONNECTION=mysql`), bukan SQLite. `database/database.sqlite`
  tidak lagi disertakan; entri `/database/*.sqlite` masuk `.gitignore`.
- Semua perubahan data memakai `@csrf`; unggahan dibatasi tipe (`pdf,jpg,jpeg,png`) dan ukuran.
- Berkas lampiran, surat final, dan bukti disimpan di disk privat `storage/app` dan hanya bisa
  diunduh lewat rute ber-otorisasi (`lampiran.unduh`, `surat-keluar.file-final`,
  `tujuan.bukti.unduh`) — tidak ada akses langsung via URL publik.
- Kata sandi sementara + `must_change_password` mencegah akun buatan administrator dipakai
  sebelum sandinya diganti.
- Log aktivitas (`log_aktivitas`) mencatat registrasi, disposisi, perubahan status, pengiriman,
  arsip, unggahan, dan penghapusan.

### Dashboard

`DashboardController` menampilkan antrean yang sama untuk `staf` dan `admin`: **Surat masuk
menunggu ACC**, **Surat keluar menunggu ACC**, **Sudah di-ACC, belum dikirim**, dan **Belum
diarsipkan**. Bagian "belum diberi disposisi" dilewati karena surat `diterima` sudah muncul di
antrean ACC. Administrator mendapat tambahan ringkasan akun dan log aktivitas terbaru.

### ACC & kartu disposisi

- **Surat masuk** — staf membuka surat lalu menekan **ACC & Disposisi**
  (`GET/POST /surat-masuk/{id}/acc`). Form terisi otomatis (dari, perihal, nomor, tanggal,
  klasifikasi); staf mengisi indeks, tanggal penyelesaian, instruksi, dan unit tujuan.
  Simpan → disposisi dibuat, status surat menjadi `didisposisi`, PDF kartu diterbitkan.
- **Surat keluar** — dari status `menunggu_ttd`, tombol **ACC Surat** di halaman detail
  (`POST /surat-keluar/{id}/acc`) menetapkan penanda tangan, mengubah status menjadi
  `disetujui`, dan membuat kartu disposisi untuk unit berkode `TU` (instruksi bawaan:
  sampaikan, catat pengiriman, arsipkan).
- **PDF kartu** dibuat otomatis oleh `App\Services\KartuDisposisiService` (dompdf) setiap kali
  disposisi dibuat atau diubah, disimpan privat di `storage/app/kartu-disposisi`.
  Kop memakai identitas dari menu **Pengaturan Instansi** (admin): nama, alamat, telepon,
  situs, dan lambang (lambang dikirim sebagai data URI agar ikut tercetak).
- **Unduh/cetak** — `GET /disposisi/{id}/pdf` untuk satu kartu, `GET /disposisi/cetak/pdf?ids[]=…`
  untuk banyak kartu dalam satu PDF (checkbox di halaman Disposisi), dan pratinjau
  `GET /disposisi/{id}/kartu` dengan tombol Cetak (dialog cetak peramban).
- **Hak akses** — ACC hanya staf/administrator; unduh kartu: staf, admin, dan unit tujuan
  (`DisposisiPolicy::unduhKartu`); unit lain mendapat 403.
- **Notifikasi** — `DisposisiBaru` dikirim ke pengguna unit tujuan lewat basis data dan surel,
  dengan kartu disposisi PDF terlampir.
- Kartu disegarkan otomatis saat unit mengubah status tindak lanjut atau mengunggah bukti.

### Ringkasan perintah

```sh
php artisan migrate --seed       # skema + data master (unit, klasifikasi, instansi, peran)
php artisan sisurat:buat-admin   # administrator pertama (interaktif)
php artisan disposisi:ingatkan   # kirim pengingat disposisi
php artisan test                 # seluruh tes fitur
```

### Instalasi awal

1. `php artisan migrate --seed` — membuat tabel dan data master. **Tidak ada akun** yang dibuat.
2. `php artisan sisurat:buat-admin` — menanyakan surel, nama, dan kata sandi administrator
   pertama. Sandi diketik tanpa echo, diulang untuk konfirmasi, dan tunduk pada aturan minimum
   10 karakter dengan huruf serta angka. Untuk otomatisasi, opsi tersedia:

   ```sh
   php artisan sisurat:buat-admin kepala@bogorkab.go.id --nama="Nama Lengkap"
   php artisan sisurat:buat-admin kepala@bogorkab.go.id --nama="Nama Lengkap" --sandi="SandiKuat2026"
   ```

   Opsi `--sandi` kurang aman karena tersimpan di riwayat shell; tanpa opsi itu sandi ditanyakan
   secara tersembunyi.
3. Masuk lewat `/login` dengan akun tersebut, lalu tambahkan akun staf dari menu **Pengguna**.
   Sandi sementara akun staf wajib diganti saat login pertama.

Aturan sandi minimum 10 karakter (huruf dan angka) berlaku untuk pembuatan administrator,
penggantian wajib, reset sandi, dan perubahan dari profil.
