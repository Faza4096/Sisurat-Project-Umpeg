<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /** Satu jawaban untuk semua hasil agar tidak bisa dipakai memetakan akun. */
    private const PESAN = 'Jika email tersebut terdaftar, tautan pengaturan kata sandi telah dikirim.';

    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Hasil (terkirim, email tidak dikenal, atau sedang dibatasi) sengaja
        // tidak dibedakan pada respons: perbedaan pesan membocorkan email mana
        // yang terdaftar sebagai akun pegawai.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', self::PESAN);
    }
}
