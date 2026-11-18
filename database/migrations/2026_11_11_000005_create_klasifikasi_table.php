<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('klasifikasi', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 20)->unique(); // contoh 400.7
            $t->string('uraian');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('klasifikasi'); }
};
