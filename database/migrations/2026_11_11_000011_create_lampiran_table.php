<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('lampiran', function (Blueprint $t) {
            $t->id();
            $t->morphs('surat'); // surat_type + surat_id (SuratMasuk / SuratKeluar)
            $t->string('nama_file');
            $t->string('path');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('lampiran'); }
};
