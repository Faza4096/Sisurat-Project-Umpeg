<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Disposisi extends Model
{
    use SoftDeletes;

    protected $table = 'disposisi';

    protected $fillable = [
        'surat_masuk_id', 'surat_keluar_id', 'dibuat_oleh', 'indeks', 'tgl_penyelesaian',
        'instruksi', 'tgl_disposisi', 'kartu_path', 'kartu_nama', 'kartu_dibuat_pada',
    ];

    protected $casts = [
        'tgl_penyelesaian' => 'date',
        'tgl_disposisi' => 'datetime',
        'kartu_dibuat_pada' => 'datetime',
    ];

    public function suratMasuk()
    {
        return $this->belongsTo(SuratMasuk::class);
    }

    public function suratKeluar()
    {
        return $this->belongsTo(SuratKeluar::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function tujuan()
    {
        return $this->hasMany(DisposisiTujuan::class);
    }

    public function sudahLewatBatas(): bool
    {
        return $this->tgl_penyelesaian !== null && $this->tgl_penyelesaian->isPast();
    }

    /* ------------------------------------------------------------------
     | Data untuk kartu disposisi (surat masuk maupun surat keluar)
     * ------------------------------------------------------------------ */

    public function nomorSurat(): string
    {
        return $this->suratMasuk?->no_agenda
            ?? $this->suratKeluar?->no_surat
            ?? ('DIS-' . $this->id);
    }

    public function perihal(): string
    {
        return $this->suratMasuk?->perihal
            ?? $this->suratKeluar?->hal
            ?? '-';
    }

    public function tanggalSurat()
    {
        return $this->suratMasuk?->tgl_surat ?? $this->suratKeluar?->tgl_surat;
    }

    public function nomorSuratAsli(): string
    {
        return $this->suratMasuk?->no_surat ?? $this->suratKeluar?->no_surat ?? '-';
    }

    /** "Dari" pada kartu: pengirim surat masuk, atau unit pembuat surat keluar. */
    public function asal(): string
    {
        if ($this->suratMasuk) {
            return $this->suratMasuk->instansi?->nama ?? '-';
        }

        return $this->suratKeluar?->unit?->nama
            ?? $this->suratKeluar?->unit?->kode
            ?? '-';
    }

    public function tautanSurat(): string
    {
        if ($this->suratMasuk) {
            return route('surat-masuk.show', $this->suratMasuk);
        }

        if ($this->suratKeluar) {
            return route('surat-keluar.show', $this->suratKeluar);
        }

        return route('disposisi.index');
    }

    public function sudahAdaKartu(): bool
    {
        return $this->kartu_path !== null;
    }
}
