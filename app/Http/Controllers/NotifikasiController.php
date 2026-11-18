<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        return view('notifikasi.index', [
            'daftar' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Tandai satu notifikasi dibaca lalu buka tautannya. */
    public function baca(Request $request, string $id)
    {
        $notifikasi = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notifikasi->markAsRead();

        return redirect($notifikasi->data['url'] ?? route('notifikasi.index'));
    }

    public function bacaSemua(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('ok', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
