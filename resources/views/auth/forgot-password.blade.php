<x-guest-layout judul="Lupa Kata Sandi">
    <x-auth.heading eyebrow="Pemulihan Akun" judul="Lupa Kata Sandi"
                    keterangan="Masukkan email dinas Anda. Kami akan mengirim tautan untuk membuat kata sandi baru." />

    <x-auth-session-status class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-auth.field name="email" label="Email Dinas" type="email" icon="user"
                      :value="old('email')" placeholder="nama@bogorkab.go.id"
                      autocomplete="username" :autofocus="true" />

        <x-auth.submit label="Kirim Tautan Reset" icon="mail-in" />
    </form>

    <p class="mt-5 text-center text-sm text-slate-500">
        Sudah ingat kata sandi?
        <a href="{{ route('login') }}" class="font-semibold text-gov-700 hover:text-gov-900 hover:underline">Kembali ke halaman masuk</a>
    </p>

    <x-auth.footnote>
        <p>Tautan pemulihan hanya berlaku terbatas dan dapat dipakai satu kali. Bila email belum masuk, periksa folder spam.</p>
    </x-auth.footnote>
</x-guest-layout>
