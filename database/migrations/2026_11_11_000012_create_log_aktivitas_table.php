<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('log_aktivitas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengguna_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('aksi', 50);
            $t->string('keterangan')->nullable();
            $t->timestamp('waktu')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('log_aktivitas'); }
};
