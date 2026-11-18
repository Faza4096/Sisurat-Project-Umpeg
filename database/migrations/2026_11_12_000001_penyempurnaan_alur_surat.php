<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyempurnaan alur kerja surat.
 * Migrasi lama tidak diubah agar riwayat tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('must_change_password')->default(false);
            $t->boolean('aktif')->default(true);
            $t->timestamp('password_diubah_pada')->nullable();
        });

        Schema::table('surat_masuk', function (Blueprint $t) {
            // status dijadikan string agar alur bisa bertambah (diarsipkan, revisi, dst.)
            $t->string('status', 20)->default('diterima')->change();
            $t->string('lokasi_arsip')->nullable();
            $t->timestamp('diarsipkan_pada')->nullable();
            $t->foreignId('diarsipkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->softDeletes();
        });

        Schema::table('surat_keluar', function (Blueprint $t) {
            $t->string('status', 20)->default('draf')->change();
            $t->foreignId('surat_masuk_id')->nullable()->constrained('surat_masuk')->nullOnDelete();
            $t->string('file_final_path')->nullable();
            $t->string('file_final_nama')->nullable();
            $t->date('tgl_kirim')->nullable();
            $t->string('penerima')->nullable();
            $t->string('metode_kirim', 50)->nullable();
            $t->string('no_bukti_kirim')->nullable();
            $t->text('catatan_revisi')->nullable();
            $t->timestamp('diajukan_pada')->nullable();
            $t->timestamp('disetujui_pada')->nullable();
            $t->softDeletes();
        });

        Schema::table('disposisi', function (Blueprint $t) {
            $t->softDeletes();
        });

        Schema::table('disposisi_tujuan', function (Blueprint $t) {
            $t->text('catatan')->nullable();
            $t->string('bukti_path')->nullable();
            $t->string('bukti_nama')->nullable();
            $t->timestamp('diunggah_pada')->nullable();
            $t->foreignId('selesai_oleh')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('lampiran', function (Blueprint $t) {
            $t->string('jenis', 20)->default('scan');
            $t->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lampiran', function (Blueprint $t) {
            $t->dropConstrainedForeignId('diunggah_oleh');
            $t->dropColumn('jenis');
        });

        Schema::table('disposisi_tujuan', function (Blueprint $t) {
            $t->dropConstrainedForeignId('selesai_oleh');
            $t->dropColumn(['catatan', 'bukti_path', 'bukti_nama', 'diunggah_pada']);
        });

        Schema::table('disposisi', function (Blueprint $t) {
            $t->dropSoftDeletes();
        });

        Schema::table('surat_keluar', function (Blueprint $t) {
            $t->dropConstrainedForeignId('surat_masuk_id');
            $t->dropColumn([
                'file_final_path', 'file_final_nama', 'tgl_kirim', 'penerima',
                'metode_kirim', 'no_bukti_kirim', 'catatan_revisi',
                'diajukan_pada', 'disetujui_pada',
            ]);
            $t->dropSoftDeletes();
        });

        Schema::table('surat_masuk', function (Blueprint $t) {
            $t->dropConstrainedForeignId('diarsipkan_oleh');
            $t->dropColumn(['lokasi_arsip', 'diarsipkan_pada']);
            $t->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['must_change_password', 'aktif', 'password_diubah_pada']);
        });
    }
};
