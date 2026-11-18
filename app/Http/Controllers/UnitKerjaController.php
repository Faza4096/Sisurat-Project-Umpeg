<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitKerjaController extends Controller
{
    public function index()
    {
        return view('master.unit-kerja.index', [
            'items' => UnitKerja::withCount(['pengguna', 'suratKeluar'])->orderBy('kode')->paginate(10),
        ]);
    }

    public function create()
    {
        return view('master.unit-kerja.form', ['item' => new UnitKerja()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', 'unique:unit_kerja,kode'],
            'nama' => ['required', 'string', 'max:255'],
        ]);

        UnitKerja::create($data);
        LogAktivitas::catat('unit_kerja.baru', "Unit kerja {$data['nama']} ditambahkan");

        return redirect()->route('unit-kerja.index')->with('ok', 'Unit kerja berhasil ditambahkan.');
    }

    public function edit(UnitKerja $unitKerja)
    {
        return view('master.unit-kerja.form', ['item' => $unitKerja]);
    }

    public function update(Request $request, UnitKerja $unitKerja)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('unit_kerja', 'kode')->ignore($unitKerja->id)],
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $unitKerja->update($data);
        LogAktivitas::catat('unit_kerja.ubah', "Unit kerja {$unitKerja->nama} diperbarui");

        return redirect()->route('unit-kerja.index')->with('ok', 'Unit kerja berhasil diperbarui.');
    }

    public function destroy(UnitKerja $unitKerja)
    {
        if ($unitKerja->pengguna()->exists() || $unitKerja->suratKeluar()->exists()) {
            return back()->with('err', 'Unit kerja masih dipakai pengguna atau surat keluar, tidak dapat dihapus.');
        }

        $nama = $unitKerja->nama;
        $unitKerja->delete();
        LogAktivitas::catat('unit_kerja.hapus', "Unit kerja {$nama} dihapus");

        return redirect()->route('unit-kerja.index')->with('ok', 'Unit kerja berhasil dihapus.');
    }
}
