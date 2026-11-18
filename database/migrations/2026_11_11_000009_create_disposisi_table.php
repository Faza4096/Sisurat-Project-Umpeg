<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('disposisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surat_masuk_id')->constrained('surat_masuk')->cascadeOnDelete();
            $t->foreignId('dibuat_oleh')->constrained('users');
            $t->string('indeks')->nullable();
            $t->date('tgl_penyelesaian')->nullable();
            $t->text('instruksi');
            $t->timestamp('tgl_disposisi')->useCurrent();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('disposisi'); }
};
