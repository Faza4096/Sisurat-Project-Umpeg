<x-guest-layout judul="Reset Kata Sandi">
    <x-auth.heading eyebrow="Pemulihan Akun" judul="Reset Kata Sandi"
                    keterangan="Tentukan kata sandi baru untuk akun dinas Anda, lalu masuk kembali ke portal." />

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-auth.field name="email" label="Email Dinas" type="email" icon="user"
                      :value="old('email', $request->email)" placeholder="nama@bogorkab.go.id"
                      autocomplete="username" :autofocus="true" />

        <x-auth.field name="password" label="Kata Sandi Baru" type="password" placeholder="••••••••"
                      autocomplete="new-password" help="Minimal 10 karakter, mengandung huruf dan angka." />

        <x-auth.field name="password_confirmation" label="Ulangi Kata Sandi Baru" type="password"
                      placeholder="••••••••" autocomplete="new-password" />

        <x-auth.submit label="Simpan Kata Sandi" />
    </form>

    <p class="mt-5 text-center text-sm text-slate-500">
        Tautan tidak berlaku?
        <a href="{{ route('password.request') }}" class="font-semibold text-gov-700 hover:text-gov-900 hover:underline">Minta tautan baru</a>
    </p>
</x-guest-layout>
