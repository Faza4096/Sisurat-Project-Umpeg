<?php

namespace App\Http\Controllers;

use App\Models\Lampiran;
use Illuminate\Support\Facades\Storage;

class LampiranController extends Controller
{
    /** Unduh lampiran mengikuti aturan akses surat induknya. */
    public function unduh(Lampiran $lampiran)
    {
        $this->authorize('unduh', $lampiran);
        abort_unless(Storage::exists($lampiran->path), 404, 'Berkas tidak ditemukan.');

        return Storage::download($lampiran->path, $lampiran->nama_file);
    }
}
