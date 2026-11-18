<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('surat_keluar', function (Blueprint $t) {
            $t->id();
            $t->string('no_surat')->unique();
            $t->date('tgl_surat');
            $t->foreignId('instansi_id')->constrained('instansi');
            $t->foreignId('klasifikasi_id')->nullable()->constrained('klasifikasi')->nullOnDelete();
            $t->foreignId('unit_id')->constrained('unit_kerja');
            $t->string('hal');
            $t->enum('sifat', ['biasa', 'segera', 'penting', 'rahasia'])->default('biasa');
            $t->string('lampiran_ket')->default('-');
            $t->text('isi_ringkas')->nullable();
            $t->enum('status', ['draf', 'menunggu_ttd', 'terkirim'])->default('draf');
            $t->foreignId('penandatangan_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('dibuat_oleh')->constrained('users');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('surat_keluar'); }
};
