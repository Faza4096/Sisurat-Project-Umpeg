<?php

namespace App\Http\Controllers;

use App\Models\DisposisiTujuan;
use App\Models\LogAktivitas;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** Urutan sifat: segera dulu, lalu penting, rahasia, terakhir biasa. */
    private const URUT_SIFAT = "case sifat when 'segera' then 0 when 'penting' then 1 when 'rahasia' then 2 else 3 end";

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $peran = $user->role?->nama ?? 'staf';

        return view('dashboard', [
            'peran' => $peran,
            'instansi' => PengaturanInstansi::current()->nama,
            'ringkasan' => $this->ringkasan($user),
            'bagian' => $this->bagian($user),
            'tren' => $this->tren($user),
            'akun' => $peran === 'admin' ? $this->ringkasanAkun() : null,
            'log' => $peran === 'admin'
                ? LogAktivitas::with('pengguna')->latest('waktu')->take(8)->get()
                : collect(),
        ]);
    }

    /** Satu baris ringkasan, bukan deretan kartu statistik. */
    private function ringkasan(User $user): array
    {
        $awalBulan = now()->startOfMonth();

        return [
            'masuk' => SuratMasuk::terlihatOleh($user)->where('tgl_diterima', '>=', $awalBulan)->count(),
            'keluar' => SuratKeluar::terlihatOleh($user)->where('tgl_surat', '>=', $awalBulan)->count(),
            'terbuka' => $this->tindakLanjutQuery($user)->count(),
        ];
    }

    private function tindakLanjutQuery(User $user)
    {
        return DisposisiTujuan::where('status_tindak_lanjut', '!=', 'selesai')
            ->when(! $user->punyaRole('admin', 'staf'),
                fn ($q) => $q->where('unit_id', $user->unit_id));
    }

    /** Daftar pekerjaan sesuai peran. Setiap bagian berisi baris siap klik. */
    private function bagian(User $user): array
    {
        return match ($user->role?->nama) {
            // Satu peran operator memegang seluruh antrean; bagian "belum
            // diserahkan" dilewati karena suratnya sudah tampil di antrean ACC.
            'staf', 'admin' => [...$this->bagianPimpinan($user), ...$this->bagianTu($user, false)],
            default => [],
        };
    }

    private function bagianTu(User $user, bool $denganPenyerahan = true): array
    {
        $belumDiserahkan = $denganPenyerahan
            ? SuratMasuk::with('instansi')
                ->terlihatOleh($user)
                ->where('status', 'diterima')
                ->orderByRaw(self::URUT_SIFAT)
                ->orderBy('tgl_diterima')
                ->take(8)
                ->get()
                ->map(fn (SuratMasuk $s) => $this->barisSuratMasuk($s, $s->tgl_diterima))
            : collect();

        $sudahAcc = SuratKeluar::with('instansi')
            ->terlihatOleh($user)
            ->where('status', 'disetujui')
            ->orderByRaw(self::URUT_SIFAT)
            ->orderBy('disetujui_pada')
            ->take(8)
            ->get()
            ->map(fn (SuratKeluar $s) => $this->barisSuratKeluar($s, $s->disetujui_pada));

        $belumArsip = SuratMasuk::with('instansi')
            ->terlihatOleh($user)
            ->where('status', 'selesai')
            ->latest('tgl_diterima')
            ->take(5)
            ->get()
            ->map(fn (SuratMasuk $s) => $this->barisSuratMasuk($s, null, 'Surat masuk'))
            ->merge(
                SuratKeluar::with('instansi')
                    ->terlihatOleh($user)
                    ->where('status', 'terkirim')
                    ->latest('tgl_kirim')
                    ->take(5)
                    ->get()
                    ->map(fn (SuratKeluar $s) => $this->barisSuratKeluar($s, null, 'Surat keluar'))
            )
            ->take(8);

        return [
            ...($denganPenyerahan ? [[
                'judul' => 'Belum diberi disposisi',
                'catatan' => 'Catat lalu beri disposisi.',
                'items' => $belumDiserahkan,
                'kosong' => 'Semua surat masuk sudah didisposisikan.',
                'tautan' => ['label' => 'Catat surat masuk', 'url' => route('surat-masuk.create')],
            ]] : []),
            [
                'judul' => 'Sudah di-ACC, belum dikirim',
                'catatan' => 'Unggah berkas final dan catat pengiriman.',
                'items' => $sudahAcc,
                'kosong' => 'Tidak ada surat yang menunggu pengiriman.',
                'tautan' => ['label' => 'Daftar surat keluar', 'url' => route('surat-keluar.index', ['status' => 'disetujui'])],
            ],
            [
                'judul' => 'Belum diarsipkan',
                'catatan' => 'Tindak lanjut selesai atau surat sudah dikirim, tetapi arsip belum dicatat.',
                'items' => $belumArsip,
                'kosong' => 'Semua surat sudah diarsipkan.',
                'tautan' => null,
            ],
        ];
    }

    private function bagianPimpinan(User $user): array
    {
        $masuk = SuratMasuk::with('instansi')
            ->terlihatOleh($user)
            ->where('status', 'diterima')
            ->orderByRaw(self::URUT_SIFAT)
            ->orderBy('tgl_diterima')
            ->take(10)
            ->get()
            ->map(fn (SuratMasuk $s) => $this->barisSuratMasuk($s, $s->tgl_diterima) + [
                'aksi' => ['label' => 'Disposisi', 'url' => route('disposisi.acc.form', $s)],
            ]);

        $keluar = SuratKeluar::with('unit', 'instansi')
            ->terlihatOleh($user)
            ->where('status', 'menunggu_ttd')
            ->orderByRaw(self::URUT_SIFAT)
            ->orderBy('diajukan_pada')
            ->take(10)
            ->get()
            ->map(fn (SuratKeluar $s) => $this->barisSuratKeluar($s, $s->diajukan_pada));

        return [
            [
                'judul' => 'Surat masuk menunggu ACC',
                'catatan' => 'Diurutkan menurut sifat surat, lalu lama menunggu.',
                'items' => $masuk,
                'kosong' => 'Tidak ada surat masuk yang menunggu disposisi.',
                'tautan' => ['label' => 'Semua surat masuk', 'url' => route('surat-masuk.index', ['status' => 'diterima'])],
            ],
            [
                'judul' => 'Surat keluar menunggu ACC',
                'catatan' => 'Periksa isi surat sebelum menyetujui atau meminta revisi.',
                'items' => $keluar,
                'kosong' => 'Tidak ada surat keluar yang menunggu persetujuan.',
                'tautan' => ['label' => 'Semua pengajuan', 'url' => route('surat-keluar.index', ['status' => 'menunggu_ttd'])],
            ],
        ];
    }

    private function barisSuratMasuk(SuratMasuk $s, ?Carbon $acuan, ?string $jenis = null): array
    {
        return [
            'nomor' => $s->no_agenda,
            'judul' => $s->perihal,
            'meta' => trim(($jenis ? $jenis . ' · ' : '') . ($s->instansi?->nama ?? '-')),
            'sifat' => $s->sifat,
            'waktu' => $acuan ? 'Masuk ' . $acuan->translatedFormat('d M Y') : optional($s->tgl_diterima)->translatedFormat('d M Y'),
            'keadaan' => $acuan && $acuan->lt(now()->subDays(3)) ? 'dekat' : null,
            'url' => route('surat-masuk.show', $s),
        ];
    }

    private function barisSuratKeluar(SuratKeluar $s, ?Carbon $acuan, ?string $jenis = null): array
    {
        return [
            'nomor' => $s->no_surat,
            'judul' => $s->hal,
            'meta' => trim(($jenis ? $jenis . ' · ' : '') . ($s->instansi?->nama ?? '-')),
            'sifat' => $s->sifat,
            'waktu' => $acuan ? 'Diajukan ' . $acuan->translatedFormat('d M Y') : optional($s->tgl_kirim)->translatedFormat('d M Y'),
            'keadaan' => $acuan && $acuan->lt(now()->subDays(3)) ? 'dekat' : null,
            'url' => route('surat-keluar.show', $s),
        ];
    }

    /** Grafik enam bulan dari dua query agregat (bukan dua belas query hitung). */
    private function tren(User $user): array
    {
        $awal = now()->startOfMonth()->subMonths(5);

        $hitung = function ($items) {
            return $items->groupBy(fn ($row) => $row->tanggal->format('Y-m'))->map->count();
        };

        $masuk = $hitung(
            SuratMasuk::terlihatOleh($user)->where('tgl_diterima', '>=', $awal)->get(['tgl_diterima'])
                ->map(fn ($s) => (object) ['tanggal' => $s->tgl_diterima])
        );

        $keluar = $hitung(
            SuratKeluar::terlihatOleh($user)->where('tgl_surat', '>=', $awal)->get(['tgl_surat'])
                ->map(fn ($s) => (object) ['tanggal' => $s->tgl_surat])
        );

        $bulan = collect(range(5, 0))->map(function ($mundur) use ($masuk, $keluar) {
            $awal = now()->copy()->subMonths($mundur)->startOfMonth();
            $kunci = $awal->format('Y-m');

            return [
                'label' => $awal->translatedFormat('M'),
                'masuk' => (int) ($masuk[$kunci] ?? 0),
                'keluar' => (int) ($keluar[$kunci] ?? 0),
            ];
        });

        return [
            'bulan' => $bulan,
            'maks' => max(1, $bulan->max(fn ($b) => max($b['masuk'], $b['keluar']))),
        ];
    }

    private function ringkasanAkun(): array
    {
        return [
            'total' => User::count(),
            'aktif' => User::where('aktif', true)->count(),
            'wajib_ganti' => User::where('must_change_password', true)->count(),
            'peran' => User::query()
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->selectRaw('roles.nama, count(*) as jumlah')
                ->groupBy('roles.nama')
                ->pluck('jumlah', 'nama'),
        ];
    }
}
