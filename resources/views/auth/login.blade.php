<x-guest-layout judul="Masuk">
    <x-auth.heading eyebrow="Portal Tata Naskah Dinas" judul="Masuk ke SiSurat"
                    keterangan="Silakan masuk dengan akun dinas Anda untuk mengelola surat dan disposisi." />

    <x-auth-session-status class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-auth.field name="email" label="Email Dinas" type="email" icon="user"
                      :value="old('email')" placeholder="nama@bogorkab.go.id"
                      autocomplete="username" :autofocus="true" />

        <x-auth.field name="password" label="Kata Sandi" type="password" placeholder="••••••••"
                      autocomplete="current-password" />

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate-300 text-gov-700 focus:ring-gov-500">
                Ingat saya
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-gov-700 hover:text-gov-900 hover:underline">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <x-auth.submit label="Masuk ke Portal" icon="logout" />
    </form>

    <x-auth.footnote>
        <p>Akun pegawai hanya dibuat administrator instansi. Hubungi administrator bila belum memiliki akses.</p>
        <p class="mt-1.5">Kata sandi sementara wajib diganti saat login pertama sebelum portal dapat digunakan.</p>
    </x-auth.footnote>
</x-guest-layout>
