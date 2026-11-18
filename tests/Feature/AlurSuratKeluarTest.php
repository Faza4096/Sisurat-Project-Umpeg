<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MenungguTandaTangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class AlurSuratKeluarTest extends TestCase
{
    use RefreshDatabase, BuatData;

    public function test_alur_draf_hingga_arsip(): void
    {
        Notification::fake();
        Storage::fake('local');

        $tu = User::factory()->staf()->create();
        $pimpinan = User::factory()->staf()->create();
        $surat = $this->suratKeluar($tu);

        // Persetujuan tidak boleh dari status draf.
        $this->actingAs($pimpinan)->post(route('surat-keluar.acc', $surat))->assertForbidden();

        // TU mengajukan → menunggu TTD + notifikasi pimpinan.
        $this->actingAs($tu)->post(route('surat-keluar.ajukan', $surat))->assertSessionHasNoErrors();
        $this->assertSame('menunggu_ttd', $surat->fresh()->status);
        Notification::assertSentTo($pimpinan, MenungguTandaTangan::class);

        // Pimpinan meminta revisi tanpa catatan → ditolak validasi.
        $this->actingAs($pimpinan)->post(route('surat-keluar.tolak', $surat), [])
            ->assertSessionHasErrors('catatan_revisi');
        $this->assertSame('menunggu_ttd', $surat->fresh()->status);

        // Dengan catatan → status revisi.
        $this->actingAs($pimpinan)->post(route('surat-keluar.tolak', $surat), [
            'catatan_revisi' => 'Perbaiki lampiran dan nomor surat.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('revisi', $surat->fresh()->status);

        // TU mengajukan ulang lalu pimpinan menyetujui.
        $this->actingAs($tu)->post(route('surat-keluar.ajukan', $surat))->assertSessionHasNoErrors();
        $this->actingAs($pimpinan)->post(route('surat-keluar.acc', $surat))->assertSessionHasNoErrors();

        $surat->refresh();
        $this->assertSame('disetujui', $surat->status);
        $this->assertSame($pimpinan->id, $surat->penandatangan_id);
        $this->assertNotNull($surat->disetujui_pada);

        // Pengiriman wajib menyertakan berkas final PDF.
        $this->actingAs($tu)->post(route('surat-keluar.kirim', $surat), [
            'tgl_kirim' => now()->toDateString(),
            'penerima' => 'Bagian Umum',
            'metode_kirim' => 'pos',
        ])->assertSessionHasErrors('file_final');

        $this->actingAs($tu)->post(route('surat-keluar.kirim', $surat), [
            'file_final' => UploadedFile::fake()->create('final.pdf', 200, 'application/pdf'),
            'tgl_kirim' => now()->toDateString(),
            'penerima' => 'Bagian Umum',
            'metode_kirim' => 'pos',
            'no_bukti_kirim' => 'RESI-001',
        ])->assertSessionHasNoErrors();

        $surat->refresh();
        $this->assertSame('terkirim', $surat->status);
        $this->assertSame('Bagian Umum', $surat->penerima);
        $this->assertNotNull($surat->file_final_path);
        Storage::disk('local')->assertExists($surat->file_final_path);

        // Unduh berkas final mengikuti hak akses surat.
        $this->actingAs($tu)->get(route('surat-keluar.file-final', $surat))->assertOk();

        $this->actingAs($tu)->post(route('surat-keluar.arsipkan', $surat))->assertSessionHasNoErrors();
        $this->assertSame('diarsipkan', $surat->fresh()->status);
    }

    public function test_staf_dapat_menyetujui_surat_keluar(): void
    {
        Storage::fake('local');

        $staf = User::factory()->staf()->create();
        $surat = $this->suratKeluar($staf, ['status' => 'menunggu_ttd']);

        $this->actingAs($staf)->post(route('surat-keluar.acc', $surat))->assertSessionHasNoErrors();

        $this->assertSame('disetujui', $surat->fresh()->status);
    }

    public function test_administrator_dapat_menyetujui_surat_keluar(): void
    {
        Storage::fake('local');

        $tu = User::factory()->staf()->create();
        $admin = User::factory()->admin()->create();
        $surat = $this->suratKeluar($tu, ['status' => 'menunggu_ttd']);

        $this->actingAs($admin)->post(route('surat-keluar.acc', $surat))->assertSessionHasNoErrors();

        $this->assertSame('disetujui', $surat->fresh()->status);
    }

    public function test_hapus_draf_memakai_soft_delete(): void
    {
        $tu = User::factory()->staf()->create();
        $surat = $this->suratKeluar($tu);

        $this->actingAs($tu)->delete(route('surat-keluar.destroy', $surat))->assertRedirect();

        $this->assertSoftDeleted('surat_keluar', ['id' => $surat->id]);
    }

    public function test_surat_terkirim_tidak_dapat_dihapus(): void
    {
        $tu = User::factory()->staf()->create();
        $surat = $this->suratKeluar($tu, ['status' => 'terkirim']);

        $this->actingAs($tu)->delete(route('surat-keluar.destroy', $surat))->assertForbidden();
        $this->assertNotSoftDeleted($surat);
    }

    public function test_buat_balasan_dari_surat_masuk(): void
    {
        $tu = User::factory()->staf()->create();
        $surat = $this->suratMasuk($tu);

        $this->actingAs($tu)
            ->get(route('surat-masuk.balas', $surat))
            ->assertRedirect(route('surat-keluar.create', ['surat_masuk_id' => $surat->id]));

        $this->actingAs($tu)
            ->get(route('surat-keluar.create', ['surat_masuk_id' => $surat->id]))
            ->assertOk()
            ->assertSee($surat->no_agenda);
    }
}
