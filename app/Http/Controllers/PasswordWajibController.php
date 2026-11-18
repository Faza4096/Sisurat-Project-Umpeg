<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Memaksa penggantian kata sandi default sebelum memakai aplikasi. */
class PasswordWajibController extends Controller
{
    public function edit()
    {
        return view('auth.password-wajib');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:password_lama'],
        ], [
            'password.different' => 'Kata sandi baru tidak boleh sama dengan kata sandi lama.',
        ]);

        $user = $request->user();
        $user->update([
            'password' => $data['password'],
            'must_change_password' => false,
            'password_diubah_pada' => now(),
        ]);

        LogAktivitas::catat('pengguna.ganti_sandi', 'Kata sandi default diganti');

        return redirect()->route('dashboard')->with('ok', 'Kata sandi berhasil diperbarui. Selamat bekerja.');
    }
}
