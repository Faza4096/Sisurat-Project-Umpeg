<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\DisposisiTujuan;
use App\Models\LogAktivitas;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use App\Notifications\StatusTindakLanjut;
use App\Services\KartuDisposisiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DisposisiController extends Controller
{
    public function index(Request $request)
    {
        $items = $this->query($request)->paginate(15)->withQueryString();

        return view('disposisi.index', [
            'items' => $items,
            'units' => UnitKerja::orderBy('nama')->get(),
            'terpilih' => (array) $request->query('ids', []),
            'menunggu' => $this->menungguDisposisi($request->user()),
        ]);
    }

    /** Form ACC & disposisi untuk surat masuk (terisi otomatis dari surat). */
    public function formAcc(SuratMasuk $suratMasuk)
    {
        $this->authorize('acc', $suratMasuk);
        $this->authorize('view', $suratMasuk);
        $suratMasuk->load('instansi', 'klasifikasi');

        return view('disposisi.acc', [
            's' => $suratMasuk,
            'units' => UnitKerja::orderBy('nama')->get(),
        ]);
    }

    /**
     * Staf meng-ACC surat masuk: disposisi terbentuk, status surat menjadi
     * didisposisi, dan PDF kartu langsung dibuat serta dilampirkan ke notifikasi.
     */
    public function acc(Request $request, SuratMasuk $suratMasuk)
    {
        $this->authorize('acc', $suratMasuk);

        $data = $request->validate([
            'indeks' => ['required', 'string', 'max:255'],
            'tgl_penyelesaian' => ['required', 'date', 'after_or_equal:today'],
            'instruksi' => ['required', 'string'],
            'tujuan' => ['required', 'array', 'min:1'],
            'tujuan.*' => ['exists:unit_kerja,id'],
        ]);

        $disposisi = DB::transaction(function () use ($data, $request, $suratMasuk) {
            $disposisi = $this->buatDisposisi($request->user(), $data, $suratMasuk, 'surat_masuk_id');
            $suratMasuk->update(['status' => 'didisposisi']);
            LogAktivitas::catat('disposisi.acc', $suratMasuk->no_agenda);

            return $disposisi;
        });

        $disposisi = app(KartuDisposisiService::class)->simpan($disposisi);

        $this->kirimNotifikasi($disposisi, $data['tujuan']);

        return redirect()->route('surat-masuk.show', $suratMasuk)
            ->with('ok', 'Surat di-ACC. Kartu disposisi PDF dibuat dan diteruskan ke unit tujuan.');
    }

    /** Simpan disposisi dari form lama (tanpa halaman ACC). */
    public function store(Request $request, SuratMasuk $suratMasuk)
    {
        $this->authorize('create', Disposisi::class);
        $this->authorize('view', $suratMasuk);

        $data = $request->validate([
            'instruksi' => ['required', 'string'],
            'indeks' => ['nullable', 'string', 'max:255'],
            'tgl_penyelesaian' => ['nullable', 'date', 'after_or_equal:today'],
            'tujuan' => ['required', 'array', 'min:1'],
            'tujuan.*' => ['exists:unit_kerja,id'],
        ]);

        $disposisi = DB::transaction(function () use ($data, $request, $suratMasuk) {
            $disposisi = $this->buatDisposisi($request->user(), $data, $suratMasuk, 'surat_masuk_id');
            $suratMasuk->update(['status' => 'didisposisi']);
            LogAktivitas::catat('disposisi.baru', $suratMasuk->no_agenda);

            return $disposisi;
        });

        $disposisi = app(KartuDisposisiService::class)->simpan($disposisi);
        $this->kirimNotifikasi($disposisi, $data['tujuan']);

        return back()->with('ok', 'Disposisi tersimpan, kartu PDF dibuat, dan diteruskan ke unit tujuan.');
    }

    /** Halaman kartu disposisi siap cetak. */
    public function kartu(Disposisi $disposisi)
    {
        $this->authorize('view', $disposisi);
        $this->muatRelasi($disposisi);

        return view('disposisi.kartu', ['d' => $disposisi]);
    }

    /** Unduh PDF kartu satu disposisi (dibuat ulang bila berkas hilang). */
    public function pdf(Disposisi $disposisi)
    {
        $this->authorize('unduhKartu', $disposisi);

        $disposisi = app(KartuDisposisiService::class)->pastikan($disposisi);
        LogAktivitas::catat('kartu.unduh', $disposisi->nomorSurat());

        return Storage::download($disposisi->kartu_path, $disposisi->kartu_nama ?: 'kartu-disposisi.pdf');
    }

    /** Cetak/unduh beberapa kartu sekaligus dalam satu PDF. */
    public function cetak(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:disposisi,id'],
        ]);

        $items = Disposisi::with('suratMasuk', 'suratKeluar')->whereIn('id', $data['ids'])
            ->orderBy('tgl_disposisi')->get();

        foreach ($items as $item) {
            $this->authorize('unduhKartu', $item);
        }

        LogAktivitas::catat('kartu.unduh.massal', $items->count() . ' kartu disposisi');

        return app(KartuDisposisiService::class)->unduhBanyak($items);
    }

    /**
     * Perbarui tindak lanjut. Hanya unit tujuan (atau admin) yang boleh.
     * Kartu PDF diperbarui otomatis setelah perubahan.
     */
    public function ubahStatus(Request $request, DisposisiTujuan $tujuan)
    {
        $this->authorize('ubah', $tujuan);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DisposisiTujuan::STATUS))],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $tujuan->update([
            'status_tindak_lanjut' => $data['status'],
            'catatan' => $data['catatan'] ?? $tujuan->catatan,
            'tgl_selesai' => $data['status'] === 'selesai' ? now() : null,
            'selesai_oleh' => $data['status'] === 'selesai' ? $request->user()->id : null,
        ]);

        $disposisi = $tujuan->disposisi;
        $surat = $disposisi->suratMasuk;

        if ($surat) {
            $surat->update(['status' => $this->hitungStatusSurat($surat)]);
            LogAktivitas::catat('disposisi.status', "{$surat->no_agenda} → {$data['status']}");
        } else {
            LogAktivitas::catat('disposisi.status', "{$disposisi->nomorSurat()} → {$data['status']}");
        }

        app(KartuDisposisiService::class)->simpan($disposisi->fresh());

        $this->kabariUnitTujuan($tujuan, $data['status'], $request->user());

        return back()->with('ok', 'Status tindak lanjut diperbarui dan kartu disposisi disegarkan.');
    }

    /** Unggah bukti tindak lanjut (foto/PDF) oleh unit tujuan. */
    public function unggahBukti(Request $request, DisposisiTujuan $tujuan)
    {
        $this->authorize('ubah', $tujuan);

        $request->validate([
            'bukti' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($tujuan->bukti_path) {
            Storage::delete($tujuan->bukti_path);
        }

        $file = $request->file('bukti');
        $tujuan->update([
            'bukti_path' => $file->store('disposisi/bukti'),
            'bukti_nama' => $file->getClientOriginalName(),
            'diunggah_pada' => now(),
            'catatan' => $request->input('catatan', $tujuan->catatan),
            'status_tindak_lanjut' => $tujuan->status_tindak_lanjut === 'belum' ? 'diproses' : $tujuan->status_tindak_lanjut,
        ]);

        $disposisi = $tujuan->disposisi;

        if ($disposisi->suratMasuk) {
            $disposisi->suratMasuk->update([
                'status' => $this->hitungStatusSurat($disposisi->suratMasuk),
            ]);
        }

        LogAktivitas::catat('disposisi.bukti', "{$disposisi->nomorSurat()}: {$file->getClientOriginalName()}");

        return back()->with('ok', 'Bukti tindak lanjut tersimpan.');
    }

    /** Unduh bukti tindak lanjut (berkas privat, mengikuti akses surat). */
    public function unduhBukti(DisposisiTujuan $tujuan)
    {
        $this->authorize('view', $tujuan);
        abort_unless($tujuan->bukti_path && Storage::exists($tujuan->bukti_path), 404, 'Bukti tidak ditemukan.');

        return Storage::download($tujuan->bukti_path, $tujuan->bukti_nama ?? 'bukti');
    }

    public function hapusBukti(DisposisiTujuan $tujuan)
    {
        $this->authorize('ubah', $tujuan);

        if ($tujuan->bukti_path) {
            Storage::delete($tujuan->bukti_path);
        }

        $tujuan->update(['bukti_path' => null, 'bukti_nama' => null, 'diunggah_pada' => null]);
        LogAktivitas::catat('disposisi.bukti.hapus', $tujuan->disposisi->nomorSurat());

        return back()->with('ok', 'Bukti tindak lanjut dihapus.');
    }

    public function destroy(Disposisi $disposisi)
    {
        $this->authorize('delete', $disposisi);

        DB::transaction(function () use ($disposisi) {
            $surat = $disposisi->suratMasuk;

            app(KartuDisposisiService::class)->hapus($disposisi);
            $disposisi->tujuan()->delete();
            $disposisi->delete();

            if ($surat) {
                $surat->update(['status' => $this->hitungStatusSurat($surat)]);
            }
            LogAktivitas::catat('disposisi.hapus', $disposisi->nomorSurat());
        });

        return back()->with('ok', 'Disposisi dibatalkan.');
    }

    /* ------------------------------------------------------------------ */

    private function buatDisposisi(User $pembuat, array $data, SuratMasuk|SuratKeluar $surat, string $kolom): Disposisi
    {
        $disposisi = Disposisi::create([
            $kolom => $surat->id,
            'dibuat_oleh' => $pembuat->id,
            'indeks' => $data['indeks'] ?? ($surat instanceof SuratKeluar ? $surat->klasifikasi?->kode : null),
            'tgl_penyelesaian' => $data['tgl_penyelesaian'] ?? null,
            'instruksi' => $data['instruksi'],
            'tgl_disposisi' => now(),
        ]);

        foreach (array_unique($data['tujuan']) as $unitId) {
            $disposisi->tujuan()->create(['unit_id' => $unitId]);
        }

        return $disposisi;
    }

    /** Perubahan oleh operator dikabarkan ke pengguna unit tujuan. */
    private function kabariUnitTujuan(DisposisiTujuan $tujuan, string $status, User $oleh): void
    {
        if (! $oleh->punyaRole('staf')) {
            return;
        }

        Notification::send(
            User::where('unit_id', $tujuan->unit_id)->whereKeyNot($oleh->id)->get(),
            new StatusTindakLanjut($tujuan, $status, $oleh->name),
        );
    }

    private function kirimNotifikasi(Disposisi $disposisi, array $unitIds): void
    {
        $penerima = User::whereIn('unit_id', $unitIds)->get();

        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new DisposisiBaru($disposisi));
        }
    }

    private function muatRelasi(Disposisi $disposisi): void
    {
        $disposisi->load('suratMasuk.instansi', 'suratKeluar.unit', 'tujuan.unit', 'pembuat');
    }

    /**
     * Hitung status surat dari seluruh tujuan disposisi aktif:
     * semua selesai → selesai; ada yang diproses/selesai → diproses;
     * semua belum → didisposisi; belum ada disposisi → diterima.
     */
    private function hitungStatusSurat(SuratMasuk $surat): string
    {
        $disposisiIds = $surat->disposisi()->pluck('id');

        if ($disposisiIds->isEmpty()) {
            return 'diterima';
        }

        $status = DisposisiTujuan::whereIn('disposisi_id', $disposisiIds)->pluck('status_tindak_lanjut');

        if ($status->isEmpty()) {
            return 'diterima';
        }

        if ($status->every(fn ($s) => $s === 'selesai')) {
            return 'selesai';
        }

        if ($status->contains(fn ($s) => in_array($s, ['diproses', 'selesai'], true))) {
            return 'diproses';
        }

        return 'didisposisi';
    }

    /**
     * Surat yang baru dicatat dan belum masuk alur disposisi: surat masuk
     * berstatus diterima serta surat keluar yang belum disetujui.
     * Staf tidak melihat antrean ini karena belum ada disposisi untuk unitnya.
     */
    private function menungguDisposisi(User $user): array
    {
        if (! $user->punyaRole('admin', 'staf')) {
            return ['masuk' => collect(), 'keluar' => collect(), 'totalMasuk' => 0, 'totalKeluar' => 0];
        }

        $masuk = SuratMasuk::with('instansi')->where('status', 'diterima');
        $keluar = SuratKeluar::with('unit')->whereIn('status', SuratKeluar::BELUM_DISETUJUI);

        return [
            'masuk' => (clone $masuk)->orderBy('tgl_diterima')->limit(10)->get(),
            'keluar' => (clone $keluar)->latest('updated_at')->limit(10)->get(),
            'totalMasuk' => $masuk->count(),
            'totalKeluar' => $keluar->count(),
        ];
    }

    private function query(Request $request): Builder
    {
        return Disposisi::with('suratMasuk.instansi', 'suratKeluar.unit', 'pembuat', 'tujuan.unit', 'tujuan.penyelesai')
            ->when(! $request->user()->punyaRole('admin', 'staf'), function ($q) use ($request) {
                $q->whereHas('tujuan', fn ($t) => $t->where('unit_id', $request->user()->unit_id));
            })
            ->when($request->query('cari'), function ($q, $cari) {
                $q->where(fn ($w) => $w->where('instruksi', 'like', "%{$cari}%")
                    ->orWhereHas('suratMasuk', fn ($s) => $s->where('no_agenda', 'like', "%{$cari}%")
                        ->orWhere('perihal', 'like', "%{$cari}%"))
                    ->orWhereHas('suratKeluar', fn ($s) => $s->where('no_surat', 'like', "%{$cari}%")
                        ->orWhere('hal', 'like', "%{$cari}%")));
            })
            ->when($request->query('unit'), fn ($q, $unit) => $q->whereHas('tujuan', fn ($t) => $t->where('unit_id', $unit)))
            ->when($request->query('status'), function ($q, $status) {
                $q->whereHas('tujuan', fn ($t) => $t->where('status_tindak_lanjut', $status));
            })
            ->latest('tgl_disposisi');
    }
}
