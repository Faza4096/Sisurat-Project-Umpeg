<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengguna dengan kata sandi default wajib menggantinya sebelum memakai aplikasi.
 */
class WajibGantiSandi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->perluGantiSandi()
            && ! $request->routeIs('password.wajib.*')
            && ! $request->routeIs('logout')
            && ! $request->routeIs('password.update')) {
            return redirect()->route('password.wajib.edit');
        }

        return $next($request);
    }
}
