<x-guest-layout judul="Ganti Kata Sandi">
    <x-auth.heading eyebrow="Keamanan Akun" judul="Ganti Kata Sandi Anda"
                    keterangan="Akun masih memakai kata sandi sementara. Demi keamanan arsip surat, ganti kata sandi sebelum melanjutkan." />

    <form method="POST" action="{{ route('password.wajib.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <x-auth.field name="password_lama" label="Kata Sandi Saat Ini" type="password"
                      placeholder="••••••••" autocomplete="current-password" :autofocus="true" />

        <x-auth.field name="password" label="Kata Sandi Baru" type="password" placeholder="••••••••"
                      autocomplete="new-password" help="Minimal 10 karakter, mengandung huruf dan angka, berbeda dari sandi lama." />

        <x-auth.field name="password_confirmation" label="Ulangi Kata Sandi Baru" type="password"
                      placeholder="••••••••" autocomplete="new-password" />

        <x-auth.submit label="Simpan & Lanjutkan" />
    </form>

    <x-auth.footnote>
        <div class="flex items-center justify-between gap-3">
            <p>Kata sandi baru dipakai pada login berikutnya.</p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-semibold text-gov-700 hover:text-gov-900 hover:underline">Keluar</button>
            </form>
        </div>
    </x-auth.footnote>
</x-guest-layout>
