<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_instansi', function (Blueprint $t) {
            $t->id();
            $t->string('nama')->default('PEMERINTAH KABUPATEN BOGOR');
            $t->string('alamat')->nullable();
            $t->string('telepon', 50)->nullable();
            $t->string('email')->nullable();
            $t->string('website')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('pejabat_nama')->nullable();
            $t->string('pejabat_jabatan')->nullable();
            $t->timestamps();
        });

        DB::table('pengaturan_instansi')->insert([
            'nama' => 'PEMERINTAH KABUPATEN BOGOR',
            'alamat' => 'Jl. Tegar Beriman No. 1, Cibinong, Kabupaten Bogor 16914',
            'telepon' => '(021) 875 2345',
            'email' => 'sekretariat@bogorkab.go.id',
            'website' => 'bogorkab.go.id',
            'logo_path' => 'img/lambang-kabupaten-bogor.png',
            'pejabat_nama' => null,
            'pejabat_jabatan' => 'Sekretaris Daerah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('disposisi', function (Blueprint $t) {
            // Satu disposisi bisa berasal dari surat masuk atau surat keluar (ACC pimpinan).
            $t->foreignId('surat_masuk_id')->nullable()->change();
            $t->foreignId('surat_keluar_id')->nullable()->after('surat_masuk_id')
                ->constrained('surat_keluar')->nullOnDelete();

            $t->string('kartu_path')->nullable();
            $t->string('kartu_nama')->nullable();
            $t->timestamp('kartu_dibuat_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disposisi', function (Blueprint $t) {
            $t->dropConstrainedForeignId('surat_keluar_id');
            $t->dropColumn(['kartu_path', 'kartu_nama', 'kartu_dibuat_pada']);
        });

        Schema::dropIfExists('pengaturan_instansi');
    }
};
