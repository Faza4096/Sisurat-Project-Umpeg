<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Models\Klasifikasi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    /** Laporan arsip surat + filter tanggal/instansi/klasifikasi. */
    public function index(Request $request)
    {
        [$jenis, $data, $ringkasan] = $this->susun($request);

        return view('laporan.index', [
            'jenis' => $jenis,
            'data' => $data,
            'ringkasan' => $ringkasan,
            'instansi' => Instansi::orderBy('nama')->get(),
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
            'units' => UnitKerja::orderBy('nama')->get(),
        ]);
    }

    /** Laporan tindak lanjut disposisi (termasuk bukti). */
    public function tindakLanjut(Request $request)
    {
        $q = \App\Models\DisposisiTujuan::with([
            'unit', 'penyelesai', 'disposisi.suratMasuk.instansi', 'disposisi.pembuat',
        ])
            // Abaikan baris milik disposisi yang sudah dibatalkan (soft delete).
            ->whereHas('disposisi')
            ->when($request->query('unit'), fn ($x, $unit) => $x->where('unit_id', $unit))
            ->when($request->query('status'), fn ($x, $status) => $x->where('status_tindak_lanjut', $status))
            ->when($request->query('dari'), fn ($x, $dari) => $x->whereHas('disposisi', fn ($d) => $d->whereDate('tgl_disposisi', '>=', $dari)))
            ->when($request->query('sampai'), fn ($x, $sampai) => $x->whereHas('disposisi', fn ($d) => $d->whereDate('tgl_disposisi', '<=', $sampai)))
            ->latest();

        return view('laporan.tindak-lanjut', [
            'data' => $q->paginate(20)->withQueryString(),
            'units' => UnitKerja::orderBy('nama')->get(),
            'ringkasan' => \App\Models\DisposisiTujuan::selectRaw('status_tindak_lanjut, count(*) as jumlah')
                ->groupBy('status_tindak_lanjut')->pluck('jumlah', 'status_tindak_lanjut'),
        ]);
    }

    /** Ekspor Excel (CSV UTF-8, pemisah titik koma agar langsung rapi di Excel). */
    public function ekspor(Request $request): StreamedResponse
    {
        [$jenis, $data] = $this->susun($request);
        $nama = "laporan-surat-{$jenis}-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($jenis, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $tulis = fn (array $baris) => fputcsv($out, array_map($this->selCsv(...), $baris), ';');

            if ($jenis === 'masuk') {
                $tulis(['No. Agenda', 'No. Surat', 'Tanggal Surat', 'Tanggal Diterima', 'Pengirim', 'Klasifikasi', 'Perihal', 'Sifat', 'Status']);
                foreach ($data as $s) {
                    $tulis([
                        $s->no_agenda, $s->no_surat,
                        $s->tgl_surat?->format('d/m/Y'), $s->tgl_diterima?->format('d/m/Y'),
                        $s->instansi?->nama, $s->klasifikasi?->kode, $s->perihal,
                        SuratMasuk::STATUS[$s->status] ?? $s->status,
                        $s->sifat,
                    ]);
                }
            } else {
                $tulis(['No. Surat', 'Tanggal Surat', 'Tujuan', 'Unit', 'Klasifikasi', 'Hal', 'Status', 'Penandatangan', 'Tgl Kirim', 'Penerima']);
                foreach ($data as $s) {
                    $tulis([
                        $s->no_surat, $s->tgl_surat?->format('d/m/Y'),
                        $s->instansi?->nama, $s->unit?->nama, $s->klasifikasi?->kode, $s->hal,
                        SuratKeluar::STATUS[$s->status] ?? $s->status,
                        $s->penandatangan?->name, $s->tgl_kirim?->format('d/m/Y'), $s->penerima,
                    ]);
                }
            }

            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Cegah CSV/formula injection: sel yang diawali pemicu formula Excel/Calc
     * diberi awalan kutip tunggal agar dibaca sebagai teks biasa.
     */
    private function selCsv(mixed $nilai): string
    {
        $teks = (string) ($nilai ?? '');

        return $teks !== '' && str_contains("=+-@\t\r", $teks[0])
            ? "'" . $teks
            : $teks;
    }

    /** Ekspor PDF (dompdf bila tersedia, selain itu halaman siap cetak). */
    public function pdf(Request $request)
    {
        [$jenis, $data, $ringkasan] = $this->susun($request);
        $html = view('laporan.pdf', [
            'jenis' => $jenis,
            'data' => $data,
            'ringkasan' => $ringkasan,
            'filter' => $request->only(['dari', 'sampai', 'instansi_id', 'klasifikasi_id', 'status']),
            'pdf' => PdfService::tersedia(),
        ])->render();

        if (PdfService::tersedia()) {
            return PdfService::unduh($html, 'laporan-surat-' . $jenis . '-' . now()->format('Ymd-His') . '.pdf');
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @return array{0: string, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection}
     */
    private function susun(Request $request): array
    {
        $jenis = $request->query('jenis') === 'keluar' ? 'keluar' : 'masuk';

        $q = $jenis === 'masuk'
            ? SuratMasuk::with('instansi', 'klasifikasi')->terlihatOleh($request->user())
                ->when($request->query('dari'), fn ($x, $v) => $x->whereDate('tgl_diterima', '>=', $v))
                ->when($request->query('sampai'), fn ($x, $v) => $x->whereDate('tgl_diterima', '<=', $v))
            : SuratKeluar::with('instansi', 'klasifikasi', 'unit', 'penandatangan')->terlihatOleh($request->user())
                ->when($request->query('dari'), fn ($x, $v) => $x->whereDate('tgl_surat', '>=', $v))
                ->when($request->query('sampai'), fn ($x, $v) => $x->whereDate('tgl_surat', '<=', $v));

        $q->when($request->query('instansi_id'), fn ($x, $v) => $x->where('instansi_id', $v))
            ->when($request->query('klasifikasi_id'), fn ($x, $v) => $x->where('klasifikasi_id', $v))
            ->when($request->query('status'), fn ($x, $v) => $x->where('status', $v));

        $data = $q->latest($jenis === 'masuk' ? 'tgl_diterima' : 'tgl_surat')->get();
        $ringkasan = $data->groupBy('status')->map->count();

        return [$jenis, $data, $ringkasan];
    }
}
