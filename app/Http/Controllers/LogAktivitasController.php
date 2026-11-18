<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Http\Request;

class LogAktivitasController extends Controller
{
    public function index(Request $request)
    {
        $items = LogAktivitas::with('pengguna')
            ->when($request->query('cari'), fn ($q, $cari) => $q->where(fn ($w) => $w->where('aksi', 'like', "%{$cari}%")
                ->orWhere('keterangan', 'like', "%{$cari}%")))
            ->when($request->query('pengguna'), fn ($q, $id) => $q->where('pengguna_id', $id))
            ->latest('waktu')
            ->paginate(20)
            ->withQueryString();

        return view('log.index', [
            'items' => $items,
            'pengguna' => User::orderBy('name')->get(),
        ]);
    }
}
