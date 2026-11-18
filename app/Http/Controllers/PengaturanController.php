<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\PengaturanInstansi;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PengaturanController extends Controller
{
    public function edit()
    {
        return view('pengaturan.edit', ['p' => PengaturanInstansi::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'pejabat_nama' => ['nullable', 'string', 'max:255'],
            'pejabat_jabatan' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $pengaturan = PengaturanInstansi::current();
        unset($data['logo']);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');

            // Ekstensi diambil dari hasil validasi isi berkas, bukan dari nama
            // kiriman peramban: berkas berisi PNG yang dinamai .php tidak boleh
            // berakhir sebagai berkas yang dieksekusi web server.
            $ekstensi = match ($file->guessExtension()) {
                'jpeg', 'jpg' => 'jpg',
                'png' => 'png',
                'webp' => 'webp',
                default => null,
            };

            if ($ekstensi === null) {
                throw ValidationException::withMessages([
                    'logo' => 'Logo harus berupa berkas PNG, JPG, atau WebP.',
                ]);
            }

            $nama = 'logo-instansi.' . $ekstensi;
            $file->move(public_path('img/pengaturan'), $nama);

            $lama = $pengaturan->logo_path;

            // Logo lama dibuang agar berkas dengan ekstensi lama tidak tertinggal
            // di webroot. Kegagalan hapus tidak boleh menggagalkan penyimpanan.
            if ($lama && str_starts_with($lama, 'img/pengaturan/') && $lama !== 'img/pengaturan/' . $nama) {
                $berkasLama = public_path($lama);

                if (is_file($berkasLama)) {
                    @unlink($berkasLama);
                }
            }

            $data['logo_path'] = 'img/pengaturan/' . $nama;
        }

        $pengaturan->update($data);
        LogAktivitas::catat('pengaturan.ubah', 'Identitas instansi diperbarui');

        return back()->with('ok', 'Pengaturan instansi disimpan. Identitas di seluruh aplikasi dan kop kartu disposisi memakai data terbaru.');
    }
}
