<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstansiController extends Controller
{
    public function index(Request $request)
    {
        $items = Instansi::withCount(['suratMasuk', 'suratKeluar'])
            ->when($request->query('cari'), fn ($q, $cari) => $q->where('nama', 'like', "%{$cari}%"))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('master.instansi.index', ['items' => $items]);
    }

    public function create()
    {
        return view('master.instansi.form', ['item' => new Instansi()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Instansi::create($data);
        LogAktivitas::catat('instansi.baru', "Instansi {$data['nama']} ditambahkan");

        return redirect()->route('instansi.index')->with('ok', 'Instansi berhasil ditambahkan.');
    }

    public function edit(Instansi $instansi)
    {
        return view('master.instansi.form', ['item' => $instansi]);
    }

    public function update(Request $request, Instansi $instansi)
    {
        $data = $this->validated($request, $instansi);

        $instansi->update($data);
        LogAktivitas::catat('instansi.ubah', "Instansi {$instansi->nama} diperbarui");

        return redirect()->route('instansi.index')->with('ok', 'Instansi berhasil diperbarui.');
    }

    public function destroy(Instansi $instansi)
    {
        if ($instansi->suratMasuk()->exists() || $instansi->suratKeluar()->exists()) {
            return back()->with('err', 'Instansi masih terkait surat, tidak dapat dihapus.');
        }

        $nama = $instansi->nama;
        $instansi->delete();
        LogAktivitas::catat('instansi.hapus', "Instansi {$nama} dihapus");

        return redirect()->route('instansi.index')->with('ok', 'Instansi berhasil dihapus.');
    }

    private function validated(Request $request, ?Instansi $instansi = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('instansi', 'nama')->ignore($instansi?->id)],
            'alamat' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
