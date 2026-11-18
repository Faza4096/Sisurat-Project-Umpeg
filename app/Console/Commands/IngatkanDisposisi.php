<?php

namespace App\Console\Commands;

use App\Models\DisposisiTujuan;
use App\Models\User;
use App\Notifications\PengingatBatasWaktu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class IngatkanDisposisi extends Command
{
    protected $signature = 'disposisi:ingatkan {--hari=2 : batas hari sebelum tanggal penyelesaian}';

    protected $description = 'Kirim notifikasi disposisi yang mendekati atau melewati batas penyelesaian';

    public function handle(): int
    {
        $batas = now()->addDays((int) $this->option('hari'))->toDateString();

        $daftar = DisposisiTujuan::with('unit', 'disposisi.suratMasuk')
            ->where('status_tindak_lanjut', '!=', 'selesai')
            ->whereHas('disposisi', fn ($q) => $q->whereNotNull('tgl_penyelesaian')
                ->whereDate('tgl_penyelesaian', '<=', $batas))
            ->get();

        $terkirim = 0;

        foreach ($daftar as $tujuan) {
            $penerima = User::where('unit_id', $tujuan->unit_id)->get();

            if ($penerima->isEmpty()) {
                $this->warn("Tidak ada pengguna pada unit {$tujuan->unit?->nama}.");
                continue;
            }

            Notification::send($penerima, new PengingatBatasWaktu($tujuan));
            $terkirim += $penerima->count();

            $this->line(sprintf(
                '%s (%s) batas %s → notifikasi ke %d pengguna',
                $tujuan->disposisi->suratMasuk?->no_agenda ?? '-',
                $tujuan->unit?->nama ?? '-',
                $tujuan->disposisi->tgl_penyelesaian?->format('d-m-Y') ?? '-',
                $penerima->count(),
            ));
        }

        $this->info("Selesai: {$daftar->count()} tindak lanjut, {$terkirim} notifikasi terkirim.");

        return self::SUCCESS;
    }
}
