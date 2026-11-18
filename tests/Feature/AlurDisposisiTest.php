<?php

namespace Tests\Feature;

use App\Models\DisposisiTujuan;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class AlurDisposisiTest extends TestCase
{
    use RefreshDatabase, BuatData;

    public function test_pimpinan_memberi_disposisi_dan_unit_tujuan_menerima_notifikasi(): void
    {
        Notification::fake();

        $tu = User::factory()->staf()->create();
        $pimpinan = User::factory()->staf()->create();
        $unit = $this->unit('UMPEG');
        $staf = User::factory()->create(['unit_id' => $unit->id]);
        $surat = $this->suratMasuk($tu);

        $this->actingAs($pimpinan)->post(route('disposisi.store', $surat), [
            'instruksi' => 'Koordinasikan dengan Puskesmas.',
            'tgl_penyelesaian' => now()->addDays(2)->toDateString(),
            'tujuan' => [$unit->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame('didisposisi', $surat->fresh()->status);
        $this->assertDatabaseHas('disposisi_tujuan', ['unit_id' => $unit->id, 'status_tindak_lanjut' => 'belum']);
        Notification::assertSentTo($staf, DisposisiBaru::class);
    }

    public function test_staf_boleh_memberi_disposisi(): void
    {
        $staf = User::factory()->staf()->create();
        $unit = $this->unit('UMPEG');
        $surat = $this->suratMasuk($staf);

        $this->actingAs($staf)->post(route('disposisi.store', $surat), [
            'instruksi' => 'Tindak lanjuti.',
            'tujuan' => [$unit->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame('didisposisi', $surat->fresh()->status);
    }

    public function test_status_surat_tidak_menjadi_diproses_saat_semua_tujuan_masih_belum(): void
    {
        $tu = User::factory()->staf()->create();
        $pimpinan = User::factory()->staf()->create();
        $unit = $this->unit('UMPEG');
        $staf = User::factory()->create(['unit_id' => $unit->id]);

        $surat = $this->suratMasuk($tu);
        $disposisi = $this->disposisi($surat, $pimpinan, [$unit]);
        $tujuan = $disposisi->tujuan()->first();

        $this->actingAs($pimpinan)->post(route('disposisi.store', $surat), [
            'instruksi' => 'Tambahan',
            'tujuan' => [$unit->id],
        ])->assertSessionHasNoErrors();

        // Dua tujuan disposisi, keduanya masih "belum": surat tetap didisposisi.
        $this->assertSame('didisposisi', $surat->fresh()->status);

        $this->actingAs($staf)->patch(route('tujuan.status', $tujuan), ['status' => 'diproses'])
            ->assertSessionHasNoErrors();
        $this->assertSame('diproses', $surat->fresh()->status);

        $this->actingAs($staf)->patch(route('tujuan.status', $tujuan), ['status' => 'belum'])
            ->assertSessionHasNoErrors();
        $this->assertSame('didisposisi', $surat->fresh()->status);

        // Semua tujuan harus selesai sebelum surat dinyatakan selesai.
        foreach ($surat->disposisi()->get()->flatMap->tujuan as $t) {
            $this->actingAs($staf)->patch(route('tujuan.status', $t), ['status' => 'selesai'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('selesai', $surat->fresh()->status);
        $this->assertSame($staf->id, $tujuan->fresh()->selesai_oleh);
    }

    public function test_staf_boleh_mengubah_status_unit_lain(): void
    {
        $staf = User::factory()->staf()->create();
        $unit = $this->unit('UMPEG');

        $surat = $this->suratMasuk($staf);
        $tujuan = $this->disposisi($surat, $staf, [$unit])->tujuan()->first();

        $this->actingAs($staf)->patch(route('tujuan.status', $tujuan), ['status' => 'selesai'])
            ->assertSessionHasNoErrors();
        $this->assertSame('selesai', $tujuan->fresh()->status_tindak_lanjut);
    }

    public function test_unit_tujuan_mengunggah_bukti_tindak_lanjut(): void
    {
        Storage::fake('local');

        $tu = User::factory()->staf()->create();
        $pimpinan = User::factory()->staf()->create();
        $unit = $this->unit('UMPEG');
        $staf = User::factory()->create(['unit_id' => $unit->id]);

        $surat = $this->suratMasuk($tu);
        $disposisi = $this->disposisi($surat, $pimpinan, [$unit]);
        $tujuan = $disposisi->tujuan()->first();

        $this->actingAs($staf)->post(route('tujuan.bukti', $tujuan), [
            'bukti' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
            'catatan' => 'Sudah dikoordinasikan dengan Puskesmas.',
        ])->assertSessionHasNoErrors();

        $tujuan->refresh();
        $this->assertNotNull($tujuan->bukti_path);
        $this->assertSame('bukti.pdf', $tujuan->bukti_nama);
        $this->assertSame('diproses', $tujuan->status_tindak_lanjut);
        $this->assertSame('diproses', $surat->fresh()->status);
        Storage::disk('local')->assertExists($tujuan->bukti_path);
    }

    public function test_surat_yang_sudah_didisposisi_tidak_dapat_dihapus(): void
    {
        $tu = User::factory()->staf()->create();
        $pimpinan = User::factory()->staf()->create();
        $surat = $this->suratMasuk($tu);
        $this->disposisi($surat, $pimpinan, [$this->unit('UMPEG')]);

        $this->actingAs($tu)->delete(route('surat-masuk.destroy', $surat))->assertForbidden();
        $this->assertNotSoftDeleted($surat);
    }

    public function test_tu_mengarsipkan_surat_yang_sudah_selesai(): void
    {
        $tu = User::factory()->staf()->create();
        $pimpinan = User::factory()->staf()->create();
        $surat = $this->suratMasuk($tu, ['status' => 'selesai']);

        $this->actingAs($tu)->patch(route('surat-masuk.arsipkan', $surat), [
            'lokasi_arsip' => 'Lemari B-2, Rak 3',
        ])->assertSessionHasNoErrors();

        $surat->refresh();
        $this->assertSame('diarsipkan', $surat->status);
        $this->assertSame('Lemari B-2, Rak 3', $surat->lokasi_arsip);
        $this->assertSame($tu->id, $surat->diarsipkan_oleh);
    }

    public function test_surat_yang_belum_selesai_tidak_dapat_diarsipkan(): void
    {
        $tu = User::factory()->staf()->create();
        $surat = $this->suratMasuk($tu, ['status' => 'diproses']);

        $this->actingAs($tu)->patch(route('surat-masuk.arsipkan', $surat), [
            'lokasi_arsip' => 'Lemari A',
        ])->assertForbidden();
    }
}
