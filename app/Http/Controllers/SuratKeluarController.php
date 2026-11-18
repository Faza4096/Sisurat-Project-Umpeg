<?php

namespace App\Http\Controllers;

use App\Http\Requests\HapusPermanenRequest;
use App\Models\Disposisi;
use App\Models\Instansi;
use App\Models\Klasifikasi;
use App\Models\LogAktivitas;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use App\Notifications\MenungguTandaTangan;
use App\Services\HapusSuratService;
use App\Services\KartuDisposisiService;
use App\Services\NomorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SuratKeluarController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->query($request);

        return view('surat-keluar.index', [
            'surat' => (clone $query)->paginate(15)->withQueryString(),
            // reorder(): MySQL menolak ORDER BY kolom non-agregat pada GROUP BY.
            'ringkasan' => (clone $query)->reorder()->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status'),
            'instansi' => Instansi::orderBy('nama')->get(),
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', SuratKeluar::class);

        $balasan = null;
        $s = new SuratKeluar();

        if ($request->filled('surat_masuk_id')) {
            $balasan = SuratMasuk::findOrFail($request->query('surat_masuk_id'));
            $this->authorize('view', $balasan);

            $s->fill([
                'surat_masuk_id' => $balasan->id,
                'instansi_id' => $balasan->instansi_id,
                'hal' => 'Balasan: ' . $balasan->perihal,
                'isi_ringkas' => "Menindaklanjuti surat nomor {$balasan->no_surat} tanggal "
                    . $balasan->tgl_surat?->translatedFormat('d F Y') . " perihal {$balasan->perihal}.",
            ]);
        }

        return view('surat-keluar.create', [
            's' => $s,
            'balasan' => $balasan,
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
            'units' => UnitKerja::orderBy('nama')->get(),
            'instansi' => Instansi::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', SuratKeluar::class);
        $data = $this->validated($request);
        $klas = Klasifikasi::findOrFail($data['klasifikasi_id']);
        $unit = UnitKerja::findOrFail($data['unit_id']);

        $surat = SuratKeluar::create([
            'no_surat' => NomorService::suratKeluar($unit->id, $klas->kode, $unit->kode),
            'tgl_surat' => $data['tgl_surat'],
            'instansi_id' => $this->instansiId($data),
            'klasifikasi_id' => $klas->id,
            'unit_id' => $unit->id,
            'hal' => $data['hal'],
            'sifat' => $data['sifat'],
            'lampiran_ket' => $data['lampiran_ket'] ?: '-',
            'isi_ringkas' => $data['isi_ringkas'] ?? null,
            'status' => 'draf',
            'surat_masuk_id' => $data['surat_masuk_id'] ?? null,
            'dibuat_oleh' => $request->user()->id,
        ]);

        LogAktivitas::catat('surat_keluar.baru', $surat->no_surat);

        return redirect()->route('surat-keluar.show', $surat)
            ->with('ok', "Draf surat dibuat dengan nomor otomatis {$surat->no_surat}. Ajukan untuk persetujuan dan tanda tangan.");
    }

    public function show(SuratKeluar $suratKeluar)
    {
        $this->authorize('view', $suratKeluar);
        $suratKeluar->load('instansi', 'klasifikasi', 'unit', 'penandatangan', 'pembuat', 'lampiran', 'suratMasuk');

        return view('surat-keluar.show', ['s' => $suratKeluar]);
    }

    public function edit(SuratKeluar $suratKeluar)
    {
        $this->authorize('update', $suratKeluar);

        return view('surat-keluar.edit', [
            's' => $suratKeluar,
            'balasan' => $suratKeluar->suratMasuk,
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
            'units' => UnitKerja::orderBy('nama')->get(),
            'instansi' => Instansi::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, SuratKeluar $suratKeluar)
    {
        $this->authorize('update', $suratKeluar);
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $suratKeluar) {
            $klas = Klasifikasi::findOrFail($data['klasifikasi_id']);
            $unit = UnitKerja::findOrFail($data['unit_id']);

            $suratKeluar->update([
                'tgl_surat' => $data['tgl_surat'],
                'instansi_id' => $this->instansiId($data),
                'klasifikasi_id' => $klas->id,
                'unit_id' => $unit->id,
                'hal' => $data['hal'],
                'sifat' => $data['sifat'],
                'lampiran_ket' => $data['lampiran_ket'] ?: '-',
                'isi_ringkas' => $data['isi_ringkas'] ?? null,
                'surat_masuk_id' => $data['surat_masuk_id'] ?? $suratKeluar->surat_masuk_id,
            ]);

            LogAktivitas::catat('surat_keluar.ubah', $suratKeluar->no_surat);
        });

        return redirect()->route('surat-keluar.show', $suratKeluar)->with('ok', 'Surat keluar berhasil diperbarui.');
    }

    public function destroy(SuratKeluar $suratKeluar)
    {
        $this->authorize('delete', $suratKeluar);

        $nomor = $suratKeluar->no_surat;
        $suratKeluar->delete();
        LogAktivitas::catat('surat_keluar.hapus', $nomor);

        return redirect()->route('surat-keluar.index')->with('ok', "Draf surat {$nomor} dipindahkan ke arsip terhapus.");
    }

    /** Halaman konfirmasi sebelum surat dihapus permanen. */
    public function formHapusPermanen(SuratKeluar $suratKeluar)
    {
        $this->authorize('hapusPermanen', $suratKeluar);
        $this->authorize('view', $suratKeluar);

        return view('surat.hapus-permanen', [
            'judul' => 'Hapus Permanen Surat Keluar',
            'nomor' => $suratKeluar->no_surat,
            'aksi' => route('surat-keluar.hapus-permanen', $suratKeluar),
            'batal' => route('surat-keluar.show', $suratKeluar),
            'rincian' => [
                'Nomor surat' => $suratKeluar->no_surat,
                'Perihal' => $suratKeluar->hal,
                'Kartu disposisi' => $suratKeluar->disposisiKartu()->withTrashed()->exists() ? 'Ikut terhapus' : 'Tidak ada',
                'Lampiran' => $suratKeluar->lampiran()->count() . ' berkas',
                'Berkas final' => $suratKeluar->file_final_path ? 'Ikut terhapus' : 'Belum ada',
            ],
            'penjelasan' => 'Kartu disposisi, tujuannya, lampiran, dan berkas final ikut dibuang dari basis data '
                . 'dan penyimpanan berkas. Data yang sudah dihapus tidak dapat dipulihkan.',
        ]);
    }

    /** Hapus permanen: surat beserta kartu disposisi dan berkas finalnya. */
    public function hapusPermanen(HapusPermanenRequest $request, SuratKeluar $suratKeluar, HapusSuratService $hapus)
    {
        $this->authorize('hapusPermanen', $suratKeluar);

        $nomor = $suratKeluar->no_surat;
        $hapus->keluar($suratKeluar);
        LogAktivitas::catat('surat_keluar.hapus_permanen', $nomor);

        return redirect()->route('surat-keluar.index')
            ->with('ok', "Surat keluar {$nomor} beserta disposisinya dihapus permanen.");
    }

    /** Operator mengajukan draf/revisi untuk persetujuan. */
    public function ajukan(SuratKeluar $suratKeluar)
    {
        $this->authorize('ajukan', $suratKeluar);

        $suratKeluar->update(['status' => 'menunggu_ttd', 'diajukan_pada' => now()]);
        LogAktivitas::catat('surat_keluar.ajukan', $suratKeluar->no_surat);

        $operator = User::operator()->whereKeyNot($suratKeluar->dibuat_oleh)->get();
        Notification::send($operator, new MenungguTandaTangan($suratKeluar));

        return back()->with('ok', 'Surat diajukan untuk persetujuan dan tanda tangan.');
    }

    /**
     * ACC surat keluar: nomor final diisi bila belum ada,
     * status menjadi disetujui, dan kartu disposisi PDF dibuat untuk unit Tata Usaha.
     */
    public function acc(Request $request, SuratKeluar $suratKeluar)
    {
        $this->authorize('acc', $suratKeluar);

        $disposisi = DB::transaction(function () use ($request, $suratKeluar) {
            $unit = $suratKeluar->unit;

            if (! $suratKeluar->no_surat && $unit) {
                $suratKeluar->no_surat = NomorService::suratKeluar(
                    $unit->id,
                    $suratKeluar->klasifikasi?->kode ?? '000',
                    $unit->kode ?: 'TU',
                );
            }

            $suratKeluar->fill([
                'status' => 'disetujui',
                'penandatangan_id' => $request->user()->id,
                'disetujui_pada' => now(),
                'catatan_revisi' => null,
            ])->save();

            // Kartu diteruskan ke unit Tata Usaha (atau unit pembuat) untuk pengiriman dan arsip.
            $unitTujuan = UnitKerja::where('kode', 'TU')->first() ?? $unit;

            $disposisi = Disposisi::firstOrCreate(
                ['surat_keluar_id' => $suratKeluar->id],
                [
                    'dibuat_oleh' => $request->user()->id,
                    'indeks' => $suratKeluar->klasifikasi?->kode,
                    'tgl_penyelesaian' => now()->addDays(3),
                    'instruksi' => 'Sampaikan surat kepada tujuan, catat pengiriman, lalu arsipkan.',
                    'tgl_disposisi' => now(),
                ]
            );

            if ($unitTujuan) {
                $disposisi->tujuan()->firstOrCreate(['unit_id' => $unitTujuan->id]);
            }

            LogAktivitas::catat('surat_keluar.acc', $suratKeluar->no_surat);

            return $disposisi;
        });

        $disposisi = app(KartuDisposisiService::class)->simpan($disposisi);

        $penerima = User::whereIn('unit_id', $disposisi->tujuan()->pluck('unit_id'))->get();
        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new DisposisiBaru($disposisi));
        }

        return back()->with('ok', 'Surat di-ACC. Kartu disposisi PDF dibuat dan diteruskan ke unit Tata Usaha untuk pengiriman.');
    }

    /** Staf menolak / meminta revisi. */
    public function tolak(Request $request, SuratKeluar $suratKeluar)
    {
        $this->authorize('tolak', $suratKeluar);

        $data = $request->validate([
            'catatan_revisi' => ['required', 'string', 'max:1000'],
        ], [
            'catatan_revisi.required' => 'Tuliskan alasan penolakan atau catatan revisi.',
        ]);

        $suratKeluar->update([
            'status' => 'revisi',
            'catatan_revisi' => $data['catatan_revisi'],
        ]);
        LogAktivitas::catat('surat_keluar.revisi', $suratKeluar->no_surat);

        if ($suratKeluar->pembuat) {
            $suratKeluar->pembuat->notify(new MenungguTandaTangan($suratKeluar, 'revisi'));
        }

        return back()->with('ok', 'Surat dikembalikan ke pembuat untuk direvisi.');
    }

    /** Staf mengunggah berkas final dan mencatat pengiriman. */
    public function kirim(Request $request, SuratKeluar $suratKeluar)
    {
        $this->authorize('kirim', $suratKeluar);

        $data = $request->validate([
            'file_final' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'tgl_kirim' => ['required', 'date'],
            'penerima' => ['required', 'string', 'max:255'],
            'metode_kirim' => ['required', Rule::in(['pos', 'email', 'kurir', 'langsung', 'lainnya'])],
            'no_bukti_kirim' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('file_final');
        $path = $file->store('surat-keluar/final');

        DB::transaction(function () use ($suratKeluar, $data, $file, $path, $request) {
            if ($suratKeluar->file_final_path) {
                Storage::delete($suratKeluar->file_final_path);
            }

            $suratKeluar->update([
                'file_final_path' => $path,
                'file_final_nama' => $file->getClientOriginalName(),
                'tgl_kirim' => $data['tgl_kirim'],
                'penerima' => $data['penerima'],
                'metode_kirim' => $data['metode_kirim'],
                'no_bukti_kirim' => $data['no_bukti_kirim'] ?? null,
                'status' => 'terkirim',
            ]);

            LogAktivitas::catat('surat_keluar.kirim', "{$suratKeluar->no_surat} → {$data['penerima']}");
        });

        return back()->with('ok', 'Berkas final tersimpan dan pengiriman tercatat.');
    }

    public function arsipkan(SuratKeluar $suratKeluar)
    {
        $this->authorize('arsipkan', $suratKeluar);

        $suratKeluar->update(['status' => 'diarsipkan']);
        LogAktivitas::catat('surat_keluar.arsip', $suratKeluar->no_surat);

        return back()->with('ok', 'Surat keluar diarsipkan.');
    }

    public function unduhFinal(SuratKeluar $suratKeluar)
    {
        $this->authorize('view', $suratKeluar);
        abort_unless($suratKeluar->file_final_path && Storage::exists($suratKeluar->file_final_path), 404);

        return Storage::download($suratKeluar->file_final_path, $suratKeluar->file_final_nama ?? 'surat-final.pdf');
    }

    private function query(Request $request): Builder
    {
        $q = SuratKeluar::with('instansi', 'unit', 'penandatangan')
            ->terlihatOleh($request->user())
            ->latest('tgl_surat');

        if ($s = $request->query('cari')) {
            $q->where(fn ($w) => $w->where('hal', 'like', "%{$s}%")->orWhere('no_surat', 'like', "%{$s}%"));
        }
        if ($status = $request->query('status')) {
            // Satu nilai atau daftar dipisah koma, mis. draf,revisi,menunggu_ttd.
            $q->whereIn('status', explode(',', $status));
        }
        if ($instansi = $request->query('instansi_id')) {
            $q->where('instansi_id', $instansi);
        }
        if ($klasifikasi = $request->query('klasifikasi_id')) {
            $q->where('klasifikasi_id', $klasifikasi);
        }
        if ($dari = $request->query('dari')) {
            $q->whereDate('tgl_surat', '>=', $dari);
        }
        if ($sampai = $request->query('sampai')) {
            $q->whereDate('tgl_surat', '<=', $sampai);
        }

        return $q;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'instansi_id' => ['nullable', 'exists:instansi,id'],
            'instansi_nama' => ['required_without:instansi_id', 'nullable', 'string', 'max:255'],
            'klasifikasi_id' => ['required', 'exists:klasifikasi,id'],
            'unit_id' => ['required', 'exists:unit_kerja,id'],
            'tgl_surat' => ['required', 'date'],
            'hal' => ['required', 'string', 'max:255'],
            'sifat' => ['required', Rule::in(['biasa', 'segera', 'penting', 'rahasia'])],
            'lampiran_ket' => ['nullable', 'string', 'max:100'],
            'isi_ringkas' => ['nullable', 'string'],
            'surat_masuk_id' => ['nullable', 'exists:surat_masuk,id'],
        ]);
    }

    private function instansiId(array $data): int
    {
        if (! empty($data['instansi_id'])) {
            return (int) $data['instansi_id'];
        }

        return Instansi::firstOrCreate(['nama' => trim($data['instansi_nama'])])->id;
    }
}
