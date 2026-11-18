<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('disposisi_tujuan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('disposisi_id')->constrained('disposisi')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('unit_kerja');
            $t->enum('status_tindak_lanjut', ['belum', 'diproses', 'selesai'])->default('belum');
            $t->timestamp('tgl_selesai')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('disposisi_tujuan'); }
};
