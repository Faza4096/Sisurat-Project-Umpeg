<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\Instansi;
use App\Models\Klasifikasi;
use App\Models\Lampiran;
use App\Models\LogAktivitas;
use App\Models\SuratKeluar;
use App\Http\Requests\HapusPermanenRequest;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Services\HapusSuratService;
use App\Services\NomorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SuratMasukController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->query($request);

        return view('surat-masuk.index', [
            'surat' => (clone $query)->paginate(15)->withQueryString(),
            // reorder(): MySQL menolak ORDER BY kolom non-agregat pada GROUP BY.
            'ringkasan' => (clone $query)->reorder()->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status'),
            'units' => UnitKerja::orderBy('nama')->get(),
            'instansi' => Instansi::orderBy('nama')->get(),
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', SuratMasuk::class);

        return view('surat-masuk.create', [
            's' => new SuratMasuk(),
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
            'instansi' => Instansi::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', SuratMasuk::class);
        $data = $this->validated($request);

        $surat = DB::transaction(function () use ($data, $request) {
            $surat = SuratMasuk::create([
                'no_agenda' => NomorService::agenda($request->user()->unit_id ?? UnitKerja::firstOrFail()->id),
                'no_surat' => $data['no_surat'],
                'tgl_surat' => $data['tgl_surat'],
                'tgl_diterima' => $data['tgl_diterima'],
                'instansi_id' => $this->instansiId($data),
                'klasifikasi_id' => $data['klasifikasi_id'] ?? null,
                'perihal' => $data['perihal'],
                'sifat' => $data['sifat'],
                'status' => 'diterima',
                'dicatat_oleh' => $request->user()->id,
            ]);

            $this->simpanScan($surat, $request);
            LogAktivitas::catat('surat_masuk.baru', $surat->no_agenda);

            return $surat;
        });

        return redirect()->route('surat-masuk.show', $surat)
            ->with('ok', "Surat tersimpan dengan nomor agenda {$surat->no_agenda}.");
    }

    public function show(SuratMasuk $suratMasuk)
    {
        $this->authorize('view', $suratMasuk);

        $suratMasuk->load([
            'instansi', 'klasifikasi', 'lampiran.pengunggah', 'pencatat', 'pengarsip',
            'disposisi.pembuat', 'disposisi.tujuan.unit', 'disposisi.tujuan.penyelesai',
            'balasan',
        ]);

        return view('surat-masuk.show', [
            's' => $suratMasuk,
            'units' => UnitKerja::orderBy('nama')->get(),
        ]);
    }

    public function edit(SuratMasuk $suratMasuk)
    {
        $this->authorize('update', $suratMasuk);

        return view('surat-masuk.edit', [
            's' => $suratMasuk,
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
            'instansi' => Instansi::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, SuratMasuk $suratMasuk)
    {
        $this->authorize('update', $suratMasuk);
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request, $suratMasuk) {
            $suratMasuk->update([
                'no_surat' => $data['no_surat'],
                'tgl_surat' => $data['tgl_surat'],
                'tgl_diterima' => $data['tgl_diterima'],
                'instansi_id' => $this->instansiId($data),
                'klasifikasi_id' => $data['klasifikasi_id'] ?? null,
                'perihal' => $data['perihal'],
                'sifat' => $data['sifat'],
            ]);

            $this->simpanScan($suratMasuk, $request);
            LogAktivitas::catat('surat_masuk.ubah', $suratMasuk->no_agenda);
        });

        return redirect()->route('surat-masuk.show', $suratMasuk)->with('ok', 'Surat masuk berhasil diperbarui.');
    }

    /** Soft delete: arsip digital tetap ada, berkas fisik di disk tidak dihapus. */
    public function destroy(SuratMasuk $suratMasuk)
    {
        $this->authorize('delete', $suratMasuk);

        $nomor = $suratMasuk->no_agenda;
        $suratMasuk->delete();
        LogAktivitas::catat('surat_masuk.hapus', $nomor);

        return redirect()->route('surat-masuk.index')->with('ok', "Surat masuk {$nomor} dipindahkan ke arsip terhapus.");
    }

    /** Halaman konfirmasi sebelum surat dihapus permanen. */
    public function formHapusPermanen(SuratMasuk $suratMasuk)
    {
        $this->authorize('hapusPermanen', $suratMasuk);
        $this->authorize('view', $suratMasuk);

        return view('surat.hapus-permanen', [
            'judul' => 'Hapus Permanen Surat Masuk',
            'nomor' => $suratMasuk->no_agenda,
            'aksi' => route('surat-masuk.hapus-permanen', $suratMasuk),
            'batal' => route('surat-masuk.show', $suratMasuk),
            'rincian' => [
                'Nomor agenda' => $suratMasuk->no_agenda,
                'Nomor surat' => $suratMasuk->no_surat,
                'Perihal' => $suratMasuk->perihal,
                'Disposisi yang ikut terhapus' => $suratMasuk->disposisi()->withTrashed()->count(),
                'Lampiran' => $suratMasuk->lampiran()->count() . ' berkas',
            ],
            'penjelasan' => 'Disposisi, tujuan disposisi, lampiran, kartu PDF, dan bukti tindak lanjut ikut '
                . 'dibuang dari basis data dan penyimpanan berkas. Surat keluar yang membalas surat ini tetap ada. '
                . 'Data yang sudah dihapus tidak dapat dipulihkan.',
        ]);
    }

    /** Hapus permanen: surat beserta disposisi, lampiran, dan kartu PDF ikut dibuang. */
    public function hapusPermanen(HapusPermanenRequest $request, SuratMasuk $suratMasuk, HapusSuratService $hapus)
    {
        $this->authorize('hapusPermanen', $suratMasuk);

        $nomor = $suratMasuk->no_agenda;
        $hapus->masuk($suratMasuk);
        LogAktivitas::catat('surat_masuk.hapus_permanen', $nomor);

        return redirect()->route('surat-masuk.index')
            ->with('ok', "Surat masuk {$nomor} beserta disposisinya dihapus permanen.");
    }

    /** Staf mengarsipkan surat yang tindak lanjutnya sudah selesai. */
    public function arsipkan(Request $request, SuratMasuk $suratMasuk)
    {
        $this->authorize('arsipkan', $suratMasuk);

        $data = $request->validate([
            'lokasi_arsip' => ['required', 'string', 'max:255'],
        ]);

        $suratMasuk->update([
            'status' => 'diarsipkan',
            'lokasi_arsip' => $data['lokasi_arsip'],
            'diarsipkan_pada' => now(),
            'diarsipkan_oleh' => $request->user()->id,
        ]);

        LogAktivitas::catat('surat_masuk.arsip', "{$suratMasuk->no_agenda} → {$data['lokasi_arsip']}");

        return back()->with('ok', 'Surat ditandai diarsipkan beserta lokasi arsip fisiknya.');
    }

    /** Kelola lampiran/scan tambahan pada surat masuk. */
    public function unggahLampiran(Request $request, SuratMasuk $suratMasuk)
    {
        $this->authorize('kelolaLampiran', $suratMasuk);

        $request->validate([
            'berkas' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'jenis' => ['nullable', Rule::in(array_keys(Lampiran::JENIS))],
        ]);

        $file = $request->file('berkas');
        $suratMasuk->lampiran()->create([
            'nama_file' => $file->getClientOriginalName(),
            'path' => $file->store('lampiran/masuk'),
            'jenis' => $request->input('jenis', 'scan'),
            'diunggah_oleh' => $request->user()->id,
        ]);

        LogAktivitas::catat('lampiran.unggah', "{$suratMasuk->no_agenda}: {$file->getClientOriginalName()}");

        return back()->with('ok', 'Lampiran berhasil diunggah.');
    }

    public function hapusLampiran(Lampiran $lampiran)
    {
        $this->authorize('hapus', $lampiran);

        Storage::delete($lampiran->path);
        $nama = $lampiran->nama_file;
        $lampiran->delete();
        LogAktivitas::catat('lampiran.hapus', $nama);

        return back()->with('ok', 'Lampiran dihapus.');
    }

    /** Tombol "Buat Balasan" pada surat masuk. */
    public function balas(SuratMasuk $suratMasuk)
    {
        $this->authorize('view', $suratMasuk);
        $this->authorize('create', SuratKeluar::class);

        return redirect()->route('surat-keluar.create', ['surat_masuk_id' => $suratMasuk->id]);
    }

    private function query(Request $request): Builder
    {
        $q = SuratMasuk::with('instansi', 'klasifikasi')
            ->terlihatOleh($request->user())
            ->latest('tgl_diterima');

        if ($s = $request->query('cari')) {
            $q->where(fn ($w) => $w->where('perihal', 'like', "%{$s}%")
                ->orWhere('no_surat', 'like', "%{$s}%")
                ->orWhere('no_agenda', 'like', "%{$s}%"));
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($sifat = $request->query('sifat')) {
            $q->where('sifat', $sifat);
        }
        if ($instansi = $request->query('instansi_id')) {
            $q->where('instansi_id', $instansi);
        }
        if ($klasifikasi = $request->query('klasifikasi_id')) {
            $q->where('klasifikasi_id', $klasifikasi);
        }
        if ($dari = $request->query('dari')) {
            $q->whereDate('tgl_diterima', '>=', $dari);
        }
        if ($sampai = $request->query('sampai')) {
            $q->whereDate('tgl_diterima', '<=', $sampai);
        }

        return $q;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'instansi_id' => ['nullable', 'exists:instansi,id'],
            'instansi_nama' => ['required_without:instansi_id', 'nullable', 'string', 'max:255'],
            'no_surat' => ['required', 'string', 'max:100'],
            'tgl_surat' => ['required', 'date'],
            'tgl_diterima' => ['required', 'date', 'after_or_equal:tgl_surat'],
            'klasifikasi_id' => ['nullable', 'exists:klasifikasi,id'],
            'perihal' => ['required', 'string', 'max:255'],
            'sifat' => ['required', Rule::in(['biasa', 'segera', 'penting', 'rahasia'])],
            'scan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
    }

    private function instansiId(array $data): int
    {
        if (! empty($data['instansi_id'])) {
            return (int) $data['instansi_id'];
        }

        return Instansi::firstOrCreate(['nama' => trim($data['instansi_nama'])])->id;
    }

    private function simpanScan(SuratMasuk $surat, Request $request): void
    {
        if (! $request->hasFile('scan')) {
            return;
        }

        $file = $request->file('scan');
        $surat->lampiran()->create([
            'nama_file' => $file->getClientOriginalName(),
            'path' => $file->store('lampiran/masuk'),
            'jenis' => 'scan',
            'diunggah_oleh' => $request->user()->id,
        ]);
    }
}
