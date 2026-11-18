<x-guest-layout judul="Verifikasi Email">
    <x-auth.heading eyebrow="Verifikasi Akun" judul="Verifikasi Email"
                    keterangan="Kami telah mengirim tautan verifikasi ke alamat email akun Anda. Buka tautan tersebut untuk mengaktifkan akses portal." />

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            Tautan verifikasi baru telah dikirim ke alamat email akun Anda.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf

        <x-auth.submit label="Kirim Ulang Email Verifikasi" icon="mail-in" />
    </form>

    <x-auth.footnote>
        <div class="flex items-center justify-between gap-3">
            <p>Belum menerima email? Periksa folder spam sebelum mengirim ulang.</p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-semibold text-gov-700 hover:text-gov-900 hover:underline">Keluar</button>
            </form>
        </div>
    </x-auth.footnote>
</x-guest-layout>
