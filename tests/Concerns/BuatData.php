<?php

namespace Tests\Concerns;

use App\Models\Disposisi;
use App\Models\Instansi;
use App\Models\Klasifikasi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;

trait BuatData
{
    protected static int $urut = 0;

    protected function unit(string $kode = 'TU'): UnitKerja
    {
        return UnitKerja::firstOrCreate(['kode' => $kode], ['nama' => 'Unit ' . $kode]);
    }

    protected function instansi(string $nama = 'Puskesmas Ciapus'): Instansi
    {
        return Instansi::firstOrCreate(['nama' => $nama], ['alamat' => 'Kabupaten Bogor']);
    }

    protected function klasifikasi(string $kode = '400.7'): Klasifikasi
    {
        return Klasifikasi::firstOrCreate(['kode' => $kode], ['uraian' => 'Kesehatan']);
    }

    protected function suratMasuk(User $pencatat, array $atribut = []): SuratMasuk
    {
        $n = ++self::$urut;

        return SuratMasuk::create(array_merge([
            'no_agenda' => sprintf('AGD/%04d/XI/2026', $n),
            'no_surat' => "400.7/{$n}-TU",
            'tgl_surat' => now()->subDays(2)->toDateString(),
            'tgl_diterima' => now()->toDateString(),
            'instansi_id' => $this->instansi()->id,
            'klasifikasi_id' => $this->klasifikasi()->id,
            'perihal' => "Perihal uji {$n}",
            'sifat' => 'biasa',
            'status' => 'diterima',
            'dicatat_oleh' => $pencatat->id,
        ], $atribut));
    }

    /** @param UnitKerja[] $units */
    protected function disposisi(SuratMasuk $surat, User $pimpinan, array $units, array $atribut = []): Disposisi
    {
        $disposisi = Disposisi::create(array_merge([
            'surat_masuk_id' => $surat->id,
            'dibuat_oleh' => $pimpinan->id,
            'instruksi' => 'Tindak lanjuti dan laporkan hasilnya.',
            'tgl_disposisi' => now(),
            'tgl_penyelesaian' => now()->addDays(3),
        ], $atribut));

        foreach ($units as $unit) {
            $disposisi->tujuan()->create(['unit_id' => $unit->id]);
        }

        $surat->update(['status' => 'didisposisi']);

        return $disposisi;
    }

    protected function suratKeluar(User $pembuat, array $atribut = []): SuratKeluar
    {
        $n = ++self::$urut;

        return SuratKeluar::create(array_merge([
            'no_surat' => "400.7/{$n}-TU",
            'tgl_surat' => now()->toDateString(),
            'instansi_id' => $this->instansi()->id,
            'klasifikasi_id' => $this->klasifikasi()->id,
            'unit_id' => $this->unit()->id,
            'hal' => "Hal uji {$n}",
            'sifat' => 'biasa',
            'lampiran_ket' => '-',
            'status' => 'draf',
            'dibuat_oleh' => $pembuat->id,
        ], $atribut));
    }
}
