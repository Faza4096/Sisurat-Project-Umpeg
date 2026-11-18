<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('surat_masuk', function (Blueprint $t) {
            $t->id();
            $t->string('no_agenda')->unique();
            $t->string('no_surat');
            $t->date('tgl_surat');
            $t->date('tgl_diterima');
            $t->foreignId('instansi_id')->constrained('instansi');
            $t->foreignId('klasifikasi_id')->nullable()->constrained('klasifikasi')->nullOnDelete();
            $t->string('perihal');
            $t->enum('sifat', ['biasa', 'segera', 'penting', 'rahasia'])->default('biasa');
            $t->enum('status', ['diterima', 'didisposisi', 'diproses', 'selesai'])->default('diterima');
            $t->foreignId('dicatat_oleh')->constrained('users');
            $t->timestamps();
            $t->index('tgl_diterima');
        });
    }
    public function down(): void { Schema::dropIfExists('surat_masuk'); }
};
