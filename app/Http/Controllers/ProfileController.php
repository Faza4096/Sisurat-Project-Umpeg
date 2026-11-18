<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Email adalah pintu masuk pemulihan akun: mengubahnya wajib dikonfirmasi
        // dengan kata sandi saat ini agar sesi yang dibajak tidak bisa mengambil
        // alih akun secara permanen.
        if ($request->validated('email') !== $user->email) {
            $request->validate([
                'current_password' => ['required', 'current_password'],
            ], [
                'current_password.required' => 'Masukkan kata sandi saat ini untuk mengubah email.',
                'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
            ]);
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /*
    | Penghapusan akun sendiri dinonaktifkan: akun pegawai hanya dikelola
    | administrator melalui menu Pengguna agar jejak surat tetap utuh.
    */
}
