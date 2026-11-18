<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Membuat administrator pertama tanpa akun demo bersandi bawaan. */
class BuatAdministrator extends Command
{
    protected $signature = 'sisurat:buat-admin
                            {email? : Surel administrator pertama}
                            {--nama= : Nama lengkap}
                            {--sandi= : Kata sandi; kosongkan agar ditanyakan tanpa echo}';

    protected $description = 'Membuat akun administrator pertama untuk instalasi baru';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Surel administrator');
        $nama = $this->option('nama') ?? $this->ask('Nama lengkap');

        if ($this->option('sandi')) {
            $sandi = $konfirmasi = (string) $this->option('sandi');
        } else {
            $sandi = (string) $this->secret('Kata sandi (minimal 10 karakter, memuat huruf dan angka)');
            $konfirmasi = (string) $this->secret('Ulangi kata sandi');
        }

        $identitas = Validator::make(
            ['nama' => $nama, 'email' => $email],
            [
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            ],
        );

        if ($identitas->fails()) {
            foreach ($identitas->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        if ($sandi !== $konfirmasi) {
            $this->error('Ulangi kata sandi tidak sama.');

            return self::FAILURE;
        }

        $sandiValid = Validator::make(
            ['sandi' => $sandi],
            ['sandi' => ['required', Password::defaults()]],
        );

        if ($sandiValid->fails()) {
            foreach ($sandiValid->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $nama,
            'email' => $email,
            'password' => Hash::make($sandi),
            'role_id' => Role::firstOrCreate(['nama' => 'admin'])->id,
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password_diubah_pada' => now(),
            'aktif' => true,
        ]);

        $this->info("Administrator {$user->email} dibuat. Masuk lewat halaman login dengan sandi yang baru saja diisi.");
        $this->line('Akun staf berikutnya dibuat administrator dari menu Pengguna.');

        return self::SUCCESS;
    }
}
