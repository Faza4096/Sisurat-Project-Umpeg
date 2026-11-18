<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisposisiController;
use App\Http\Controllers\InstansiController;
use App\Http\Controllers\KlasifikasiController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogAktivitasController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PasswordWajibController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuratKeluarController;
use App\Http\Controllers\SuratMasukController;
use App\Http\Controllers\UnitKerjaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
    | Wajib ganti kata sandi default
    */
    Route::get('ganti-sandi-wajib', [PasswordWajibController::class, 'edit'])->name('password.wajib.edit');
    Route::put('ganti-sandi-wajib', [PasswordWajibController::class, 'update'])->name('password.wajib.update');

    /*
    | Surat masuk
    */
    Route::get('surat-masuk', [SuratMasukController::class, 'index'])->name('surat-masuk.index');
    Route::get('surat-masuk/{suratMasuk}/balas', [SuratMasukController::class, 'balas'])
        ->whereNumber('suratMasuk')->name('surat-masuk.balas');
    Route::get('surat-masuk/{suratMasuk}', [SuratMasukController::class, 'show'])
        ->whereNumber('suratMasuk')->name('surat-masuk.show');

    Route::middleware('role:staf')->group(function () {
        Route::get('surat-masuk/create', [SuratMasukController::class, 'create'])->name('surat-masuk.create');
        Route::post('surat-masuk', [SuratMasukController::class, 'store'])->name('surat-masuk.store');
        Route::get('surat-masuk/{suratMasuk}/edit', [SuratMasukController::class, 'edit'])->name('surat-masuk.edit');
        Route::put('surat-masuk/{suratMasuk}', [SuratMasukController::class, 'update'])->name('surat-masuk.update');
        Route::delete('surat-masuk/{suratMasuk}', [SuratMasukController::class, 'destroy'])->name('surat-masuk.destroy');
        Route::get('surat-masuk/{suratMasuk}/permanen', [SuratMasukController::class, 'formHapusPermanen'])
            ->whereNumber('suratMasuk')->name('surat-masuk.hapus-permanen.form');
        Route::delete('surat-masuk/{suratMasuk}/permanen', [SuratMasukController::class, 'hapusPermanen'])
            ->whereNumber('suratMasuk')->name('surat-masuk.hapus-permanen');
        Route::patch('surat-masuk/{suratMasuk}/arsipkan', [SuratMasukController::class, 'arsipkan'])->name('surat-masuk.arsipkan');
        Route::post('surat-masuk/{suratMasuk}/lampiran', [SuratMasukController::class, 'unggahLampiran'])->name('surat-masuk.lampiran.store');
    });

    /*
    | Surat keluar
    */
    Route::get('surat-keluar', [SuratKeluarController::class, 'index'])->name('surat-keluar.index');
    Route::get('surat-keluar/{suratKeluar}/file-final', [SuratKeluarController::class, 'unduhFinal'])
        ->whereNumber('suratKeluar')->name('surat-keluar.file-final');
    Route::get('surat-keluar/{suratKeluar}', [SuratKeluarController::class, 'show'])
        ->whereNumber('suratKeluar')->name('surat-keluar.show');

    Route::middleware('role:staf')->group(function () {
        Route::get('surat-keluar/create', [SuratKeluarController::class, 'create'])->name('surat-keluar.create');
        Route::post('surat-keluar', [SuratKeluarController::class, 'store'])->name('surat-keluar.store');
        Route::get('surat-keluar/{suratKeluar}/edit', [SuratKeluarController::class, 'edit'])->name('surat-keluar.edit');
        Route::put('surat-keluar/{suratKeluar}', [SuratKeluarController::class, 'update'])->name('surat-keluar.update');
        Route::delete('surat-keluar/{suratKeluar}', [SuratKeluarController::class, 'destroy'])->name('surat-keluar.destroy');
        Route::get('surat-keluar/{suratKeluar}/permanen', [SuratKeluarController::class, 'formHapusPermanen'])
            ->whereNumber('suratKeluar')->name('surat-keluar.hapus-permanen.form');
        Route::delete('surat-keluar/{suratKeluar}/permanen', [SuratKeluarController::class, 'hapusPermanen'])
            ->whereNumber('suratKeluar')->name('surat-keluar.hapus-permanen');
        Route::post('surat-keluar/{suratKeluar}/ajukan', [SuratKeluarController::class, 'ajukan'])->name('surat-keluar.ajukan');
        Route::post('surat-keluar/{suratKeluar}/kirim', [SuratKeluarController::class, 'kirim'])->name('surat-keluar.kirim');
        Route::post('surat-keluar/{suratKeluar}/arsipkan', [SuratKeluarController::class, 'arsipkan'])->name('surat-keluar.arsipkan');
    });

    Route::post('surat-keluar/{suratKeluar}/acc', [SuratKeluarController::class, 'acc'])
        ->middleware('role:staf')->name('surat-keluar.acc');
    Route::post('surat-keluar/{suratKeluar}/tolak', [SuratKeluarController::class, 'tolak'])
        ->middleware('role:staf')->name('surat-keluar.tolak');

    /*
    | Disposisi & tindak lanjut
    */
    Route::get('disposisi', [DisposisiController::class, 'index'])->name('disposisi.index');
    Route::post('surat-masuk/{suratMasuk}/disposisi', [DisposisiController::class, 'store'])
        ->middleware('role:staf')->name('disposisi.store');
    Route::get('surat-masuk/{suratMasuk}/acc', [DisposisiController::class, 'formAcc'])
        ->whereNumber('suratMasuk')->middleware('role:staf')->name('disposisi.acc.form');
    Route::post('surat-masuk/{suratMasuk}/acc', [DisposisiController::class, 'acc'])
        ->whereNumber('suratMasuk')->middleware('role:staf')->name('disposisi.acc');
    Route::get('disposisi/cetak/pdf', [DisposisiController::class, 'cetak'])->name('disposisi.cetak');
    Route::get('disposisi/{disposisi}/kartu', [DisposisiController::class, 'kartu'])
        ->whereNumber('disposisi')->name('disposisi.kartu');
    Route::get('disposisi/{disposisi}/pdf', [DisposisiController::class, 'pdf'])
        ->whereNumber('disposisi')->name('disposisi.pdf');
    Route::delete('disposisi/{disposisi}', [DisposisiController::class, 'destroy'])
        ->middleware('role:staf')->name('disposisi.destroy');
    Route::patch('disposisi-tujuan/{tujuan}', [DisposisiController::class, 'ubahStatus'])->name('tujuan.status');
    Route::get('disposisi-tujuan/{tujuan}/bukti', [DisposisiController::class, 'unduhBukti'])->name('tujuan.bukti.unduh');
    Route::post('disposisi-tujuan/{tujuan}/bukti', [DisposisiController::class, 'unggahBukti'])->name('tujuan.bukti');
    Route::delete('disposisi-tujuan/{tujuan}/bukti', [DisposisiController::class, 'hapusBukti'])->name('tujuan.bukti.hapus');

    /*
    | Laporan & ekspor
    */
    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/tindak-lanjut', [LaporanController::class, 'tindakLanjut'])->name('laporan.tindak-lanjut');
    Route::get('laporan/ekspor', [LaporanController::class, 'ekspor'])->name('laporan.ekspor');
    Route::get('laporan/pdf', [LaporanController::class, 'pdf'])->name('laporan.pdf');

    /*
    | Notifikasi
    */
    Route::get('notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::get('notifikasi/{id}', [NotifikasiController::class, 'baca'])->name('notifikasi.baca');
    Route::post('notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');

    /*
    | Data master & log aktivitas (administrator)
    */
    Route::middleware('role:admin')->group(function () {
        Route::resource('unit-kerja', UnitKerjaController::class)->except('show');
        Route::resource('klasifikasi', KlasifikasiController::class)->except('show');
        Route::resource('instansi', InstansiController::class)->except('show');
        Route::resource('pengguna', PenggunaController::class)->except('show');
        Route::get('log-aktivitas', [LogAktivitasController::class, 'index'])->name('log.index');
        Route::get('pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan.edit');
        Route::put('pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
    });

    /*
    | Lampiran & profil
    */
    Route::get('lampiran/{lampiran}', [LampiranController::class, 'unduh'])->name('lampiran.unduh');
    Route::delete('lampiran/{lampiran}', [SuratMasukController::class, 'hapusLampiran'])->name('lampiran.hapus');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__ . '/auth.php';
