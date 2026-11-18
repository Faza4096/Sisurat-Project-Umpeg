<?php

use App\Http\Middleware\AkunAktif;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\WajibGantiSandi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Akun nonaktif diputus sesinya, lalu akun bersandi default wajib menggantinya.
        $middleware->appendToGroup('web', AkunAktif::class);
        $middleware->appendToGroup('web', WajibGantiSandi::class);

        // Header keamanan berlaku untuk semua respons, termasuk halaman galat.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Respons galat dirender di luar pipeline middleware, jadi header
        // keamanan dipasang ulang di sini agar 403/404/500 tetap memilikinya.
        $exceptions->respond(fn (Response $response) => SecurityHeaders::pasang($response));
    })->create();
