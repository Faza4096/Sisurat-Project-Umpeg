<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk seluruh respons. Selain middleware, header dipasang
 * ulang lewat handler pengecualian karena Kernel merender galat (403/404/500)
 * di luar pipeline middleware.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::pasang($next($request));
    }

    public static function pasang(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', self::kebijakan());

        return $response;
    }

    /**
     * Alpine mengevaluasi x-data lewat new Function sehingga 'unsafe-eval' masih
     * diperlukan; bila kelak memakai build CSP-safe (@alpinejs/csp), hapus
     * 'unsafe-eval'. Atribut style inline (lebar grafik, modal) memerlukan
     * 'unsafe-inline' pada style-src.
     */
    private static function kebijakan(): string
    {
        $skrip = "'self' 'unsafe-eval'";
        $gaya = "'self' 'unsafe-inline'";
        $koneksi = "'self'";

        // Vite dev server memuat aset dan HMR dari origin terpisah.
        if ($vite = self::originVite()) {
            $skrip .= ' ' . $vite;
            $gaya .= ' ' . $vite;
            $koneksi .= ' ' . $vite . ' ' . preg_replace('/^http/', 'ws', $vite);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$skrip}",
            "style-src {$gaya}",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src {$koneksi}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /** Origin Vite dari public/hot, atau null bila aset memakai hasil build. */
    private static function originVite(): ?string
    {
        $berkas = public_path('hot');

        if (! is_file($berkas)) {
            return null;
        }

        $isi = @file_get_contents($berkas);

        if ($isi === false) {
            return null;
        }

        $bagian = parse_url(trim($isi));

        if (! isset($bagian['host'])) {
            return null;
        }

        $port = isset($bagian['port']) ? ':' . $bagian['port'] : '';

        return ($bagian['scheme'] ?? 'http') . '://' . $bagian['host'] . $port;
    }
}
