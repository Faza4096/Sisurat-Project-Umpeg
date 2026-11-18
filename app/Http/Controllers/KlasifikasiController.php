<?php

namespace App\Http\Controllers;

use App\Models\Klasifikasi;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KlasifikasiController extends Controller
{
    public function index()
    {
        return view('master.klasifikasi.index', [
            'items' => Klasifikasi::withCount(['suratMasuk', 'suratKeluar'])->orderBy('kode')->paginate(10),
        ]);
    }

    public function create()
    {
        return view('master.klasifikasi.form', ['item' => new Klasifikasi()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', 'unique:klasifikasi,kode'],
            'uraian' => ['required', 'string', 'max:255'],
        ]);

        Klasifikasi::create($data);
        LogAktivitas::catat('klasifikasi.baru', "Klasifikasi {$data['kode']} ditambahkan");

        return redirect()->route('klasifikasi.index')->with('ok', 'Klasifikasi berhasil ditambahkan.');
    }

    public function edit(Klasifikasi $klasifikasi)
    {
        return view('master.klasifikasi.form', ['item' => $klasifikasi]);
    }

    public function update(Request $request, Klasifikasi $klasifikasi)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('klasifikasi', 'kode')->ignore($klasifikasi->id)],
            'uraian' => ['required', 'string', 'max:255'],
        ]);

        $klasifikasi->update($data);
        LogAktivitas::catat('klasifikasi.ubah', "Klasifikasi {$klasifikasi->kode} diperbarui");

        return redirect()->route('klasifikasi.index')->with('ok', 'Klasifikasi berhasil diperbarui.');
    }

    public function destroy(Klasifikasi $klasifikasi)
    {
        if ($klasifikasi->suratMasuk()->exists() || $klasifikasi->suratKeluar()->exists()) {
            return back()->with('err', 'Klasifikasi masih dipakai surat, tidak dapat dihapus.');
        }

        $kode = $klasifikasi->kode;
        $klasifikasi->delete();
        LogAktivitas::catat('klasifikasi.hapus', "Klasifikasi {$kode} dihapus");

        return redirect()->route('klasifikasi.index')->with('ok', 'Klasifikasi berhasil dihapus.');
    }
}
