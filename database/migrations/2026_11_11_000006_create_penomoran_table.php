<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('penomoran', function (Blueprint $t) {
            $t->id();
            $t->foreignId('unit_id')->constrained('unit_kerja');
            $t->enum('jenis', ['agenda', 'keluar']);
            $t->unsignedSmallInteger('tahun');
            $t->unsignedInteger('terakhir')->default(0);
            $t->timestamps();
            $t->unique(['unit_id', 'jenis', 'tahun']);
        });
    }
    public function down(): void { Schema::dropIfExists('penomoran'); }
};
