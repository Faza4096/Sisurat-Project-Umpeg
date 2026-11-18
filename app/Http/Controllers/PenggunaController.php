<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\Role;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\SesiPenggunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PenggunaController extends Controller
{
    public function index(Request $request)
    {
        $items = User::with(['role', 'unit'])
            ->when($request->query('cari'), function ($q, $cari) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$cari}%")
                    ->orWhere('email', 'like', "%{$cari}%")
                    ->orWhere('nip', 'like', "%{$cari}%"));
            })
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('role', fn ($r) => $r->where('nama', $role)))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('master.pengguna.index', ['items' => $items, 'roles' => Role::orderBy('nama')->get()]);
    }

    public function create()
    {
        return view('master.pengguna.form', $this->options(new User()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        // Satu aturan sandi dengan reset, profil, dan penggantian sandi wajib.
        $data['password'] = $request->validate([
            'password' => ['required', 'string', Password::defaults()],
        ])['password'];
        // Akun baru selalu memakai sandi sementara: wajib diganti saat login pertama.
        $data['must_change_password'] = true;

        $user = User::create($data);
        LogAktivitas::catat('pengguna.baru', "Pengguna {$user->name} ditambahkan");

        return redirect()->route('pengguna.index')->with('ok', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $pengguna)
    {
        return view('master.pengguna.form', $this->options($pengguna));
    }

    public function update(Request $request, User $pengguna, SesiPenggunaService $sesi)
    {
        $data = $this->validated($request, $pengguna);
        $sandiDireset = $request->filled('password');

        if ($sandiDireset) {
            $data['password'] = $request->validate([
                'password' => ['string', Password::defaults()],
            ])['password'];
            // Sandi yang direset administrator wajib diganti pengguna.
            $data['must_change_password'] = true;
        }

        $pengguna->update($data);

        if ($sandiDireset) {
            // Perangkat yang sudah masuk harus terlempar: sesi lama dihapus dan
            // cookie "ingat saya" tidak berlaku lagi.
            $pengguna->setRememberToken(Str::random(60));
            $pengguna->save();

            $sesi->putuskan($pengguna);
        }

        LogAktivitas::catat('pengguna.ubah', "Pengguna {$pengguna->name} diperbarui");

        return redirect()->route('pengguna.index')->with('ok', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $pengguna)
    {
        if ($pengguna->is($request->user())) {
            return back()->with('err', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        if ($pengguna->suratMasukDiarsipkan()->exists() || $pengguna->suratKeluarDibuat()->exists()) {
            // Jejak surat harus tetap utuh: nonaktifkan, jangan hapus.
            $pengguna->update(['aktif' => false]);

            return back()->with('ok', 'Pengguna memiliki riwayat surat sehingga hanya dinonaktifkan.');
        }

        if ($pengguna->punyaRole('admin') && User::whereHas('role', fn ($q) => $q->where('nama', 'admin'))->count() <= 1) {
            return back()->with('err', 'Akun administrator terakhir tidak dapat dihapus.');
        }

        $nama = $pengguna->name;
        $pengguna->delete();
        LogAktivitas::catat('pengguna.hapus', "Pengguna {$nama} dihapus");

        return redirect()->route('pengguna.index')->with('ok', 'Pengguna berhasil dihapus.');
    }

    private function options(User $user): array
    {
        return [
            'item' => $user,
            'roles' => Role::orderBy('nama')->get(),
            'units' => UnitKerja::orderBy('nama')->get(),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:25'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => ['required', 'exists:roles,id'],
            'unit_id' => ['nullable', 'exists:unit_kerja,id'],
        ]);
    }
}
