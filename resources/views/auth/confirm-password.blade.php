<x-guest-layout judul="Konfirmasi Kata Sandi">
    <x-auth.heading eyebrow="Keamanan Akun" judul="Konfirmasi Kata Sandi"
                    keterangan="Area ini dilindungi. Masukkan kata sandi Anda sekali lagi untuk melanjutkan." />

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-auth.field name="password" label="Kata Sandi" type="password" placeholder="••••••••"
                      autocomplete="current-password" :autofocus="true" />

        <x-auth.submit label="Konfirmasi & Lanjutkan" />
    </form>
</x-guest-layout>
